<?php

declare(strict_types=1);

namespace ArchitectureLab\Tests\Catalog;

use ArchitectureLab\Catalog\Application\CatalogService;
use ArchitectureLab\Catalog\Infrastructure\DocumentCourseOfferingRepository;
use ArchitectureLab\Catalog\PublicApi\Exception\NoSeatsAvailable;
use ArchitectureLab\Catalog\PublicApi\Exception\OfferingNotFound;
use ArchitectureLab\Eventing\EventNames;
use ArchitectureLab\Eventing\EventRecorder;
use ArchitectureLab\Instrumentation\Counters;
use ArchitectureLab\Persistence\InMemoryDocumentStore;
use ArchitectureLab\Persistence\InMemoryOutbox;
use ArchitectureLab\Shared\FrozenClock;
use ArchitectureLab\Shared\OfferingId;
use ArchitectureLab\Shared\SequentialIdGenerator;
use PHPUnit\Framework\TestCase;

final class CatalogServiceTest extends TestCase
{
    public function testPublishesAnOfferingAndRecordsAnEvent(): void
    {
        [$catalog, $outbox] = $this->service();

        $catalog->publish(new OfferingId('arch-101'), 'Architecture', 12000, 4, '2026-02-01T10:00:00+00:00');

        $view = $catalog->offering(new OfferingId('arch-101'));

        self::assertSame('arch-101', $view->id->value);
        self::assertSame('Architecture', $view->title);
        self::assertSame(12000, $view->price->cents);
        self::assertSame(4, $view->capacity);
        self::assertSame(0, $view->reservedSeats);
        self::assertSame(4, $view->seatsLeft());
        self::assertCount(1, $catalog->offerings());

        $events = $outbox->pending(10);
        self::assertCount(1, $events);
        self::assertSame(EventNames::COURSE_OFFERING_PUBLISHED, $events[0]->name);
        self::assertSame(4, $events[0]->payload['capacity']);
    }

    public function testReservesASeatAndRecordsTheNewCount(): void
    {
        [$catalog, $outbox] = $this->service();
        $catalog->publish(new OfferingId('arch-101'), 'Architecture', 12000, 2, '2026-02-01T10:00:00+00:00');

        $catalog->reserveSeat(new OfferingId('arch-101'));

        self::assertSame(1, $catalog->offering(new OfferingId('arch-101'))->reservedSeats);
        $events = $outbox->pending(10);
        self::assertSame(EventNames::SEAT_RESERVED, $events[1]->name);
        self::assertSame(1, $events[1]->payload['reserved_seats']);
    }

    public function testRefusesToReserveBeyondCapacityWithAPublicException(): void
    {
        [$catalog] = $this->service();
        $catalog->publish(new OfferingId('arch-101'), 'Architecture', 12000, 1, '2026-02-01T10:00:00+00:00');
        $catalog->reserveSeat(new OfferingId('arch-101'));

        $this->expectException(NoSeatsAvailable::class);

        $catalog->reserveSeat(new OfferingId('arch-101'));
    }

    public function testReleasesASeat(): void
    {
        [$catalog] = $this->service();
        $catalog->publish(new OfferingId('arch-101'), 'Architecture', 12000, 2, '2026-02-01T10:00:00+00:00');
        $catalog->reserveSeat(new OfferingId('arch-101'));

        $catalog->releaseSeat(new OfferingId('arch-101'));

        self::assertSame(0, $catalog->offering(new OfferingId('arch-101'))->reservedSeats);
    }

    public function testUnknownOfferingIsRefused(): void
    {
        [$catalog] = $this->service();

        $this->expectException(OfferingNotFound::class);

        $catalog->offering(new OfferingId('missing'));
    }

    public function testReleaseWithoutReservationFails(): void
    {
        [$catalog] = $this->service();
        $catalog->publish(new OfferingId('arch-101'), 'Architecture', 12000, 2, '2026-02-01T10:00:00+00:00');

        $this->expectException(\LogicException::class);

        $catalog->releaseSeat(new OfferingId('arch-101'));
    }

    /**
     * @return array{CatalogService, InMemoryOutbox}
     */
    private function service(): array
    {
        $store = new InMemoryDocumentStore(new Counters());
        $outbox = new InMemoryOutbox();
        $clock = new FrozenClock();
        $ids = new SequentialIdGenerator();

        return [
            new CatalogService(
                new DocumentCourseOfferingRepository($store, new Counters()),
                $store,
                $outbox,
                new EventRecorder($ids, $clock),
            ),
            $outbox,
        ];
    }
}
