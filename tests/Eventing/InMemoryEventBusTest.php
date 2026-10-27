<?php

declare(strict_types=1);

namespace ArchitectureLab\Tests\Eventing;

use ArchitectureLab\Eventing\EventEnvelope;
use ArchitectureLab\Eventing\EventListener;
use ArchitectureLab\Eventing\InMemoryEventBus;
use ArchitectureLab\Instrumentation\Counters;
use ArchitectureLab\Tests\Support\CallLog;
use PHPUnit\Framework\TestCase;

final class InMemoryEventBusTest extends TestCase
{
    public function testDeliversToEverySubscriberInRegistrationOrder(): void
    {
        $bus = new InMemoryEventBus(new Counters());
        $log = new CallLog();
        $first = new class ($log) implements EventListener {
            public function __construct(private readonly CallLog $log)
            {
            }

            public function handle(EventEnvelope $event): void
            {
                $this->log->record('first:' . $event->name);
            }
        };
        $second = new class ($log) implements EventListener {
            public function __construct(private readonly CallLog $log)
            {
            }

            public function handle(EventEnvelope $event): void
            {
                $this->log->record('second:' . $event->name);
            }
        };
        $bus->subscribe('test.event', $first);
        $bus->subscribe('test.event', $second);

        $listeners = $bus->publish($this->event());

        self::assertSame(2, $listeners);
        self::assertSame(['first:test.event', 'second:test.event'], $log->entries());
    }

    public function testAnEventWithoutSubscribersIsIgnored(): void
    {
        $bus = new InMemoryEventBus(new Counters());

        self::assertSame(0, $bus->publish($this->event()));
    }

    public function testCountsPublishedEvents(): void
    {
        $counters = new Counters();
        $bus = new InMemoryEventBus($counters);
        $bus->publish($this->event());
        $bus->publish($this->event());

        self::assertSame(2, $counters->eventsPublished);
    }

    private function event(): EventEnvelope
    {
        return new EventEnvelope('evt-1', 'test.event', 'agg', [], '2026-01-01T00:00:00+00:00');
    }
}
