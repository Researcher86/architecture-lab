<?php

declare(strict_types=1);

namespace ArchitectureLab\Billing\Domain;

use ArchitectureLab\Shared\EnrollmentId;
use ArchitectureLab\Shared\InvoiceId;
use ArchitectureLab\Shared\Money;
use ArchitectureLab\Shared\OfferingId;
use ArchitectureLab\Shared\StudentId;
use DateTimeImmutable;
use DomainException;

/**
 * Billing's aggregate.
 *
 * It knows an enrollment only by id. The invoice does not embed an Enrollment
 * object, so a change in Enrollment's wording cannot silently change Billing.
 */
final class Invoice
{
    private InvoiceStatus $status = InvoiceStatus::Open;

    private ?string $failureReason = null;

    private ?DateTimeImmutable $decidedAt = null;

    public function __construct(
        private readonly InvoiceId $id,
        private readonly EnrollmentId $enrollmentId,
        private readonly StudentId $studentId,
        private readonly OfferingId $offeringId,
        private readonly Money $amount,
        private readonly DateTimeImmutable $issuedAt,
    ) {
    }

    public function markPaid(DateTimeImmutable $at): void
    {
        if ($this->status !== InvoiceStatus::Open) {
            throw new DomainException(sprintf('Cannot pay invoice in status %s', $this->status->value));
        }

        $this->status = InvoiceStatus::Paid;
        $this->decidedAt = $at;
    }

    public function markFailed(string $reason, DateTimeImmutable $at): void
    {
        if ($this->status !== InvoiceStatus::Open) {
            throw new DomainException(sprintf('Cannot fail invoice in status %s', $this->status->value));
        }

        $this->status = InvoiceStatus::Failed;
        $this->failureReason = $reason;
        $this->decidedAt = $at;
    }

    public function id(): InvoiceId
    {
        return $this->id;
    }

    public function enrollmentId(): EnrollmentId
    {
        return $this->enrollmentId;
    }

    public function studentId(): StudentId
    {
        return $this->studentId;
    }

    public function offeringId(): OfferingId
    {
        return $this->offeringId;
    }

    public function amount(): Money
    {
        return $this->amount;
    }

    public function issuedAt(): DateTimeImmutable
    {
        return $this->issuedAt;
    }

    public function status(): InvoiceStatus
    {
        return $this->status;
    }

    public function failureReason(): ?string
    {
        return $this->failureReason;
    }

    public function decidedAt(): ?DateTimeImmutable
    {
        return $this->decidedAt;
    }
}
