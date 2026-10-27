<?php

declare(strict_types=1);

namespace ArchitectureLab\Shared;

use ArchitectureLab\Shared\Port\Clock;
use DateTimeImmutable;
use DateTimeZone;

/** A clock that only moves when a test asks it to. */
final class FrozenClock implements Clock
{
    private DateTimeImmutable $now;

    public function __construct(?DateTimeImmutable $start = null)
    {
        $this->now = $start ?? new DateTimeImmutable('2026-01-01T09:00:00+00:00', new DateTimeZone('UTC'));
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function advance(int $seconds = 1): void
    {
        $this->now = $this->now->modify(sprintf('+%d seconds', $seconds));
    }
}
