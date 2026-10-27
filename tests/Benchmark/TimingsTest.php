<?php

declare(strict_types=1);

namespace ArchitectureLab\Tests\Benchmark;

use ArchitectureLab\Benchmark\Timings;
use PHPUnit\Framework\TestCase;

final class TimingsTest extends TestCase
{
    public function testSummarizesFiveSortedSamples(): void
    {
        $timings = Timings::of([1.0, 2.0, 3.0, 4.0, 5.0]);

        self::assertSame(1.0, $timings->min);
        self::assertSame(5.0, $timings->max);
        self::assertSame(3.0, $timings->mean);
        self::assertSame(3.0, $timings->median);
        self::assertSame(5.0, $timings->p95);
        self::assertSame(1.0, $timings->mad);
    }

    public function testSingleSampleUsesTheSameValueEverywhere(): void
    {
        $timings = Timings::of([0.25]);

        self::assertSame(0.25, $timings->min);
        self::assertSame(0.25, $timings->max);
        self::assertSame(0.25, $timings->mean);
        self::assertSame(0.25, $timings->median);
        self::assertSame(0.25, $timings->p95);
        self::assertSame(0.0, $timings->mad);
    }

    public function testSortsSamplesBeforeSummarizing(): void
    {
        $timings = Timings::of([3.0, 1.0, 2.0]);

        self::assertSame(1.0, $timings->min);
        self::assertSame(3.0, $timings->max);
        self::assertSame(2.0, $timings->median);
    }

    public function testUsesNearestRankForP95(): void
    {
        $timings = Timings::of([1.0, 2.0, 3.0, 4.0]);

        self::assertSame(4.0, $timings->p95);
    }
}
