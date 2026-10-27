<?php

declare(strict_types=1);

use ArchitectureLab\Eventing\EventEnvelope;
use ArchitectureLab\Eventing\EventListener;
use ArchitectureLab\Eventing\IdempotentListener;
use ArchitectureLab\Eventing\InMemoryEventBus;
use ArchitectureLab\Eventing\OutboxDispatcher;
use ArchitectureLab\Experiment\Experiment;
use ArchitectureLab\Experiment\Options;
use ArchitectureLab\Experiment\Output;
use ArchitectureLab\Instrumentation\Counters;
use ArchitectureLab\Persistence\InMemoryOutbox;
use ArchitectureLab\Persistence\InMemoryProcessedEventStore;
use ArchitectureLab\Runtime\LabRuntime;

return new Experiment(
    name: 'events:delivery',
    description: 'at-least-once delivery, retries, idempotency and a dead letter',
    supports: ['events', 'students', 'capacity'],
    run: static function (Options $options, Output $output): void {
        $events = $options->events(20);
        $counters = new Counters();
        $outbox = new InMemoryOutbox();
        $bus = new InMemoryEventBus($counters);
        $processed = new InMemoryProcessedEventStore();
        $flaky = new class () implements EventListener {
            public int $attempts = 0;
            public int $effects = 0;

            public function handle(EventEnvelope $event): void
            {
                ++$this->attempts;

                if ($this->attempts <= 2) {
                    throw new RuntimeException('temporary delivery failure');
                }

                ++$this->effects;
            }
        };
        $bus->subscribe('test.event', new IdempotentListener('flaky', $flaky, $processed, $counters));

        for ($index = 1; $index <= $events; ++$index) {
            $outbox->record(new EventEnvelope(
                id: 'evt-' . $index,
                name: 'test.event',
                aggregateId: 'agg-' . $index,
                payload: ['index' => $index],
                occurredAt: '2026-01-01T00:00:00+00:00',
            ));
        }

        $dispatcher = new OutboxDispatcher($outbox, $bus, 3);
        $delivered = 0;
        $failed = 0;

        for ($round = 0; $round < 4; ++$round) {
            $report = $dispatcher->dispatch($events);
            $delivered += $report->delivered;
            $failed += $report->failed;
        }

        $alwaysFailing = new class () implements EventListener {
            public function handle(EventEnvelope $event): void
            {
                throw new RuntimeException('permanent failure');
            }
        };
        $deadBus = new InMemoryEventBus($counters);
        $deadBus->subscribe('dead.event', new IdempotentListener('dead', $alwaysFailing, new InMemoryProcessedEventStore(), $counters));
        $deadOutbox = new InMemoryOutbox();
        $deadOutbox->record(new EventEnvelope('dead-1', 'dead.event', 'agg-dead', [], '2026-01-01T00:00:00+00:00'));
        $deadDispatcher = new OutboxDispatcher($deadOutbox, $deadBus, 2);
        $deadDispatcher->dispatch();
        $deadDispatcher->dispatch();

        $duplicateBus = new InMemoryEventBus($counters);
        $duplicateProcessed = new InMemoryProcessedEventStore();
        $duplicateListener = new class () implements EventListener {
            public int $effects = 0;

            public function handle(EventEnvelope $event): void
            {
                ++$this->effects;
            }
        };
        $duplicateBus->subscribe('duplicate.event', new IdempotentListener('duplicate', $duplicateListener, $duplicateProcessed, $counters));
        $duplicate = new EventEnvelope('dup-1', 'duplicate.event', 'agg', [], '2026-01-01T00:00:00+00:00');
        $duplicateBus->publish($duplicate);
        $duplicateBus->publish($duplicate);

        $runtime = LabRuntime::sqlite(':memory:', 'event-driven', 4);
        $runtime->publishCourse('arch-101', 'Architecture', 12000, $options->capacity(6), '2026-02-01T10:00:00+00:00');
        $students = $options->students(6);

        for ($student = 1; $student <= $students; ++$student) {
            try {
                $runtime->requestEnrollment(sprintf('event-%02d', $student), 'arch-101');
            } catch (ArchitectureLab\Enrollment\PublicApi\Exception\EnrollmentRejected) {
            }
        }

        $pendingBefore = $runtime->pendingEvents();
        $runtime->pump();

        $output->heading('Event delivery', 'A retry is not a duplicate side effect when the listener is idempotent.');
        $output->line(sprintf('flaky delivery: attempts=%d effects=%d delivered=%d failed=%d pending=%d', $flaky->attempts, $flaky->effects, $delivered, $failed, $outbox->countPending()));
        $output->line(sprintf('duplicate publish: effects=%d skips=%d', $duplicateListener->effects, $counters->handlerSkips));
        $output->line(sprintf('permanent failure: dead=%d pending=%d', $deadOutbox->countDeadLettered(), $deadOutbox->countPending()));
        $output->line(sprintf('runtime chain: pending before=%d, after=%d, notifications=%d', $pendingBefore, $runtime->pendingEvents(), $runtime->notifications()->count()));
        $output->measure('events', $events);
        $output->measure('flaky_attempts', $flaky->attempts);
        $output->measure('flaky_effects', $flaky->effects);
        $output->measure('flaky_delivered', $delivered);
        $output->measure('flaky_failed', $failed);
        $output->measure('duplicate_effects', $duplicateListener->effects);
        $output->measure('duplicate_skips', $counters->handlerSkips);
        $output->measure('dead_lettered', $deadOutbox->countDeadLettered());
        $output->measure('runtime_pending_before', $pendingBefore);
        $output->measure('runtime_notifications', $runtime->notifications()->count());
    },
);
