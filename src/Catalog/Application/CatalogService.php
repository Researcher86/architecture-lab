<?php

declare(strict_types=1);

namespace ArchitectureLab\Catalog\Application;

use ArchitectureLab\Catalog\Application\Port\CourseOfferingRepository;
use ArchitectureLab\Catalog\Domain\CourseOffering;
use ArchitectureLab\Catalog\Domain\SeatsExhausted;
use ArchitectureLab\Catalog\PublicApi\CatalogApi;
use ArchitectureLab\Catalog\PublicApi\Exception\NoSeatsAvailable;
use ArchitectureLab\Catalog\PublicApi\Exception\OfferingNotFound;
use ArchitectureLab\Catalog\PublicApi\OfferingView;
use ArchitectureLab\Eventing\EventNames;
use ArchitectureLab\Eventing\EventRecorder;
use ArchitectureLab\Eventing\Outbox;
use ArchitectureLab\Shared\Money;
use ArchitectureLab\Shared\OfferingId;
use ArchitectureLab\Shared\Port\UnitOfWork;
use DateTimeImmutable;

/**
 * Catalog's public use cases.
 *
 * The class translates domain exceptions into public ones and records
 * integration events in the outbox. Cross-context callers see only CatalogApi.
 */
final readonly class CatalogService implements CatalogApi
{
    public function __construct(
        private CourseOfferingRepository $offerings,
        private UnitOfWork $unitOfWork,
        private Outbox $outbox,
        private EventRecorder $recorder,
    ) {
    }

    public function publish(OfferingId $id, string $title, int $priceCents, int $capacity, string $startsAt): void
    {
        $offering = new CourseOffering(
            $id,
            $title,
            new Money($priceCents),
            new DateTimeImmutable($startsAt),
            $capacity,
        );

        $this->unitOfWork->transactional(function () use ($offering): void {
            $this->offerings->save($offering);
            $this->outbox->record($this->recorder->record(
                EventNames::COURSE_OFFERING_PUBLISHED,
                $offering->id()->value,
                [
                    'offering_id' => $offering->id()->value,
                    'title' => $offering->title(),
                    'price_cents' => $offering->price()->cents,
                    'currency' => $offering->price()->currency,
                    'capacity' => $offering->capacity(),
                    'starts_at' => $offering->startsAt()->format(DATE_ATOM),
                ],
            ));
        });
    }

    public function reserveSeat(OfferingId $id): void
    {
        $offering = $this->getAggregate($id);

        try {
            $offering->reserveSeat();
        } catch (SeatsExhausted $exception) {
            throw new NoSeatsAvailable($exception->getMessage(), 0, $exception);
        }

        $this->offerings->save($offering);
        $this->outbox->record($this->recorder->record(
            EventNames::SEAT_RESERVED,
            $offering->id()->value,
            [
                'offering_id' => $offering->id()->value,
                'reserved_seats' => $offering->reservedSeats(),
                'capacity' => $offering->capacity(),
            ],
        ));
    }

    public function releaseSeat(OfferingId $id): void
    {
        $offering = $this->getAggregate($id);
        $offering->releaseSeat();
        $this->offerings->save($offering);
        $this->outbox->record($this->recorder->record(
            EventNames::SEAT_RELEASED,
            $offering->id()->value,
            [
                'offering_id' => $offering->id()->value,
                'reserved_seats' => $offering->reservedSeats(),
                'capacity' => $offering->capacity(),
            ],
        ));
    }

    public function offering(OfferingId $id): OfferingView
    {
        return $this->view($this->getAggregate($id));
    }

    public function offerings(): array
    {
        $views = [];

        foreach ($this->offerings->all() as $offering) {
            $views[] = $this->view($offering);
        }

        return $views;
    }

    private function getAggregate(OfferingId $id): CourseOffering
    {
        $offering = $this->offerings->get($id);

        if ($offering === null) {
            throw new OfferingNotFound(sprintf('Unknown offering: %s', $id->value));
        }

        return $offering;
    }

    private function view(CourseOffering $offering): OfferingView
    {
        return new OfferingView(
            $offering->id(),
            $offering->title(),
            $offering->price(),
            $offering->capacity(),
            $offering->reservedSeats(),
            $offering->startsAt()->format(DATE_ATOM),
        );
    }
}
