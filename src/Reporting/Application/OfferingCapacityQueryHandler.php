<?php

declare(strict_types=1);

namespace ArchitectureLab\Reporting\Application;

use ArchitectureLab\Reporting\Projection\OfferingCapacityProjection;
use ArchitectureLab\Reporting\PublicApi\OfferingCapacityView;
use ArchitectureLab\Reporting\PublicApi\Query\OfferingCapacityQuery;
use ArchitectureLab\Shared\Port\DocumentStore;

/** Reads one document and returns a view, never an aggregate. */
final readonly class OfferingCapacityQueryHandler
{
    public function __construct(
        private DocumentStore $store,
    ) {
    }

    public function handle(OfferingCapacityQuery $query): OfferingCapacityView
    {
        $document = $this->store->get(OfferingCapacityProjection::COLLECTION, $query->offeringId) ?? [];

        return new OfferingCapacityView(
            offeringId: $query->offeringId,
            title: (string) ($document['title'] ?? ''),
            capacity: (int) ($document['capacity'] ?? 0),
            reservedSeats: (int) ($document['reserved_seats'] ?? 0),
            pending: (int) ($document['pending'] ?? 0),
            confirmed: (int) ($document['confirmed'] ?? 0),
            cancelled: (int) ($document['cancelled'] ?? 0),
        );
    }
}
