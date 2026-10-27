<?php

declare(strict_types=1);

namespace ArchitectureLab\Eventing;

/** Dispatches a message to every listener registered for its name. */
interface EventBus
{
    public function subscribe(string $eventName, EventListener $listener): void;

    /**
     * @return int number of listeners invoked
     */
    public function publish(EventEnvelope $event): int;
}
