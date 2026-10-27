<?php

declare(strict_types=1);

namespace ArchitectureLab\Eventing;

use ArchitectureLab\Instrumentation\Counters;

/**
 * A synchronous in-process bus.
 *
 * The bus is deliberately not an async runtime. It executes handlers in the
 * caller's process and propagates their exceptions; the outbox dispatcher is
 * responsible for retrying a failed delivery later.
 */
final class InMemoryEventBus implements EventBus
{
    /** @var array<string, list<EventListener>> */
    private array $listeners = [];

    public function __construct(
        private readonly Counters $counters,
    ) {
    }

    public function subscribe(string $eventName, EventListener $listener): void
    {
        $this->listeners[$eventName][] = $listener;
    }

    public function publish(EventEnvelope $event): int
    {
        $this->counters->eventsPublished++;
        $listeners = $this->listeners[$event->name] ?? [];

        foreach ($listeners as $listener) {
            $listener->handle($event);
        }

        return count($listeners);
    }
}
