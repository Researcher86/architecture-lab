<?php

declare(strict_types=1);

namespace ArchitectureLab\Shared;

use ArchitectureLab\Shared\Port\IdGenerator;

/**
 * Deterministic ids (`enr-1`, `inv-2`) rather than random UUIDs.
 *
 * Scenario reports and tests can then compare exact identifiers. A production
 * adapter would use UUIDs or a database sequence; that is deliberately kept
 * outside application code.
 */
final class SequentialIdGenerator implements IdGenerator
{
    /** @var array<string, int> */
    private array $counters = [];

    public function next(string $prefix): string
    {
        $next = ($this->counters[$prefix] ?? 0) + 1;
        $this->counters[$prefix] = $next;

        return $prefix . '-' . $next;
    }
}
