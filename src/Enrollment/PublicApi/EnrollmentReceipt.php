<?php

declare(strict_types=1);

namespace ArchitectureLab\Enrollment\PublicApi;

/** The synchronous result of a request; async mode returns pending. */
final readonly class EnrollmentReceipt
{
    public function __construct(
        public string $enrollmentId,
        public string $status,
        public bool $paid,
        public ?string $invoiceId = null,
    ) {
    }
}
