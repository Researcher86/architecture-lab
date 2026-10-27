<?php

declare(strict_types=1);

namespace ArchitectureLab\Tests\Enrollment;

use ArchitectureLab\Enrollment\Application\Command\RequestEnrollmentHandler;
use ArchitectureLab\Enrollment\Application\Port\EnrollmentRepository;
use ArchitectureLab\Enrollment\Application\Port\NoSeatsAvailable as EnrollmentNoSeatsAvailable;
use ArchitectureLab\Enrollment\Application\Port\PaymentOutcome;
use ArchitectureLab\Enrollment\Application\Port\PaymentProcessor;
use ArchitectureLab\Enrollment\Application\Port\SeatAllocator;
use ArchitectureLab\Enrollment\Application\Port\SeatReservation;
use ArchitectureLab\Enrollment\Infrastructure\DocumentEnrollmentRepository;
use ArchitectureLab\Enrollment\PublicApi\Command\RequestEnrollment;
use ArchitectureLab\Enrollment\PublicApi\Exception\EnrollmentRejected;
use ArchitectureLab\Eventing\EventNames;
use ArchitectureLab\Eventing\EventRecorder;
use ArchitectureLab\Instrumentation\Counters;
use ArchitectureLab\Persistence\InMemoryDocumentStore;
use ArchitectureLab\Persistence\InMemoryOutbox;
use ArchitectureLab\Shared\EnrollmentId;
use ArchitectureLab\Shared\FrozenClock;
use ArchitectureLab\Shared\Money;
use ArchitectureLab\Shared\OfferingId;
use ArchitectureLab\Shared\SequentialIdGenerator;
use ArchitectureLab\Shared\StudentId;
use PHPUnit\Framework\TestCase;

final class RequestEnrollmentHandlerTest extends TestCase
{
    public function testAsyncModeStopsAtPendingPayment(): void
    {
        [$handler, $repository, $outbox] = $this->handler();

        $receipt = $handler->handle(new RequestEnrollment('s-1', 'arch-101'));

        self::assertSame('pending_payment', $receipt->status);
        self::assertFalse($receipt->paid);
        self::assertNull($receipt->invoiceId);
        self::assertSame('pending_payment', $repository->get(new EnrollmentId('enr-1'))?->status()->value);

        $events = array_map(static fn ($message): string => $message->name, $outbox->pending(10));
        self::assertSame([EventNames::ENROLLMENT_REQUESTED], $events);
    }

    public function testSyncSuccessConfirmsTheEnrollment(): void
    {
        $payment = new FakePaymentProcessor(new PaymentOutcome(true, 'inv-1'));
        [$handler, $repository, $outbox] = $this->handler($payment);

        $receipt = $handler->handle(new RequestEnrollment('s-1', 'arch-101'));

        self::assertSame('confirmed', $receipt->status);
        self::assertTrue($receipt->paid);
        self::assertSame('inv-1', $receipt->invoiceId);
        self::assertSame(1, $payment->calls);
        self::assertSame('confirmed', $repository->get(new EnrollmentId('enr-1'))?->status()->value);
        self::assertSame([
            EventNames::ENROLLMENT_REQUESTED,
            EventNames::ENROLLMENT_CONFIRMED,
        ], array_map(static fn ($message): string => $message->name, $outbox->pending(10)));
    }

    public function testSyncFailureCancelsAndReleasesTheSeat(): void
    {
        $payment = new FakePaymentProcessor(new PaymentOutcome(false, 'inv-1', 'card_declined'));
        [$handler, $repository, $outbox, $seats] = $this->handler($payment);

        $receipt = $handler->handle(new RequestEnrollment('s-1', 'arch-101'));

        self::assertSame('cancelled', $receipt->status);
        self::assertFalse($receipt->paid);
        self::assertSame('card_declined', $receipt->invoiceId === null ? null : $repository->get(new EnrollmentId('enr-1'))?->cancellationReason());
        self::assertSame(['arch-101'], $seats->released);
        self::assertSame([
            EventNames::ENROLLMENT_REQUESTED,
            EventNames::ENROLLMENT_CANCELLED,
        ], array_map(static fn ($message): string => $message->name, $outbox->pending(10)));
    }

    public function testFullCourseBecomesAPublicRejectionWithoutStoredState(): void
    {
        [$handler, $repository, $outbox, $seats] = $this->handler(
            null,
            new FakeSeatAllocator(throwOnReserve: true),
        );

        try {
            $handler->handle(new RequestEnrollment('s-1', 'arch-101'));
            self::fail('The full course should be rejected');
        } catch (EnrollmentRejected) {
            self::assertSame([], $repository->all());
            self::assertSame([], $outbox->pending(10));
            self::assertSame([], $seats->released);
        }
    }

    public function testTheSeatAllocatorReceivesTheOfferingId(): void
    {
        [$handler, , , $seats] = $this->handler();

        $handler->handle(new RequestEnrollment('s-1', 'arch-101'));

        self::assertSame(['arch-101'], $seats->reserved);
    }

    /**
     * @return array{RequestEnrollmentHandler, EnrollmentRepository, InMemoryOutbox, FakeSeatAllocator}
     */
    private function handler(
        ?PaymentProcessor $payment = null,
        ?FakeSeatAllocator $allocator = null,
    ): array {
        $store = new InMemoryDocumentStore(new Counters());
        $outbox = new InMemoryOutbox();
        $clock = new FrozenClock();
        $ids = new SequentialIdGenerator();
        $repository = new DocumentEnrollmentRepository($store, new Counters());
        $seats = $allocator ?? new FakeSeatAllocator();

        $handler = new RequestEnrollmentHandler(
            enrollments: $repository,
            seats: $seats,
            unitOfWork: $store,
            outbox: $outbox,
            recorder: new EventRecorder($ids, $clock),
            ids: $ids,
            clock: $clock,
            payment: $payment,
        );

        return [$handler, $repository, $outbox, $seats];
    }
}

final class FakeSeatAllocator implements SeatAllocator
{
    /** @var list<string> */
    public array $reserved = [];

    /** @var list<string> */
    public array $released = [];

    public function __construct(
        private readonly bool $throwOnReserve = false,
    ) {
    }

    public function reserve(OfferingId $offeringId): SeatReservation
    {
        if ($this->throwOnReserve) {
            throw new EnrollmentNoSeatsAvailable('Course is full');
        }

        $this->reserved[] = $offeringId->value;

        return new SeatReservation($offeringId, 'Architecture', new Money(12000), 3);
    }

    public function release(OfferingId $offeringId): void
    {
        $this->released[] = $offeringId->value;
    }
}

final class FakePaymentProcessor implements PaymentProcessor
{
    public int $calls = 0;

    public function __construct(
        private readonly PaymentOutcome $outcome,
    ) {
    }

    public function charge(
        EnrollmentId $enrollmentId,
        StudentId $studentId,
        OfferingId $offeringId,
        Money $amount,
    ): PaymentOutcome {
        ++$this->calls;

        return $this->outcome;
    }
}
