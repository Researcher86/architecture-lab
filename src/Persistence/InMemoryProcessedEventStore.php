<?php

declare(strict_types=1);

namespace ArchitectureLab\Persistence;

use ArchitectureLab\Eventing\ProcessedEventStore;

/** Process-local processed-event keys for fast tests. */
final class InMemoryProcessedEventStore implements ProcessedEventStore
{
    /** @var array<string, true> */
    private array $keys = [];

    public function has(string $listener, string $eventId): bool
    {
        return isset($this->keys[$listener . ':' . $eventId]);
    }

    public function mark(string $listener, string $eventId): void
    {
        $this->keys[$listener . ':' . $eventId] = true;
    }
}
