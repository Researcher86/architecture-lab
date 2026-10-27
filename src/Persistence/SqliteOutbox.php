<?php

declare(strict_types=1);

namespace ArchitectureLab\Persistence;

use ArchitectureLab\Eventing\EventEnvelope;
use ArchitectureLab\Eventing\Outbox;
use ArchitectureLab\Eventing\OutboxMessage;
use ArchitectureLab\Instrumentation\Counters;
use PDO;

/**
 * A real outbox table in the same database as the document store.
 *
 * The outbox row is written inside the same transaction as the aggregate, so
 * a crash cannot leave one without the other. Delivery is a separate step and
 * intentionally at-least-once.
 */
final class SqliteOutbox implements Outbox
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly Counters $counters,
    ) {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS outbox (
                position INTEGER PRIMARY KEY AUTOINCREMENT,
                id TEXT NOT NULL UNIQUE,
                name TEXT NOT NULL,
                aggregate_id TEXT NOT NULL,
                payload TEXT NOT NULL,
                occurred_at TEXT NOT NULL,
                attempts INTEGER NOT NULL DEFAULT 0,
                status TEXT NOT NULL DEFAULT \'pending\',
                last_error TEXT NULL
            )',
        );
    }

    public function record(EventEnvelope $event): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO outbox (id, name, aggregate_id, payload, occurred_at)
             VALUES (:id, :name, :aggregate_id, :payload, :occurred_at)',
        );
        $statement->execute([
            'id' => $event->id,
            'name' => $event->name,
            'aggregate_id' => $event->aggregateId,
            'payload' => json_encode($event->payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            'occurred_at' => $event->occurredAt,
        ]);
        ++$this->counters->sqlQueries;
    }

    public function pending(int $limit): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM outbox WHERE status = \'pending\' ORDER BY position LIMIT :limit',
        );
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        ++$this->counters->sqlQueries;

        $messages = [];

        foreach ($rows as $row) {
            $payload = json_decode((string) $row['payload'], true, 512, JSON_THROW_ON_ERROR);

            if (!is_array($payload)) {
                continue;
            }

            /** @var array<string, int|string|bool|null> $payload */
            $event = new EventEnvelope(
                id: (string) $row['id'],
                name: (string) $row['name'],
                aggregateId: (string) $row['aggregate_id'],
                payload: $payload,
                occurredAt: (string) $row['occurred_at'],
            );

            $messages[] = new OutboxMessage(
                id: $event->id,
                name: $event->name,
                aggregateId: $event->aggregateId,
                payload: $event->payload,
                occurredAt: $event->occurredAt,
                attempts: (int) $row['attempts'],
                event: $event,
            );
        }

        return $messages;
    }

    public function markDelivered(string $eventId): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE outbox SET status = \'delivered\', last_error = NULL WHERE id = :id',
        );
        $statement->execute(['id' => $eventId]);
        ++$this->counters->sqlQueries;
    }

    public function markFailed(string $eventId, string $error, bool $deadLetter): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE outbox
             SET attempts = attempts + 1,
                 status = :status,
                 last_error = :error
             WHERE id = :id',
        );
        $statement->execute([
            'id' => $eventId,
            'status' => $deadLetter ? 'dead' : 'pending',
            'error' => $error,
        ]);
        ++$this->counters->sqlQueries;
    }

    public function countPending(): int
    {
        ++$this->counters->sqlQueries;
        $statement = $this->pdo->query('SELECT COUNT(*) FROM outbox WHERE status = \'pending\'');

        if ($statement === false) {
            return 0;
        }

        return (int) $statement->fetchColumn();
    }

    public function countDeadLettered(): int
    {
        ++$this->counters->sqlQueries;
        $statement = $this->pdo->query('SELECT COUNT(*) FROM outbox WHERE status = \'dead\'');

        if ($statement === false) {
            return 0;
        }

        return (int) $statement->fetchColumn();
    }
}
