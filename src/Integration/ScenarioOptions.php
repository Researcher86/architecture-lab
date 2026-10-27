<?php

declare(strict_types=1);

namespace ArchitectureLab\Integration;

final readonly class ScenarioOptions
{
    public function __construct(
        public int $students = 12,
        public int $offerings = 2,
        public int $capacity = 4,
        public int $failEvery = 4,
        public string $seed = 'architecture-lab',
        public string $mode = 'event-driven',
        public string $startsAt = '2026-02-01T10:00:00+00:00',
    ) {
        if ($this->students < 1) {
            throw new \InvalidArgumentException('students must be positive');
        }

        if ($this->offerings < 1) {
            throw new \InvalidArgumentException('offerings must be positive');
        }

        if ($this->capacity < 1) {
            throw new \InvalidArgumentException('capacity must be positive');
        }

        if ($this->failEvery < 0) {
            throw new \InvalidArgumentException('failEvery cannot be negative');
        }
    }
}
