<?php

declare(strict_types=1);

namespace ArchitectureLab\Shared\Port;

/**
 * A small document store used by the repository adapters.
 *
 * The interface is deliberately narrower than a database: read, write and run
 * a transaction. It is a port, so application code never sees PDO or SQL.
 */
interface DocumentStore extends UnitOfWork
{
    /**
     * @param array<string, mixed> $document
     */
    public function put(string $collection, string $id, array $document): void;

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $collection, string $id): ?array;

    /**
     * @return list<array<string, mixed>>
     */
    public function all(string $collection): array;
}
