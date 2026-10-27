<?php

declare(strict_types=1);

namespace ArchitectureLab\Enrollment\Domain;

enum EnrollmentStatus: string
{
    case PendingPayment = 'pending_payment';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';

    public function isTerminal(): bool
    {
        return $this !== self::PendingPayment;
    }
}
