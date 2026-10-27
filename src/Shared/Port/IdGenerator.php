<?php

declare(strict_types=1);

namespace ArchitectureLab\Shared\Port;

/** Produces identifiers without a database sequence, so tests stay deterministic. */
interface IdGenerator
{
    public function next(string $prefix): string;
}
