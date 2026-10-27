<?php

declare(strict_types=1);

namespace ArchitectureLab\Billing\Application\Port;

use ArchitectureLab\Billing\Domain\Invoice;

/** The external payment provider is behind this port. */
interface PaymentGateway
{
    public function charge(Invoice $invoice): PaymentResult;
}
