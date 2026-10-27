<?php

declare(strict_types=1);

namespace ArchitectureLab\Persistence;

use ArchitectureLab\Instrumentation\Counters;
use ArchitectureLab\Shared\Port\DocumentStore;

/**
 * A document store for unit tests and adapter-swap experiments.
 *
 * It is not a database: values live in PHP arrays, and its transaction
 * implementation is a state snapshot. That is enough to prove that the core
 * use cases do not know about PDO.
 */
final class InMemoryDocumentStore implements DocumentStore
{
    /** @var array<string, array<string, array<string, mixed>>> */
    private array $collections = [];

    public function __construct(
        private readonly Counters $counters,
    ) {
    }

    public function put(string $collection, string $id, array $document): void
    {
        $this->collections[$collection][$id] = $document;
        ++$this->counters->storeWrites;
    }

    public function get(string $collection, string $id): ?array
    {
        ++$this->counters->storeReads;

        return $this->collections[$collection][$id] ?? null;
    }

    public function all(string $collection): array
    {
        $documents = array_values($this->collections[$collection] ?? []);
        $this->counters->storeReads += count($documents) + 1;

        return $documents;
    }

    public function transactional(callable $work): mixed
    {
        $snapshot = $this->collections;

        try {
            return $work();
        } catch (\Throwable $exception) {
            $this->collections = $snapshot;

            throw $exception;
        }
    }
}
