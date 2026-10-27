<?php

declare(strict_types=1);

namespace ArchitectureLab\Enrollment\Application\Port;

use ArchitectureLab\Shared\Money;
use ArchitectureLab\Shared\OfferingId;

/** The catalog's answer as Enrollment needs it, stripped of Catalog types. */
final readonly class SeatReservation
{
    public function __construct(
        public OfferingId $offeringId,
        public string $courseTitle,
        public Money $price,
        public int $seatsLeft,
    ) {
    }
}
