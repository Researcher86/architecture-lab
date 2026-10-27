<?php

declare(strict_types=1);

namespace ArchitectureLab\Catalog\PublicApi;

use ArchitectureLab\Shared\OfferingId;

/**
 * The only catalog surface another context may depend on.
 *
 * Enrollment's anti-corruption adapter calls this interface; it never imports
 * CourseOffering or a catalog repository.
 */
interface CatalogApi
{
    public function publish(OfferingId $id, string $title, int $priceCents, int $capacity, string $startsAt): void;

    public function reserveSeat(OfferingId $id): void;

    public function releaseSeat(OfferingId $id): void;

    public function offering(OfferingId $id): OfferingView;

    /**
     * @return list<OfferingView>
     */
    public function offerings(): array;
}
