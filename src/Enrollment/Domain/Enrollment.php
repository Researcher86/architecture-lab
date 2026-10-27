<?php

declare(strict_types=1);

namespace ArchitectureLab\Enrollment\Domain;

use ArchitectureLab\Shared\EnrollmentId;
use ArchitectureLab\Shared\Money;
use ArchitectureLab\Shared\OfferingId;
use ArchitectureLab\Shared\StudentId;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;

/**
 * Enrollment's aggregate: one student's pending or finished course attempt.
 *
 * It owns the state transition, not the payment and not the seat. The seat is
 * reserved in Catalog through a port; the payment result arrives as an event
 * or from a synchronous adapter.
 */
final class Enrollment
{
    private EnrollmentStatus $status = EnrollmentStatus::PendingPayment;

    private ?string $cancellationReason = null;

    private ?DateTimeImmutable $decidedAt = null;

    public function __construct(
        private readonly EnrollmentId $id,
        private readonly StudentId $studentId,
        private readonly OfferingId $offeringId,
        private readonly string $courseTitle,
        private readonly Money $price,
        private readonly DateTimeImmutable $requestedAt,
    ) {
        if (trim($this->courseTitle) === '') {
            throw new InvalidArgumentException('Course title cannot be empty');
        }
    }

    public function confirm(DateTimeImmutable $at): void
    {
        if ($this->status !== EnrollmentStatus::PendingPayment) {
            throw new DomainException(sprintf('Cannot confirm enrollment in status %s', $this->status->value));
        }

        $this->status = EnrollmentStatus::Confirmed;
        $this->decidedAt = $at;
    }

    public function cancel(string $reason, DateTimeImmutable $at): void
    {
        if ($this->status !== EnrollmentStatus::PendingPayment) {
            throw new DomainException(sprintf('Cannot cancel enrollment in status %s', $this->status->value));
        }

        $this->status = EnrollmentStatus::Cancelled;
        $this->cancellationReason = $reason;
        $this->decidedAt = $at;
    }

    public function id(): EnrollmentId
    {
        return $this->id;
    }

    public function studentId(): StudentId
    {
        return $this->studentId;
    }

    public function offeringId(): OfferingId
    {
        return $this->offeringId;
    }

    public function courseTitle(): string
    {
        return $this->courseTitle;
    }

    public function price(): Money
    {
        return $this->price;
    }

    public function requestedAt(): DateTimeImmutable
    {
        return $this->requestedAt;
    }

    public function status(): EnrollmentStatus
    {
        return $this->status;
    }

    public function cancellationReason(): ?string
    {
        return $this->cancellationReason;
    }

    public function decidedAt(): ?DateTimeImmutable
    {
        return $this->decidedAt;
    }
}
