<?php

declare(strict_types=1);

namespace ArchitectureLab\Eventing;

/** Remembers which listener already handled which event. */
interface ProcessedEventStore
{
    public function has(string $listener, string $eventId): bool;

    public function mark(string $listener, string $eventId): void;
}
