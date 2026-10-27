<?php

declare(strict_types=1);

namespace ArchitectureLab\Persistence;

use ArchitectureLab\Instrumentation\Counters;
use ArchitectureLab\Shared\Port\DocumentStore;
use PDO;
use RuntimeException;

/**
 * A JSON-document adapter over SQLite.
 *
 * One table is enough for a lab about boundaries: the point is not to design a
 * schema, but to have a real transactional store behind the port. Nested
 * transactions use savepoints, so a handler can compose two units of work.
 */
final class SqliteDocumentStore implements DocumentStore
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly Counters $counters,
    ) {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS documents (
                collection TEXT NOT NULL,
                id TEXT NOT NULL,
                payload TEXT NOT NULL,
                PRIMARY KEY (collection, id)
            )',
        );
    }

    public function put(string $collection, string $id, array $document): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO documents (collection, id, payload) VALUES (:collection, :id, :payload)
             ON CONFLICT (collection, id) DO UPDATE SET payload = excluded.payload',
        );

        $statement->execute([
            'collection' => $collection,
            'id' => $id,
            'payload' => json_encode($document, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        ]);
        ++$this->counters->storeWrites;
        ++$this->counters->sqlQueries;
    }

    public function get(string $collection, string $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT payload FROM documents WHERE collection = :collection AND id = :id',
        );
        $statement->execute(['collection' => $collection, 'id' => $id]);
        $payload = $statement->fetchColumn();
        ++$this->counters->storeReads;
        ++$this->counters->sqlQueries;

        if ($payload === false) {
            return null;
        }

        $document = json_decode((string) $payload, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($document)) {
            throw new RuntimeException(sprintf('Stored document %s/%s is not an object', $collection, $id));
        }

        /** @var array<string, mixed> $document */
        return $document;
    }

    public function all(string $collection): array
    {
        $statement = $this->pdo->prepare(
            'SELECT payload FROM documents WHERE collection = :collection ORDER BY rowid',
        );
        $statement->execute(['collection' => $collection]);
        $rows = $statement->fetchAll(PDO::FETCH_COLUMN);
        ++$this->counters->storeReads;
        ++$this->counters->sqlQueries;

        $documents = [];

        foreach ($rows as $payload) {
            $document = json_decode((string) $payload, true, 512, JSON_THROW_ON_ERROR);

            if (!is_array($document)) {
                throw new RuntimeException(sprintf('Stored document in %s is not an object', $collection));
            }

            /** @var array<string, mixed> $document */
            $documents[] = $document;
        }

        return $documents;
    }

    public function transactional(callable $work): mixed
    {
        if ($this->pdo->inTransaction()) {
            $savepoint = 'sp_' . bin2hex(random_bytes(4));
            $this->pdo->exec('SAVEPOINT ' . $savepoint);

            try {
                $result = $work();
                $this->pdo->exec('RELEASE SAVEPOINT ' . $savepoint);

                return $result;
            } catch (\Throwable $exception) {
                $this->pdo->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
                $this->pdo->exec('RELEASE SAVEPOINT ' . $savepoint);

                throw $exception;
            }
        }

        $this->pdo->beginTransaction();

        try {
            $result = $work();
            $this->pdo->commit();

            return $result;
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();

            throw $exception;
        }
    }
}
