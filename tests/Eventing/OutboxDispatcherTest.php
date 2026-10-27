<?php

declare(strict_types=1);

namespace ArchitectureLab\Tests\Eventing;

use ArchitectureLab\Eventing\EventEnvelope;
use ArchitectureLab\Eventing\EventListener;
use ArchitectureLab\Eventing\IdempotentListener;
use ArchitectureLab\Eventing\InMemoryEventBus;
use ArchitectureLab\Eventing\OutboxDispatcher;
use ArchitectureLab\Instrumentation\Counters;
use ArchitectureLab\Persistence\InMemoryOutbox;
use ArchitectureLab\Persistence\InMemoryProcessedEventStore;
use ArchitectureLab\Tests\Support\CallLog;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class OutboxDispatcherTest extends TestCase
{
    public function testDeliversEveryPendingMessage(): void
    {
        $outbox = new InMemoryOutbox();
        $outbox->record($this->event('evt-1'));
        $outbox->record($this->event('evt-2'));
        $bus = new InMemoryEventBus(new Counters());
        $received = new CallLog();
        $bus->subscribe('test.event', $this->listener($received));

        $report = new OutboxDispatcher($outbox, $bus)->dispatch();

        self::assertSame(2, $report->delivered);
        self::assertSame(2, $report->handled);
        self::assertSame(0, $report->failed);
        self::assertSame(0, $outbox->countPending());
        self::assertSame(['evt-1', 'evt-2'], $received->entries());
    }

    public function testRetriesATemporaryFailureUntilItSucceeds(): void
    {
        $outbox = new InMemoryOutbox();
        $outbox->record($this->event('evt-1'));
        $bus = new InMemoryEventBus(new Counters());
        $listener = new class () implements EventListener {
            public int $attempts = 0;

            public function handle(EventEnvelope $event): void
            {
                ++$this->attempts;

                if ($this->attempts < 2) {
                    throw new RuntimeException('temporary');
                }
            }
        };
        $bus->subscribe('test.event', $listener);
        $dispatcher = new OutboxDispatcher($outbox, $bus, maxAttempts: 3);

        $first = $dispatcher->dispatch();
        $second = $dispatcher->dispatch();

        self::assertSame(1, $first->failed);
        self::assertSame(0, $first->deadLettered);
        self::assertSame(1, $second->delivered);
        self::assertSame(0, $outbox->countPending());
        self::assertSame(2, $listener->attempts);
    }

    public function testDeadLettersAPermanentFailureAfterMaxAttempts(): void
    {
        $outbox = new InMemoryOutbox();
        $outbox->record($this->event('evt-1'));
        $bus = new InMemoryEventBus(new Counters());
        $bus->subscribe('test.event', new class () implements EventListener {
            public function handle(EventEnvelope $event): void
            {
                throw new RuntimeException('permanent');
            }
        });
        $dispatcher = new OutboxDispatcher($outbox, $bus, maxAttempts: 2);

        $first = $dispatcher->dispatch();
        $second = $dispatcher->dispatch();

        self::assertSame(1, $first->failed);
        self::assertSame(0, $first->deadLettered);
        self::assertSame(1, $second->failed);
        self::assertSame(1, $second->deadLettered);
        self::assertSame(0, $outbox->countPending());
        self::assertSame(1, $outbox->countDeadLettered());
    }

    public function testLimitCapsOnePass(): void
    {
        $outbox = new InMemoryOutbox();
        $outbox->record($this->event('evt-1'));
        $outbox->record($this->event('evt-2'));
        $bus = new InMemoryEventBus(new Counters());
        $received = new CallLog();
        $bus->subscribe('test.event', $this->listener($received));

        $report = new OutboxDispatcher($outbox, $bus)->dispatch(limit: 1);

        self::assertSame(1, $report->delivered);
        self::assertSame(1, $outbox->countPending());
    }

    public function testIdempotentListenerSuppressesRedelivery(): void
    {
        $counters = new Counters();
        $processed = new InMemoryProcessedEventStore();
        $inner = new class () implements EventListener {
            public int $effects = 0;

            public function handle(EventEnvelope $event): void
            {
                ++$this->effects;
            }
        };
        $listener = new IdempotentListener('test', $inner, $processed, $counters);
        $event = $this->event('evt-1');

        $listener->handle($event);
        $listener->handle($event);

        self::assertSame(1, $inner->effects);
        self::assertSame(1, $counters->eventHandlers);
        self::assertSame(1, $counters->handlerSkips);
    }

    public function testFailedListenerIsNotMarkedAsProcessed(): void
    {
        $processed = new InMemoryProcessedEventStore();
        $attempts = 0;
        $listener = new IdempotentListener(
            'test',
            new class ($attempts) implements EventListener {
                public function __construct(private int &$attempts)
                {
                }

                public function handle(EventEnvelope $event): void
                {
                    ++$this->attempts;

                    if ($this->attempts === 1) {
                        throw new RuntimeException('first attempt fails');
                    }
                }
            },
            $processed,
            new Counters(),
        );
        $event = $this->event('evt-1');

        try {
            $listener->handle($event);
            self::fail('The first delivery should throw');
        } catch (RuntimeException) {
            self::assertFalse($processed->has('test', 'evt-1'));
        }

        $listener->handle($event);

        self::assertSame(2, $attempts);
        self::assertTrue($processed->has('test', 'evt-1'));
    }

    /**
     * @param CallLog $received
     */
    private function listener(CallLog $received): EventListener
    {
        return new class ($received) implements EventListener {
            public function __construct(private readonly CallLog $received)
            {
            }

            public function handle(EventEnvelope $event): void
            {
                $this->received->record($event->id);
            }
        };
    }

    private function event(string $id): EventEnvelope
    {
        return new EventEnvelope($id, 'test.event', 'agg', [], '2026-01-01T00:00:00+00:00');
    }
}
