<?php

declare(strict_types=1);

namespace ArchitectureLab\Catalog\Infrastructure;

use ArchitectureLab\Catalog\Application\Port\CourseOfferingRepository;
use ArchitectureLab\Catalog\Domain\CourseOffering;
use ArchitectureLab\Instrumentation\Counters;
use ArchitectureLab\Shared\Money;
use ArchitectureLab\Shared\OfferingId;
use ArchitectureLab\Shared\Port\DocumentStore;
use DateTimeImmutable;

/**
 * Maps the catalog aggregate to the document-store port.
 *
 * Serialization lives in infrastructure. The domain aggregate keeps only its
 * invariants and does not know that JSON exists.
 */
final readonly class DocumentCourseOfferingRepository implements CourseOfferingRepository
{
    private const COLLECTION = 'catalog_course_offerings';

    public function __construct(
        private DocumentStore $store,
        private Counters $counters,
    ) {
    }

    public function save(CourseOffering $offering): void
    {
        $this->store->put(self::COLLECTION, $offering->id()->value, [
            'id' => $offering->id()->value,
            'title' => $offering->title(),
            'price_cents' => $offering->price()->cents,
            'currency' => $offering->price()->currency,
            'starts_at' => $offering->startsAt()->format(DATE_ATOM),
            'capacity' => $offering->capacity(),
            'reserved_seats' => $offering->reservedSeats(),
        ]);
        ++$this->counters->repositoryCalls;
    }

    public function get(OfferingId $id): ?CourseOffering
    {
        $document = $this->store->get(self::COLLECTION, $id->value);
        ++$this->counters->repositoryCalls;

        return $document === null ? null : $this->hydrate($document);
    }

    public function all(): array
    {
        $offerings = [];

        foreach ($this->store->all(self::COLLECTION) as $document) {
            $offerings[] = $this->hydrate($document);
        }

        ++$this->counters->repositoryCalls;

        return $offerings;
    }

    /**
     * @param array<string, mixed> $document
     */
    private function hydrate(array $document): CourseOffering
    {
        $offering = new CourseOffering(
            new OfferingId((string) $document['id']),
            (string) $document['title'],
            new Money((int) $document['price_cents'], (string) $document['currency']),
            new DateTimeImmutable((string) $document['starts_at']),
            (int) $document['capacity'],
        );

        for ($seat = 0; $seat < (int) $document['reserved_seats']; ++$seat) {
            $offering->reserveSeat();
        }

        return $offering;
    }
}
