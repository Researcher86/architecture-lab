<?php

declare(strict_types=1);

namespace ArchitectureLab\Eventing;

use ArchitectureLab\Instrumentation\Counters;

/**
 * At-least-once delivery becomes effectively-once side effects here.
 *
 * The wrapper checks a key before executing the inner listener and records the
 * key after success. If a later listener in the same bus fails, the dispatcher
 * retries the whole event; already-successful listeners are skipped.
 */
final class IdempotentListener implements EventListener
{
    public function __construct(
        private readonly string $name,
        private readonly EventListener $inner,
        private readonly ProcessedEventStore $processed,
        private readonly Counters $counters,
    ) {
    }

    public function handle(EventEnvelope $event): void
    {
        if ($this->processed->has($this->name, $event->id)) {
            ++$this->counters->handlerSkips;

            return;
        }

        $this->inner->handle($event);
        $this->processed->mark($this->name, $event->id);
        ++$this->counters->eventHandlers;
    }
}
