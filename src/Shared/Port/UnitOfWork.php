<?php

declare(strict_types=1);

namespace ArchitectureLab\Shared\Port;

/**
 * A transaction boundary owned by the application, not by a framework.
 *
 * Implementations may be a no-op for in-memory tests or a real SQLite
 * transaction. The callable's return value is passed through unchanged.
 */
interface UnitOfWork
{
    public function transactional(callable $work): mixed;
}
