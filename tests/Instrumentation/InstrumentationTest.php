<?php

declare(strict_types=1);

namespace ArchitectureLab\Tests\Instrumentation;

use ArchitectureLab\Instrumentation\Counters;
use ArchitectureLab\Instrumentation\Measure;
use ArchitectureLab\Instrumentation\MemoryMeter;
use ArchitectureLab\Instrumentation\Stopwatch;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;

final class InstrumentationTest extends TestCase
{
    public function testCountersIncrementAndReset(): void
    {
        $counters = new Counters();
        $counters->add('storeReads', 3);
        $counters->add('eventsPublished');
        ++$counters->sqlQueries;

        self::assertSame(3, $counters->storeReads);
        self::assertSame(1, $counters->eventsPublished);
        self::assertSame(1, $counters->sqlQueries);

        $counters->reset();

        self::assertSame(0, $counters->storeReads);
        self::assertSame(0, $counters->eventsPublished);
        self::assertSame(0, $counters->sqlQueries);
    }

    public function testUnknownCounterNameIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Counters()->add('cacheMisses');
    }

    public function testStopwatchMeasuresAndRefusesBadTransitions(): void
    {
        $stopwatch = new Stopwatch();
        $stopwatch->start();
        usleep(1000);
        $elapsed = $stopwatch->stop();

        self::assertGreaterThan(0.0, $elapsed);
        self::assertFalse($stopwatch->isRunning());

        $this->expectException(LogicException::class);
        $stopwatch->stop();
    }

    public function testStopwatchCannotStartTwice(): void
    {
        $stopwatch = new Stopwatch();
        $stopwatch->start();

        $this->expectException(LogicException::class);
        $stopwatch->start();
    }

    public function testMeasureReturnsCountersAndTiming(): void
    {
        $measure = new Measure();
        $measure->start();
        ++$measure->counters->storeWrites;
        $measure->stop();
        $result = $measure->toArray();

        self::assertSame(1, $result['storeWrites']);
        self::assertSame(0, $result['storeReads']);
        self::assertGreaterThanOrEqual(0.0, $result['elapsed_seconds']);
        self::assertArrayHasKey('php_delta_bytes', $result);
    }

    public function testMeasureRefusesToReadWhileRunning(): void
    {
        $measure = new Measure();
        $measure->start();

        $this->expectException(LogicException::class);

        $measure->toArray();
    }

    public function testMemoryMeterReportsASnapshot(): void
    {
        $meter = new MemoryMeter();
        $snapshot = $meter->snapshot();

        self::assertGreaterThan(0, $snapshot['usage']);
        self::assertGreaterThanOrEqual($snapshot['usage'], $snapshot['peak']);
        self::assertGreaterThan(0, $meter->baseline());
    }
}
