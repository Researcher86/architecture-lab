<?php

declare(strict_types=1);

namespace ArchitectureLab\Billing\Infrastructure;

use ArchitectureLab\Billing\Application\Port\PaymentGateway;
use ArchitectureLab\Billing\Application\Port\PaymentResult;
use ArchitectureLab\Billing\Domain\Invoice;

/**
 * A deterministic payment provider for reproducible experiments.
 *
 * `failEvery = 0` always succeeds. Otherwise every Nth charge is declined,
 * with a counter that belongs to this gateway instance. No randomness and no
 * network: the scenario can name the exact enrollment that fails.
 */
final class DeterministicPaymentGateway implements PaymentGateway
{
    private int $charges = 0;

    public function __construct(
        private readonly int $failEvery = 0,
    ) {
        if ($this->failEvery < 0) {
            throw new \InvalidArgumentException('failEvery cannot be negative');
        }
    }

    public function charge(Invoice $invoice): PaymentResult
    {
        ++$this->charges;

        if ($this->failEvery > 0 && $this->charges % $this->failEvery === 0) {
            return new PaymentResult(paid: false, reason: 'card_declined');
        }

        return new PaymentResult(paid: true);
    }
}
