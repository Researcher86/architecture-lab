<?php

declare(strict_types=1);

namespace ArchitectureLab\Enrollment\Application\Event;

use ArchitectureLab\Enrollment\Application\Port\EnrollmentRepository;
use ArchitectureLab\Enrollment\Application\Port\SeatAllocator;
use ArchitectureLab\Enrollment\Domain\Enrollment;
use ArchitectureLab\Enrollment\Domain\EnrollmentStatus;
use ArchitectureLab\Eventing\EventEnvelope;
use ArchitectureLab\Eventing\EventListener;
use ArchitectureLab\Eventing\EventNames;
use ArchitectureLab\Eventing\EventRecorder;
use ArchitectureLab\Eventing\Outbox;
use ArchitectureLab\Shared\EnrollmentId;
use ArchitectureLab\Shared\Port\Clock;
use ArchitectureLab\Shared\Port\UnitOfWork;

/**
 * Reacts to Billing's published outcome in event-driven mode.
 *
 * This is a subscriber, not a synchronous call: Billing has no reference to an
 * enrollment repository, and Enrollment has no reference to Billing's classes.
 */
final readonly class PaymentResultListener implements EventListener
{
    public function __construct(
        private EnrollmentRepository $enrollments,
        private SeatAllocator $seats,
        private UnitOfWork $unitOfWork,
        private Outbox $outbox,
        private EventRecorder $recorder,
        private Clock $clock,
    ) {
    }

    public function handle(EventEnvelope $event): void
    {
        if ($event->name !== EventNames::PAYMENT_SUCCEEDED && $event->name !== EventNames::PAYMENT_FAILED) {
            return;
        }

        $this->unitOfWork->transactional(function () use ($event): void {
            $enrollment = $this->enrollments->get(new EnrollmentId((string) $event->payload['enrollment_id']));

            if ($enrollment === null || $enrollment->status() !== EnrollmentStatus::PendingPayment) {
                return;
            }

            if ($event->name === EventNames::PAYMENT_SUCCEEDED) {
                $enrollment->confirm($this->clock->now());
                $this->enrollments->save($enrollment);
                $this->record($enrollment, EventNames::ENROLLMENT_CONFIRMED, null);

                return;
            }

            $reason = (string) ($event->payload['reason'] ?? 'payment_failed');
            $enrollment->cancel($reason, $this->clock->now());
            $this->enrollments->save($enrollment);
            $this->seats->release($enrollment->offeringId());
            $this->record($enrollment, EventNames::ENROLLMENT_CANCELLED, $reason);
        });
    }

    private function record(Enrollment $enrollment, string $eventName, ?string $reason): void
    {
        $this->outbox->record($this->recorder->record(
            $eventName,
            $enrollment->id()->value,
            [
                'enrollment_id' => $enrollment->id()->value,
                'student_id' => $enrollment->studentId()->value,
                'offering_id' => $enrollment->offeringId()->value,
                'course_title' => $enrollment->courseTitle(),
                'price_cents' => $enrollment->price()->cents,
                'currency' => $enrollment->price()->currency,
                'reason' => $reason,
            ],
        ));
    }
}
