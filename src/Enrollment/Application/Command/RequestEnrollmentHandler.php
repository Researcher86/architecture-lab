<?php

declare(strict_types=1);

namespace ArchitectureLab\Enrollment\Application\Command;

use ArchitectureLab\Enrollment\Application\Port\EnrollmentRepository;
use ArchitectureLab\Enrollment\Application\Port\NoSeatsAvailable;
use ArchitectureLab\Enrollment\Application\Port\PaymentOutcome;
use ArchitectureLab\Enrollment\Application\Port\PaymentProcessor;
use ArchitectureLab\Enrollment\Application\Port\SeatAllocator;
use ArchitectureLab\Enrollment\Domain\Enrollment;
use ArchitectureLab\Enrollment\Domain\EnrollmentStatus;
use ArchitectureLab\Enrollment\PublicApi\Command\RequestEnrollment;
use ArchitectureLab\Enrollment\PublicApi\EnrollmentReceipt;
use ArchitectureLab\Enrollment\PublicApi\Exception\EnrollmentRejected;
use ArchitectureLab\Eventing\EventNames;
use ArchitectureLab\Eventing\EventRecorder;
use ArchitectureLab\Eventing\Outbox;
use ArchitectureLab\Shared\EnrollmentId;
use ArchitectureLab\Shared\OfferingId;
use ArchitectureLab\Shared\Port\Clock;
use ArchitectureLab\Shared\Port\IdGenerator;
use ArchitectureLab\Shared\Port\UnitOfWork;
use ArchitectureLab\Shared\StudentId;

/**
 * The write side of enrollment.
 *
 * In sync mode a Billing adapter is present and the result is terminal before
 * the command returns. In event-driven mode the processor is null: the command
 * stops after `enrollment.requested` is in the outbox, and the rest of the
 * chain is driven by the dispatcher.
 */
final readonly class RequestEnrollmentHandler
{
    public function __construct(
        private EnrollmentRepository $enrollments,
        private SeatAllocator $seats,
        private UnitOfWork $unitOfWork,
        private Outbox $outbox,
        private EventRecorder $recorder,
        private IdGenerator $ids,
        private Clock $clock,
        private ?PaymentProcessor $payment = null,
    ) {
    }

    public function handle(RequestEnrollment $command): EnrollmentReceipt
    {
        try {
            $enrollment = $this->unitOfWork->transactional(function () use ($command): Enrollment {
                $reservation = $this->seats->reserve(new OfferingId($command->offeringId));
                $enrollment = new Enrollment(
                    id: new EnrollmentId($this->ids->next('enr')),
                    studentId: new StudentId($command->studentId),
                    offeringId: $reservation->offeringId,
                    courseTitle: $reservation->courseTitle,
                    price: $reservation->price,
                    requestedAt: $this->clock->now(),
                );
                $this->enrollments->save($enrollment);
                $this->outbox->record($this->recorder->record(
                    EventNames::ENROLLMENT_REQUESTED,
                    $enrollment->id()->value,
                    [
                        'enrollment_id' => $enrollment->id()->value,
                        'student_id' => $enrollment->studentId()->value,
                        'offering_id' => $enrollment->offeringId()->value,
                        'course_title' => $enrollment->courseTitle(),
                        'price_cents' => $enrollment->price()->cents,
                        'currency' => $enrollment->price()->currency,
                        'requested_at' => $enrollment->requestedAt()->format(DATE_ATOM),
                    ],
                ));

                return $enrollment;
            });
        } catch (NoSeatsAvailable $exception) {
            throw new EnrollmentRejected($exception->getMessage(), 0, $exception);
        }

        if ($this->payment !== null) {
            $outcome = $this->payment->charge(
                $enrollment->id(),
                $enrollment->studentId(),
                $enrollment->offeringId(),
                $enrollment->price(),
            );
            $this->unitOfWork->transactional(fn () => $this->applyPayment($enrollment->id(), $outcome));

            return new EnrollmentReceipt(
                enrollmentId: $enrollment->id()->value,
                status: $outcome->paid ? EnrollmentStatus::Confirmed->value : EnrollmentStatus::Cancelled->value,
                paid: $outcome->paid,
                invoiceId: $outcome->invoiceId,
            );
        }

        return new EnrollmentReceipt(
            enrollmentId: $enrollment->id()->value,
            status: EnrollmentStatus::PendingPayment->value,
            paid: false,
        );
    }

    private function applyPayment(EnrollmentId $id, PaymentOutcome $outcome): void
    {
        $enrollment = $this->enrollments->get($id);

        if ($enrollment === null || $enrollment->status() !== EnrollmentStatus::PendingPayment) {
            return;
        }

        if ($outcome->paid) {
            $enrollment->confirm($this->clock->now());
            $this->enrollments->save($enrollment);
            $this->recordDecision($enrollment, EventNames::ENROLLMENT_CONFIRMED, null);

            return;
        }

        $reason = $outcome->reason ?? 'payment_failed';
        $enrollment->cancel($reason, $this->clock->now());
        $this->enrollments->save($enrollment);
        $this->seats->release($enrollment->offeringId());
        $this->recordDecision($enrollment, EventNames::ENROLLMENT_CANCELLED, $reason);
    }

    private function recordDecision(Enrollment $enrollment, string $eventName, ?string $reason): void
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
