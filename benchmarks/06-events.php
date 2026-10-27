<?php

declare(strict_types=1);

use ArchitectureLab\Benchmark\Benchmark;
use ArchitectureLab\Eventing\EventEnvelope;
use ArchitectureLab\Eventing\EventListener;
use ArchitectureLab\Eventing\IdempotentListener;
use ArchitectureLab\Eventing\InMemoryEventBus;
use ArchitectureLab\Eventing\OutboxDispatcher;
use ArchitectureLab\Instrumentation\Counters;
use ArchitectureLab\Persistence\InMemoryOutbox;
use ArchitectureLab\Persistence\InMemoryProcessedEventStore;

/*
 * Chapter 06: outbox delivery.
 *
 * One operation writes a batch of 20 events and drains it. The listener is an
 * idempotent counter; the benchmark measures record + dispatch + mark, not a
 * network. `events.duplicate_skip` repeats one already-processed event to show
 * the price of the idempotency check that prevents a second side effect.
 */

$eventsCounters = new Counters();
$eventsOutbox = new InMemoryOutbox();
$eventsBus = new InMemoryEventBus($eventsCounters);
$eventsEffects = new class () implements EventListener {
    public int $effects = 0;

    public function handle(EventEnvelope $event): void
    {
        ++$this->effects;
    }
};
$eventsBus->subscribe(
    'benchmark.event',
    new IdempotentListener('benchmark', $eventsEffects, new InMemoryProcessedEventStore(), $eventsCounters),
);
$eventsDispatcher = new OutboxDispatcher($eventsOutbox, $eventsBus);
$eventsSequence = 1;

$duplicateCounters = new Counters();
$duplicateBus = new InMemoryEventBus($duplicateCounters);
$duplicateEffects = new class () implements EventListener {
    public int $effects = 0;

    public function handle(EventEnvelope $event): void
    {
        ++$this->effects;
    }
};
$duplicateBus->subscribe(
    'benchmark.duplicate',
    new IdempotentListener('benchmark-duplicate', $duplicateEffects, new InMemoryProcessedEventStore(), $duplicateCounters),
);
$duplicateEvent = new EventEnvelope('duplicate-1', 'benchmark.duplicate', 'agg', [], '2026-01-01T00:00:00+00:00');

return [
    new Benchmark('events.outbox_roundtrip', 50, static function () use ($eventsOutbox, $eventsDispatcher, &$eventsSequence): void {
        for ($index = 0; $index < 20; ++$index) {
            $eventsOutbox->record(new EventEnvelope(
                id: 'benchmark-evt-' . $eventsSequence,
                name: 'benchmark.event',
                aggregateId: 'agg',
                payload: ['index' => $eventsSequence],
                occurredAt: '2026-01-01T00:00:00+00:00',
            ));
            ++$eventsSequence;
        }

        $eventsDispatcher->dispatch(limit: 1000);
    }),
    new Benchmark('events.duplicate_skip', 1000, static function () use ($duplicateBus, $duplicateEvent): void {
        $duplicateBus->publish($duplicateEvent);
    }),
];
