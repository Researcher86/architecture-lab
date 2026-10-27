<?php

declare(strict_types=1);

namespace ArchitectureLab\Billing\Application\Port;

/** The gateway's answer in Billing's language. */
final readonly class PaymentResult
{
    public function __construct(
        public bool $paid,
        public ?string $reason = null,
    ) {
    }
}
