<?php

declare(strict_types=1);

namespace ArchitectureLab\Billing\Domain;

enum InvoiceStatus: string
{
    case Open = 'open';
    case Paid = 'paid';
    case Failed = 'failed';
}
