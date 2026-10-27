<?php

declare(strict_types=1);

namespace ArchitectureLab\Runtime;

use ArchitectureLab\Billing\Application\BillingService;
use ArchitectureLab\Billing\Application\Event\EnrollmentRequestedListener;
use ArchitectureLab\Billing\Infrastructure\DeterministicPaymentGateway;
use ArchitectureLab\Billing\Infrastructure\DocumentInvoiceRepository;
use ArchitectureLab\Catalog\Application\CatalogService;
use ArchitectureLab\Catalog\Infrastructure\DocumentCourseOfferingRepository;
use ArchitectureLab\Catalog\PublicApi\CatalogApi;
use ArchitectureLab\Catalog\PublicApi\Command\PublishCourse;
use ArchitectureLab\Cqrs\CommandBus;
use ArchitectureLab\Cqrs\QueryBus;
use ArchitectureLab\Enrollment\Application\Command\RequestEnrollmentHandler;
use ArchitectureLab\Enrollment\Application\Event\PaymentResultListener;
use ArchitectureLab\Enrollment\Infrastructure\BillingPaymentProcessor;
use ArchitectureLab\Enrollment\Infrastructure\CatalogSeatAllocator;
use ArchitectureLab\Enrollment\Infrastructure\DocumentEnrollmentRepository;
use ArchitectureLab\Enrollment\PublicApi\Command\RequestEnrollment;
use ArchitectureLab\Enrollment\PublicApi\EnrollmentApi;
use ArchitectureLab\Enrollment\PublicApi\EnrollmentQueryService;
use ArchitectureLab\Eventing\EventBus;
use ArchitectureLab\Eventing\EventNames;
use ArchitectureLab\Eventing\EventRecorder;
use ArchitectureLab\Eventing\IdempotentListener;
use ArchitectureLab\Eventing\InMemoryEventBus;
use ArchitectureLab\Eventing\Outbox;
use ArchitectureLab\Eventing\OutboxDispatcher;
use ArchitectureLab\Eventing\ProcessedEventStore;
use ArchitectureLab\Instrumentation\Counters;
use ArchitectureLab\Notifications\NotificationLog;
use ArchitectureLab\Persistence\InMemoryDocumentStore;
use ArchitectureLab\Persistence\InMemoryOutbox;
use ArchitectureLab\Persistence\InMemoryProcessedEventStore;
use ArchitectureLab\Persistence\SqliteDocumentStore;
use ArchitectureLab\Persistence\SqliteOutbox;
use ArchitectureLab\Persistence\SqliteProcessedEventStore;
use ArchitectureLab\Reporting\Application\OfferingCapacityQueryHandler;
use ArchitectureLab\Reporting\Application\StudentScheduleQueryHandler;
use ArchitectureLab\Reporting\Projection\OfferingCapacityProjection;
use ArchitectureLab\Reporting\Projection\StudentScheduleProjection;
use ArchitectureLab\Reporting\PublicApi\Query\OfferingCapacityQuery;
use ArchitectureLab\Reporting\PublicApi\Query\StudentScheduleQuery;
use ArchitectureLab\Shared\FrozenClock;
use ArchitectureLab\Shared\OfferingId;
use ArchitectureLab\Shared\Port\DocumentStore;
use ArchitectureLab\Shared\SequentialIdGenerator;
use PDO;

/**
 * The composition root.
 *
 * This is the only class allowed to know both application ports and concrete
 * adapters. Delivery code receives the three buses and never constructs a
 * repository. The same object serves experiments, tests and the scenario.
 */
final class LabRuntime
{
    private readonly Counters $counters;
    private readonly DocumentStore $store;
    private readonly Outbox $outbox;
    private readonly EventBus $bus;
    private readonly OutboxDispatcher $dispatcher;
    private readonly CommandBus $commands;
    private readonly QueryBus $queries;
    private readonly CatalogApi $catalog;
    private readonly EnrollmentApi $enrollments;
    private readonly NotificationLog $notifications;
    private readonly FrozenClock $clock;

    private function __construct(
        private readonly string $mode,
        Counters $counters,
        DocumentStore $store,
        Outbox $outbox,
        ProcessedEventStore $processed,
        FrozenClock $clock,
        SequentialIdGenerator $ids,
        int $failEvery,
    ) {
        $this->counters = $counters;
        $this->store = $store;
        $this->outbox = $outbox;
        $this->clock = $clock;
        $this->notifications = new NotificationLog();
        $recorder = new EventRecorder($ids, $clock);

        $catalogRepository = new DocumentCourseOfferingRepository($store, $counters);
        $catalog = new CatalogService($catalogRepository, $store, $outbox, $recorder);
        $enrollmentRepository = new DocumentEnrollmentRepository($store, $counters);
        $invoiceRepository = new DocumentInvoiceRepository($store, $counters);
        $billing = new BillingService(
            $invoiceRepository,
            new DeterministicPaymentGateway($failEvery),
            $store,
            $outbox,
            $recorder,
            $ids,
            $clock,
        );
        $seats = new CatalogSeatAllocator($catalog);
        $payment = $mode === 'sync' ? new BillingPaymentProcessor($billing) : null;
        $requestEnrollment = new RequestEnrollmentHandler(
            $enrollmentRepository,
            $seats,
            $store,
            $outbox,
            $recorder,
            $ids,
            $clock,
            $payment,
        );

        $bus = new InMemoryEventBus($counters);
        $offeringProjection = new OfferingCapacityProjection($store, $counters);
        $scheduleProjection = new StudentScheduleProjection($store, $counters);
        $offeringListener = new IdempotentListener('reporting.offering', $offeringProjection, $processed, $counters);
        $scheduleListener = new IdempotentListener('reporting.schedule', $scheduleProjection, $processed, $counters);
        $notificationListener = new IdempotentListener('notifications.enrollment', $this->notifications, $processed, $counters);

        foreach ([
            EventNames::COURSE_OFFERING_PUBLISHED,
            EventNames::SEAT_RESERVED,
            EventNames::SEAT_RELEASED,
            EventNames::ENROLLMENT_REQUESTED,
            EventNames::ENROLLMENT_CONFIRMED,
            EventNames::ENROLLMENT_CANCELLED,
        ] as $eventName) {
            $bus->subscribe($eventName, $offeringListener);
        }

        foreach ([
            EventNames::ENROLLMENT_REQUESTED,
            EventNames::ENROLLMENT_CONFIRMED,
            EventNames::ENROLLMENT_CANCELLED,
        ] as $eventName) {
            $bus->subscribe($eventName, $scheduleListener);
        }

        foreach ([EventNames::ENROLLMENT_CONFIRMED, EventNames::ENROLLMENT_CANCELLED] as $eventName) {
            $bus->subscribe($eventName, $notificationListener);
        }

        if ($mode === 'event-driven') {
            $billingListener = new IdempotentListener(
                'billing.enrollment_requested',
                new EnrollmentRequestedListener($billing),
                $processed,
                $counters,
            );
            $bus->subscribe(EventNames::ENROLLMENT_REQUESTED, $billingListener);
            $paymentResultListener = new IdempotentListener(
                'enrollment.payment_result',
                new PaymentResultListener($enrollmentRepository, $seats, $store, $outbox, $recorder, $clock),
                $processed,
                $counters,
            );
            $bus->subscribe(EventNames::PAYMENT_SUCCEEDED, $paymentResultListener);
            $bus->subscribe(EventNames::PAYMENT_FAILED, $paymentResultListener);
        }

        $commands = new CommandBus();
        $commands->register(
            PublishCourse::class,
            fn (object $command): null => $this->handlePublishCourse($catalog, $command),
        );
        $commands->register(
            RequestEnrollment::class,
            fn (object $command): mixed => $command instanceof RequestEnrollment
                ? $requestEnrollment->handle($command)
                : throw new \LogicException('Unexpected command class'),
        );

        $queries = new QueryBus();
        $offeringCapacity = new OfferingCapacityQueryHandler($store);
        $studentSchedule = new StudentScheduleQueryHandler($store);
        $queries->register(
            OfferingCapacityQuery::class,
            static fn (object $query): mixed => $query instanceof OfferingCapacityQuery
                ? $offeringCapacity->handle($query)
                : throw new \LogicException('Unexpected query class'),
        );
        $queries->register(
            StudentScheduleQuery::class,
            static fn (object $query): mixed => $query instanceof StudentScheduleQuery
                ? $studentSchedule->handle($query)
                : throw new \LogicException('Unexpected query class'),
        );

        $this->bus = $bus;
        $this->dispatcher = new OutboxDispatcher($outbox, $bus);
        $this->commands = $commands;
        $this->queries = $queries;
        $this->catalog = $catalog;
        $this->enrollments = new EnrollmentQueryService($enrollmentRepository);
    }

    public static function inMemory(string $mode = 'event-driven', int $failEvery = 0): self
    {
        $counters = new Counters();
        $store = new InMemoryDocumentStore($counters);

        return new self(
            mode: $mode,
            counters: $counters,
            store: $store,
            outbox: new InMemoryOutbox(),
            processed: new InMemoryProcessedEventStore(),
            clock: new FrozenClock(),
            ids: new SequentialIdGenerator(),
            failEvery: $failEvery,
        );
    }

    public static function sqlite(string $path = ':memory:', string $mode = 'event-driven', int $failEvery = 0): self
    {
        $counters = new Counters();
        $pdo = new PDO('sqlite:' . $path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return new self(
            mode: $mode,
            counters: $counters,
            store: new SqliteDocumentStore($pdo, $counters),
            outbox: new SqliteOutbox($pdo, $counters),
            processed: new SqliteProcessedEventStore($pdo, $counters),
            clock: new FrozenClock(),
            ids: new SequentialIdGenerator(),
            failEvery: $failEvery,
        );
    }

    public function mode(): string
    {
        return $this->mode;
    }

    public function counters(): Counters
    {
        return $this->counters;
    }

    public function catalog(): CatalogApi
    {
        return $this->catalog;
    }

    public function enrollments(): EnrollmentApi
    {
        return $this->enrollments;
    }

    public function commands(): CommandBus
    {
        return $this->commands;
    }

    public function queries(): QueryBus
    {
        return $this->queries;
    }

    public function outbox(): Outbox
    {
        return $this->outbox;
    }

    public function eventBus(): EventBus
    {
        return $this->bus;
    }

    public function clock(): FrozenClock
    {
        return $this->clock;
    }

    public function notifications(): NotificationLog
    {
        return $this->notifications;
    }

    public function pendingEvents(): int
    {
        return $this->outbox->countPending();
    }

    public function deadLettered(): int
    {
        return $this->outbox->countDeadLettered();
    }

    public function dispatchOutbox(int $limit = 100): \ArchitectureLab\Eventing\DispatchReport
    {
        return $this->dispatcher->dispatch($limit);
    }

    /**
     * Runs delivery until quiescent or the safety bound is reached.
     */
    public function pump(int $limit = 100, int $maxRounds = 100): \ArchitectureLab\Eventing\DispatchReport
    {
        $total = new \ArchitectureLab\Eventing\DispatchReport();

        for ($round = 0; $round < $maxRounds; ++$round) {
            if ($this->outbox->countPending() === 0) {
                break;
            }

            $report = $this->dispatcher->dispatch($limit);
            $total->add($report);

            if ($report->delivered === 0 && $report->failed === 0) {
                break;
            }
        }

        return $total;
    }

    public function publishCourse(
        string $offeringId,
        string $title,
        int $priceCents,
        int $capacity,
        string $startsAt,
    ): void {
        $this->catalog->publish(new OfferingId($offeringId), $title, $priceCents, $capacity, $startsAt);
    }

    public function requestEnrollment(string $studentId, string $offeringId): mixed
    {
        return $this->commands->dispatch(new RequestEnrollment($studentId, $offeringId));
    }

    public function offeringCapacity(string $offeringId): \ArchitectureLab\Reporting\PublicApi\OfferingCapacityView
    {
        $result = $this->queries->ask(new OfferingCapacityQuery($offeringId));

        if (!$result instanceof \ArchitectureLab\Reporting\PublicApi\OfferingCapacityView) {
            throw new \LogicException('OfferingCapacityQuery returned an unexpected type');
        }

        return $result;
    }

    public function studentSchedule(string $studentId): \ArchitectureLab\Reporting\PublicApi\StudentScheduleView
    {
        $result = $this->queries->ask(new StudentScheduleQuery($studentId));

        if (!$result instanceof \ArchitectureLab\Reporting\PublicApi\StudentScheduleView) {
            throw new \LogicException('StudentScheduleQuery returned an unexpected type');
        }

        return $result;
    }

    public function store(): DocumentStore
    {
        return $this->store;
    }

    private function handlePublishCourse(CatalogApi $catalog, object $command): null
    {
        if (!$command instanceof PublishCourse) {
            throw new \LogicException('Unexpected command class');
        }

        $catalog->publish(
            new OfferingId($command->offeringId),
            $command->title,
            $command->priceCents,
            $command->capacity,
            $command->startsAt,
        );

        return null;
    }
}
