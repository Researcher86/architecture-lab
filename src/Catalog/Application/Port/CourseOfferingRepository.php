<?php

declare(strict_types=1);

namespace ArchitectureLab\Catalog\Application\Port;

use ArchitectureLab\Catalog\Domain\CourseOffering;
use ArchitectureLab\Shared\OfferingId;

/** The repository port belongs to the application, the adapter to the outside. */
interface CourseOfferingRepository
{
    public function save(CourseOffering $offering): void;

    public function get(OfferingId $id): ?CourseOffering;

    /**
     * @return list<CourseOffering>
     */
    public function all(): array;
}
