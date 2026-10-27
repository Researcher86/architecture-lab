<?php

declare(strict_types=1);

namespace ArchitectureLab\Enrollment\Application\Port;

use ArchitectureLab\Shared\EnrollmentId;
use ArchitectureLab\Shared\Money;
use ArchitectureLab\Shared\OfferingId;
use ArchitectureLab\Shared\StudentId;

/** The synchronous port used by the modular-monolith workflow. */
interface PaymentProcessor
{
    public function charge(
        EnrollmentId $enrollmentId,
        StudentId $studentId,
        OfferingId $offeringId,
        Money $amount,
    ): PaymentOutcome;
}
