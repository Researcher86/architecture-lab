<?php

declare(strict_types=1);

namespace ArchitectureLab\Reporting\Projection;

use ArchitectureLab\Eventing\EventEnvelope;
use ArchitectureLab\Eventing\EventListener;
use ArchitectureLab\Eventing\EventNames;
use ArchitectureLab\Instrumentation\Counters;
use ArchitectureLab\Shared\Port\DocumentStore;

/**
 * The capacity read model, rebuilt only from events.
 *
 * It keeps counters rather than absolute values: two students' events can be
 * delivered in different orders, but each reservation is still exactly one
 * increment. An absolute `seatsTaken` in the event would let an older message
 * overwrite a newer state.
 */
final class OfferingCapacityProjection implements EventListener
{
    public const COLLECTION = 'reporting_offering_capacity';

    public function __construct(
        private readonly DocumentStore $store,
        private readonly Counters $counters,
    ) {
    }

    public function handle(EventEnvelope $event): void
    {
        $offeringId = (string) ($event->payload['offering_id'] ?? '');

        if ($offeringId === '') {
            return;
        }

        $document = $this->store->get(self::COLLECTION, $offeringId) ?? [
            'offering_id' => $offeringId,
            'title' => '',
            'capacity' => 0,
            'reserved_seats' => 0,
            'pending' => 0,
            'confirmed' => 0,
            'cancelled' => 0,
        ];

        match ($event->name) {
            EventNames::COURSE_OFFERING_PUBLISHED => $this->published($document, $event),
            EventNames::SEAT_RESERVED => $document['reserved_seats'] = (int) $document['reserved_seats'] + 1,
            EventNames::SEAT_RELEASED => $document['reserved_seats'] = max(0, (int) $document['reserved_seats'] - 1),
            EventNames::ENROLLMENT_REQUESTED => $document['pending'] = (int) $document['pending'] + 1,
            EventNames::ENROLLMENT_CONFIRMED => $this->confirmed($document),
            EventNames::ENROLLMENT_CANCELLED => $this->cancelled($document),
            default => null,
        };

        if (!in_array($event->name, [
            EventNames::COURSE_OFFERING_PUBLISHED,
            EventNames::SEAT_RESERVED,
            EventNames::SEAT_RELEASED,
            EventNames::ENROLLMENT_REQUESTED,
            EventNames::ENROLLMENT_CONFIRMED,
            EventNames::ENROLLMENT_CANCELLED,
        ], true)) {
            return;
        }

        $this->store->put(self::COLLECTION, $offeringId, $document);
        ++$this->counters->projectionUpdates;
    }

    /**
     * @param array<string, mixed> $document
     */
    private function published(array &$document, EventEnvelope $event): void
    {
        $document['title'] = (string) $event->payload['title'];
        $document['capacity'] = (int) $event->payload['capacity'];
    }

    /**
     * @param array<string, mixed> $document
     */
    private function confirmed(array &$document): void
    {
        $document['pending'] = max(0, (int) $document['pending'] - 1);
        $document['confirmed'] = (int) $document['confirmed'] + 1;
    }

    /**
     * @param array<string, mixed> $document
     */
    private function cancelled(array &$document): void
    {
        $document['pending'] = max(0, (int) $document['pending'] - 1);
        $document['cancelled'] = (int) $document['cancelled'] + 1;
    }
}
