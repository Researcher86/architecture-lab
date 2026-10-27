<?php

declare(strict_types=1);

namespace ArchitectureLab\Billing\PublicApi;

/** Billing's public result; Enrollment translates it through an adapter. */
final readonly class PaymentOutcome
{
    public function __construct(
        public bool $paid,
        public string $invoiceId,
        public ?string $failureReason = null,
    ) {
    }
}
