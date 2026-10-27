<?php

declare(strict_types=1);

namespace ArchitectureLab\Enrollment\Application\Port;

/** A payment result translated into Enrollment's vocabulary. */
final readonly class PaymentOutcome
{
    public function __construct(
        public bool $paid,
        public string $invoiceId,
        public ?string $reason = null,
    ) {
    }
}
