<?php

declare(strict_types=1);

namespace ArchitectureLab\Tests\Reporting;

use ArchitectureLab\Eventing\EventEnvelope;
use ArchitectureLab\Eventing\EventNames;
use ArchitectureLab\Instrumentation\Counters;
use ArchitectureLab\Persistence\InMemoryDocumentStore;
use ArchitectureLab\Reporting\Application\OfferingCapacityQueryHandler;
use ArchitectureLab\Reporting\Application\StudentScheduleQueryHandler;
use ArchitectureLab\Reporting\Projection\OfferingCapacityProjection;
use ArchitectureLab\Reporting\Projection\StudentScheduleProjection;
use ArchitectureLab\Reporting\PublicApi\Query\OfferingCapacityQuery;
use ArchitectureLab\Reporting\PublicApi\Query\StudentScheduleQuery;
use PHPUnit\Framework\TestCase;

final class ProjectionTest extends TestCase
{
    public function testCapacityProjectionFoldsTheEventStream(): void
    {
        $store = new InMemoryDocumentStore(new Counters());
        $counters = new Counters();
        $projection = new OfferingCapacityProjection($store, $counters);
        $projection->handle($this->event(EventNames::COURSE_OFFERING_PUBLISHED, [
            'offering_id' => 'arch-101',
            'title' => 'Architecture',
            'capacity' => 4,
        ]));
        $projection->handle($this->event(EventNames::SEAT_RESERVED, ['offering_id' => 'arch-101']));
        $projection->handle($this->event(EventNames::ENROLLMENT_REQUESTED, ['offering_id' => 'arch-101']));
        $projection->handle($this->event(EventNames::ENROLLMENT_CONFIRMED, ['offering_id' => 'arch-101']));

        $view = new OfferingCapacityQueryHandler($store)->handle(new OfferingCapacityQuery('arch-101'));

        self::assertSame('Architecture', $view->title);
        self::assertSame(4, $view->capacity);
        self::assertSame(1, $view->reservedSeats);
        self::assertSame(0, $view->pending);
        self::assertSame(1, $view->confirmed);
        self::assertSame(0, $view->cancelled);
        self::assertSame(3, $view->seatsLeft());
        self::assertSame(4, $counters->projectionUpdates);
    }

    public function testReleaseAndCancellationAreCounted(): void
    {
        $store = new InMemoryDocumentStore(new Counters());
        $projection = new OfferingCapacityProjection($store, new Counters());
        $projection->handle($this->event(EventNames::SEAT_RESERVED, ['offering_id' => 'arch-101']));
        $projection->handle($this->event(EventNames::ENROLLMENT_REQUESTED, ['offering_id' => 'arch-101']));
        $projection->handle($this->event(EventNames::ENROLLMENT_CANCELLED, ['offering_id' => 'arch-101']));
        $projection->handle($this->event(EventNames::SEAT_RELEASED, ['offering_id' => 'arch-101']));

        $view = new OfferingCapacityQueryHandler($store)->handle(new OfferingCapacityQuery('arch-101'));

        self::assertSame(0, $view->reservedSeats);
        self::assertSame(0, $view->pending);
        self::assertSame(1, $view->cancelled);
    }

    public function testProjectionIgnoresEventsWithoutAnOffering(): void
    {
        $store = new InMemoryDocumentStore(new Counters());
        $counters = new Counters();
        $projection = new OfferingCapacityProjection($store, $counters);

        $projection->handle($this->event(EventNames::ENROLLMENT_CONFIRMED, []));

        self::assertSame(0, $counters->projectionUpdates);
        self::assertSame([], $store->all(OfferingCapacityProjection::COLLECTION));
    }

    public function testUnknownEventsAreIgnored(): void
    {
        $store = new InMemoryDocumentStore(new Counters());
        $counters = new Counters();
        $projection = new OfferingCapacityProjection($store, $counters);

        $projection->handle($this->event('test.unknown', ['offering_id' => 'arch-101']));

        self::assertSame(0, $counters->projectionUpdates);
    }

    public function testEmptyCapacityQueryReturnsZeroes(): void
    {
        $view = new OfferingCapacityQueryHandler(new InMemoryDocumentStore(new Counters()))
            ->handle(new OfferingCapacityQuery('missing'));

        self::assertSame('missing', $view->offeringId);
        self::assertSame(0, $view->capacity);
        self::assertSame(0, $view->seatsLeft());
    }

    public function testStudentScheduleKeepsTheLatestStatusPerEnrollment(): void
    {
        $store = new InMemoryDocumentStore(new Counters());
        $projection = new StudentScheduleProjection($store, new Counters());
        $payload = [
            'student_id' => 's-1',
            'enrollment_id' => 'enr-1',
            'offering_id' => 'arch-101',
            'course_title' => 'Architecture',
        ];

        $projection->handle($this->event(EventNames::ENROLLMENT_REQUESTED, $payload));
        $projection->handle($this->event(EventNames::ENROLLMENT_CONFIRMED, $payload));

        $view = new StudentScheduleQueryHandler($store)->handle(new StudentScheduleQuery('s-1'));

        self::assertSame('s-1', $view->studentId);
        self::assertCount(1, $view->entries);
        self::assertSame('confirmed', $view->entries[0]->status);
        self::assertSame('Architecture', $view->entries[0]->courseTitle);
    }

    public function testScheduleIsSortedByEnrollmentIdAndEmptyForUnknownStudent(): void
    {
        $store = new InMemoryDocumentStore(new Counters());
        $projection = new StudentScheduleProjection($store, new Counters());
        $projection->handle($this->event(EventNames::ENROLLMENT_REQUESTED, [
            'student_id' => 's-1',
            'enrollment_id' => 'enr-2',
            'offering_id' => 'arch-101',
            'course_title' => 'Architecture',
        ]));
        $projection->handle($this->event(EventNames::ENROLLMENT_REQUESTED, [
            'student_id' => 's-1',
            'enrollment_id' => 'enr-1',
            'offering_id' => 'arch-102',
            'course_title' => 'Testing',
        ]));

        $view = new StudentScheduleQueryHandler($store)->handle(new StudentScheduleQuery('s-1'));
        $empty = new StudentScheduleQueryHandler($store)->handle(new StudentScheduleQuery('unknown'));

        self::assertSame(['enr-1', 'enr-2'], array_map(static fn ($entry): string => $entry->enrollmentId, $view->entries));
        self::assertSame('unknown', $empty->studentId);
        self::assertSame([], $empty->entries);
    }

    public function testScheduleIgnoresOtherEventNames(): void
    {
        $store = new InMemoryDocumentStore(new Counters());
        $counters = new Counters();
        $projection = new StudentScheduleProjection($store, $counters);

        $projection->handle($this->event(EventNames::PAYMENT_SUCCEEDED, [
            'student_id' => 's-1',
            'enrollment_id' => 'enr-1',
        ]));

        self::assertSame(0, $counters->projectionUpdates);
    }

    /**
     * @param array<string, int|string|bool|null> $payload
     */
    private function event(string $name, array $payload): EventEnvelope
    {
        return new EventEnvelope('evt-' . $name, $name, 'agg', $payload, '2026-01-01T00:00:00+00:00');
    }
}
