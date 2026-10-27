<?php

declare(strict_types=1);

namespace ArchitectureLab\Enrollment\Application\Port;

use ArchitectureLab\Shared\OfferingId;

/**
 * Enrollment's own notion of reserving a seat.
 *
 * The catalog adapter implements this port. Enrollment does not know that
 * Catalog exists, which is what lets the contexts be split later.
 */
interface SeatAllocator
{
    /**
     * @throws NoSeatsAvailable
     */
    public function reserve(OfferingId $offeringId): SeatReservation;

    public function release(OfferingId $offeringId): void;
}
