<?php

declare(strict_types=1);

namespace ArchitectureLab\Billing\PublicApi;

use ArchitectureLab\Shared\EnrollmentId;
use ArchitectureLab\Shared\Money;
use ArchitectureLab\Shared\OfferingId;
use ArchitectureLab\Shared\StudentId;

interface BillingApi
{
    /**
     * Idempotent by enrollment id: a retry returns the existing decision
     * instead of charging the student twice.
     */
    public function chargeForEnrollment(
        EnrollmentId $enrollmentId,
        StudentId $studentId,
        OfferingId $offeringId,
        Money $amount,
    ): PaymentOutcome;
}
