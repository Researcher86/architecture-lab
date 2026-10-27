<?php

declare(strict_types=1);

namespace ArchitectureLab\Shared;

use ArchitectureLab\Shared\Port\Clock;
use DateTimeImmutable;
use DateTimeZone;

/** Wall-clock time for a running system. */
final readonly class SystemClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
