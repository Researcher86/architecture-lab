<?php

declare(strict_types=1);

namespace ArchitectureLab\Catalog\PublicApi\Command;

/** A delivery-layer command; it carries primitives, not domain objects. */
final readonly class PublishCourse
{
    public function __construct(
        public string $offeringId,
        public string $title,
        public int $priceCents,
        public int $capacity,
        public string $startsAt,
    ) {
    }
}
