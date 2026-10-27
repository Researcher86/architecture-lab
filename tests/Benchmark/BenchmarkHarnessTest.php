<?php

declare(strict_types=1);

namespace ArchitectureLab\Tests\Benchmark;

use ArchitectureLab\Benchmark\BenchmarkReport;
use ArchitectureLab\Benchmark\BenchmarkResult;
use ArchitectureLab\Benchmark\BenchmarkRunner;
use ArchitectureLab\Benchmark\Environment;
use ArchitectureLab\Benchmark\Formatter\CsvFormatter;
use ArchitectureLab\Benchmark\Formatter\JsonFormatter;
use ArchitectureLab\Benchmark\Formatter\TextFormatter;
use ArchitectureLab\Benchmark\Timings;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class BenchmarkHarnessTest extends TestCase
{
    public function testRunnerExecutesWarmupsAndRepetitions(): void
    {
        $calls = 0;
        $result = new BenchmarkRunner(repetitions: 3, warmups: 1)->run(
            'counter',
            static function () use (&$calls): void {
                ++$calls;
            },
            iterations: 2,
        );

        self::assertSame(8, $calls);
        self::assertSame('counter', $result->name);
        self::assertSame(2, $result->iterations);
        self::assertSame(3, $result->repetitions);
        self::assertGreaterThanOrEqual(0.0, $result->elapsedSeconds);
        self::assertGreaterThan(0.0, $result->operationsPerSecond());
        self::assertGreaterThan(0.0, $result->secondsPerOperation());
    }

    public function testRunnerRejectsInvalidConfiguration(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new BenchmarkRunner(repetitions: 0);
    }

    public function testRunnerRejectsNegativeWarmups(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new BenchmarkRunner(warmups: -1);
    }

    public function testRunnerRejectsZeroIterations(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new BenchmarkRunner()->run('empty', static fn (): null => null, iterations: 0);
    }

    public function testTextFormatterIncludesEnvironmentAndResult(): void
    {
        $text = new TextFormatter()->format($this->report());

        self::assertStringContainsString('Benchmark suite: test', $text);
        self::assertStringContainsString('counter', $text);
        self::assertStringContainsString('PHP ' . PHP_VERSION, $text);
        self::assertStringContainsString('ops/sec', $text);
    }

    public function testJsonFormatterProducesStructuredOutput(): void
    {
        $json = new JsonFormatter()->format($this->report());
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($decoded);
        self::assertSame('test', $decoded['suite']);
        self::assertSame(PHP_VERSION, $decoded['environment']['php_version']);
        self::assertSame('counter', $decoded['results'][0]['name']);
        self::assertSame(10, $decoded['results'][0]['iterations']);
        self::assertArrayHasKey('median', $decoded['results'][0]['seconds']);
    }

    public function testCsvFormatterHasOneHeaderAndOneRow(): void
    {
        $csv = new CsvFormatter()->format($this->report());
        $lines = array_values(array_filter(explode("\n", trim($csv))));

        self::assertCount(2, $lines);
        self::assertStringContainsString('suite,benchmark,iterations', $lines[0]);
        self::assertStringContainsString('test,counter,10', $lines[1]);
        self::assertStringContainsString(PHP_VERSION, $lines[1]);
    }

    private function report(): BenchmarkReport
    {
        $timings = Timings::of([0.001, 0.002, 0.003, 0.004, 0.005]);
        $result = new BenchmarkResult(
            name: 'counter',
            iterations: 10,
            repetitions: 5,
            elapsedSeconds: 0.015,
            timings: $timings,
            phpDelta: 1024,
            peakDelta: 2048,
        );

        return new BenchmarkReport('test', Environment::detect(), [$result]);
    }
}
