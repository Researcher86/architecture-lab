<?php

declare(strict_types=1);

namespace ArchitectureLab\Tests\Benchmark;

use ArchitectureLab\Benchmark\Benchmark;
use ArchitectureLab\Benchmark\BenchmarkRunner;
use ArchitectureLab\Foundation\ProjectRoot;
use PHPUnit\Framework\TestCase;

final class BenchmarkSuitesTest extends TestCase
{
    /**
     * @return array<string, string>
     */
    private function suites(): array
    {
        $suites = [];

        foreach (glob(ProjectRoot::path() . '/benchmarks/*.php') ?: [] as $path) {
            $suites[basename($path, '.php')] = $path;
        }

        ksort($suites);

        return $suites;
    }

    public function testEveryChapterHasASuite(): void
    {
        self::assertSame([
            '01-layered',
            '02-hexagonal',
            '03-clean',
            '04-modules',
            '05-cqrs',
            '06-events',
            '07-ddd',
        ], array_keys($this->suites()));
    }

    public function testEverySuiteLoadsBenchmarkDescriptors(): void
    {
        foreach ($this->suites() as $suite => $path) {
            $loaded = require $path;

            self::assertIsArray($loaded, $suite);
            self::assertNotEmpty($loaded, $suite);

            foreach ($loaded as $benchmark) {
                self::assertInstanceOf(Benchmark::class, $benchmark, $suite);
                self::assertGreaterThan(0, $benchmark->iterations, $suite);
            }
        }
    }

    public function testEverySuiteRunsOneIteration(): void
    {
        $runner = new BenchmarkRunner(repetitions: 1, warmups: 0);

        foreach ($this->suites() as $suite => $path) {
            $loaded = require $path;

            self::assertIsArray($loaded, $suite);

            foreach ($loaded as $benchmark) {
                self::assertInstanceOf(Benchmark::class, $benchmark, $suite);
                $result = $runner->run($benchmark->name, $benchmark->run, iterations: 1);

                self::assertSame($benchmark->name, $result->name, $suite);
                self::assertGreaterThanOrEqual(0.0, $result->timings->median, $suite);
            }
        }
    }
}
