<?php

declare(strict_types=1);

namespace ArchitectureLab\Reporting\PublicApi\Query;

/** A CQRS query: it names the read side only. */
final readonly class OfferingCapacityQuery
{
    public function __construct(public string $offeringId)
    {
    }
}
