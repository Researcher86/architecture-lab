<?php

declare(strict_types=1);

namespace ArchitectureLab\Enrollment\PublicApi;

use ArchitectureLab\Enrollment\Domain\Enrollment;
use ArchitectureLab\Enrollment\Domain\EnrollmentStatus;

/** A stable read shape for diagnostics and delivery layers. */
final readonly class EnrollmentView
{
    public function __construct(
        public string $id,
        public string $studentId,
        public string $offeringId,
        public string $courseTitle,
        public int $priceCents,
        public string $currency,
        public EnrollmentStatus $status,
        public ?string $cancellationReason,
        public string $requestedAt,
        public ?string $decidedAt,
    ) {
    }

    public static function fromDomain(Enrollment $enrollment): self
    {
        return new self(
            id: $enrollment->id()->value,
            studentId: $enrollment->studentId()->value,
            offeringId: $enrollment->offeringId()->value,
            courseTitle: $enrollment->courseTitle(),
            priceCents: $enrollment->price()->cents,
            currency: $enrollment->price()->currency,
            status: $enrollment->status(),
            cancellationReason: $enrollment->cancellationReason(),
            requestedAt: $enrollment->requestedAt()->format(DATE_ATOM),
            decidedAt: $enrollment->decidedAt()?->format(DATE_ATOM),
        );
    }

    /**
     * @return array<string, int|string|null>
     */
    public function toArray(): array
    {
        return [
            'enrollment_id' => $this->id,
            'student_id' => $this->studentId,
            'offering_id' => $this->offeringId,
            'course_title' => $this->courseTitle,
            'price_cents' => $this->priceCents,
            'currency' => $this->currency,
            'status' => $this->status->value,
            'cancellation_reason' => $this->cancellationReason,
            'requested_at' => $this->requestedAt,
            'decided_at' => $this->decidedAt,
        ];
    }
}
