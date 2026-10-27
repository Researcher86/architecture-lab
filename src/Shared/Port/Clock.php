<?php

declare(strict_types=1);

namespace ArchitectureLab\Shared\Port;

use DateTimeImmutable;

/** A source of time that can be frozen for a reproducible scenario. */
interface Clock
{
    public function now(): DateTimeImmutable;
}
