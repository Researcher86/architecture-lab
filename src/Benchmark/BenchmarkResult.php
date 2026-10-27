<?php

declare(strict_types=1);

namespace ArchitectureLab\Benchmark;

/**
 * One benchmark, measured.
 *
 * `phpDelta` is what the engine allocator reports; it is not RSS and it is not
 * native memory. The name keeps that limitation visible in JSON and CSV.
 */
final readonly class BenchmarkResult
{
    public function __construct(
        public string $name,
        public int $iterations,
        public int $repetitions,
        public float $elapsedSeconds,
        public Timings $timings,
        public int $phpDelta,
        public int $peakDelta,
    ) {
    }

    public function operationsPerSecond(): float
    {
        return $this->timings->median <= 0.0 ? 0.0 : $this->iterations / $this->timings->median;
    }

    public function secondsPerOperation(): float
    {
        return $this->timings->median / $this->iterations;
    }
}
