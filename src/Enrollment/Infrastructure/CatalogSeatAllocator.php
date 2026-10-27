<?php

declare(strict_types=1);

namespace ArchitectureLab\Enrollment\Infrastructure;

use ArchitectureLab\Catalog\PublicApi\CatalogApi;
use ArchitectureLab\Catalog\PublicApi\Exception\NoSeatsAvailable;
use ArchitectureLab\Catalog\PublicApi\Exception\OfferingNotFound;
use ArchitectureLab\Enrollment\Application\Port\NoSeatsAvailable as EnrollmentNoSeatsAvailable;
use ArchitectureLab\Enrollment\Application\Port\SeatAllocator;
use ArchitectureLab\Enrollment\Application\Port\SeatReservation;
use ArchitectureLab\Shared\OfferingId;

/**
 * Anti-corruption layer: Catalog's language becomes Enrollment's language.
 *
 * This adapter is the only Enrollment class allowed to import CatalogApi. The
 * application port contains no Catalog exception and no Catalog DTO.
 */
final readonly class CatalogSeatAllocator implements SeatAllocator
{
    public function __construct(
        private CatalogApi $catalog,
    ) {
    }

    public function reserve(OfferingId $offeringId): SeatReservation
    {
        try {
            $offering = $this->catalog->offering($offeringId);
            $this->catalog->reserveSeat($offeringId);
        } catch (NoSeatsAvailable $exception) {
            throw new EnrollmentNoSeatsAvailable($exception->getMessage(), 0, $exception);
        } catch (OfferingNotFound $exception) {
            throw new EnrollmentNoSeatsAvailable($exception->getMessage(), 0, $exception);
        }

        return new SeatReservation(
            offeringId: $offering->id,
            courseTitle: $offering->title,
            price: $offering->price,
            seatsLeft: $offering->seatsLeft() - 1,
        );
    }

    public function release(OfferingId $offeringId): void
    {
        $this->catalog->releaseSeat($offeringId);
    }
}
