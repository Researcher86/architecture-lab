<?php

declare(strict_types=1);

namespace ArchitectureLab\Persistence;

use ArchitectureLab\Eventing\ProcessedEventStore;
use ArchitectureLab\Instrumentation\Counters;
use PDO;

/** Durable idempotency keys, one row per listener and event. */
final class SqliteProcessedEventStore implements ProcessedEventStore
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly Counters $counters,
    ) {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS processed_events (
                listener TEXT NOT NULL,
                event_id TEXT NOT NULL,
                PRIMARY KEY (listener, event_id)
            )',
        );
    }

    public function has(string $listener, string $eventId): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT 1 FROM processed_events WHERE listener = :listener AND event_id = :event_id',
        );
        $statement->execute(['listener' => $listener, 'event_id' => $eventId]);
        $found = $statement->fetchColumn() !== false;
        ++$this->counters->sqlQueries;

        return $found;
    }

    public function mark(string $listener, string $eventId): void
    {
        $statement = $this->pdo->prepare(
            'INSERT OR IGNORE INTO processed_events (listener, event_id) VALUES (:listener, :event_id)',
        );
        $statement->execute(['listener' => $listener, 'event_id' => $eventId]);
        ++$this->counters->sqlQueries;
    }
}
