<?php

declare(strict_types=1);

namespace ArchitectureLab\Tests\Eventing;

use ArchitectureLab\Eventing\EventEnvelope;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class EventEnvelopeTest extends TestCase
{
    public function testRoundTripsThroughAnArray(): void
    {
        $event = new EventEnvelope(
            id: 'evt-1',
            name: 'enrollment.requested',
            aggregateId: 'enr-1',
            payload: ['student_id' => 's-1', 'price_cents' => 12000, 'paid' => false],
            occurredAt: '2026-02-01T10:00:00+00:00',
            version: 2,
        );

        $restored = EventEnvelope::fromArray($event->toArray());

        self::assertSame($event->toArray(), $restored->toArray());
    }

    public function testDefaultsVersionToOne(): void
    {
        $event = EventEnvelope::fromArray([
            'id' => 'evt-1',
            'name' => 'test.event',
            'aggregate_id' => 'agg',
            'payload' => [],
            'occurred_at' => '2026-01-01T00:00:00+00:00',
        ]);

        self::assertSame(1, $event->version);
    }

    public function testEmptyIdIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new EventEnvelope('', 'test.event', 'agg', [], '2026-01-01T00:00:00+00:00');
    }

    public function testEmptyNameIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new EventEnvelope('evt-1', ' ', 'agg', [], '2026-01-01T00:00:00+00:00');
    }

    public function testNonPositiveVersionIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new EventEnvelope('evt-1', 'test.event', 'agg', [], '2026-01-01T00:00:00+00:00', 0);
    }

    public function testNonArrayPayloadIsRefusedDuringDeserialization(): void
    {
        $this->expectException(InvalidArgumentException::class);

        EventEnvelope::fromArray(['payload' => 'not-an-array']);
    }
}
