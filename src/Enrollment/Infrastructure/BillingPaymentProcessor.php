<?php

declare(strict_types=1);

namespace ArchitectureLab\Enrollment\Infrastructure;

use ArchitectureLab\Billing\PublicApi\BillingApi;
use ArchitectureLab\Enrollment\Application\Port\PaymentOutcome;
use ArchitectureLab\Enrollment\Application\Port\PaymentProcessor;
use ArchitectureLab\Shared\EnrollmentId;
use ArchitectureLab\Shared\Money;
use ArchitectureLab\Shared\OfferingId;
use ArchitectureLab\Shared\StudentId;

/**
 * Translates Billing's public result into the port Enrollment owns.
 *
 * Billing never appears in Enrollment's application layer; this adapter is the
 * seam that keeps the two contexts independent.
 */
final readonly class BillingPaymentProcessor implements PaymentProcessor
{
    public function __construct(
        private BillingApi $billing,
    ) {
    }

    public function charge(
        EnrollmentId $enrollmentId,
        StudentId $studentId,
        OfferingId $offeringId,
        Money $amount,
    ): PaymentOutcome {
        $result = $this->billing->chargeForEnrollment($enrollmentId, $studentId, $offeringId, $amount);

        return new PaymentOutcome(
            paid: $result->paid,
            invoiceId: $result->invoiceId,
            reason: $result->failureReason,
        );
    }
}
