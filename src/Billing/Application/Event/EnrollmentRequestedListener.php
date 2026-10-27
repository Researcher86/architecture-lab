<?php

declare(strict_types=1);

namespace ArchitectureLab\Billing\Application\Event;

use ArchitectureLab\Billing\PublicApi\BillingApi;
use ArchitectureLab\Eventing\EventEnvelope;
use ArchitectureLab\Eventing\EventListener;
use ArchitectureLab\Eventing\EventNames;
use ArchitectureLab\Shared\EnrollmentId;
use ArchitectureLab\Shared\Money;
use ArchitectureLab\Shared\OfferingId;
use ArchitectureLab\Shared\StudentId;

/**
 * Billing's anti-corruption layer for the enrollment event.
 *
 * The payload is read as a published contract and translated into Billing's
 * local values. No Enrollment class is imported here.
 */
final readonly class EnrollmentRequestedListener implements EventListener
{
    public function __construct(
        private BillingApi $billing,
    ) {
    }

    public function handle(EventEnvelope $event): void
    {
        if ($event->name !== EventNames::ENROLLMENT_REQUESTED) {
            return;
        }

        $this->billing->chargeForEnrollment(
            new EnrollmentId((string) $event->payload['enrollment_id']),
            new StudentId((string) $event->payload['student_id']),
            new OfferingId((string) $event->payload['offering_id']),
            new Money(
                (int) $event->payload['price_cents'],
                (string) $event->payload['currency'],
            ),
        );
    }
}
