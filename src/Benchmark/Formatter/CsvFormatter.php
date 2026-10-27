<?php

declare(strict_types=1);

namespace ArchitectureLab\Benchmark\Formatter;

use ArchitectureLab\Benchmark\BenchmarkReport;
use RuntimeException;

/**
 * For comparing across runs in a spreadsheet.
 *
 * CSV has no room for one environment header, so the fields that most often
 * explain a difference are repeated on every row.
 */
final readonly class CsvFormatter implements Formatter
{
    private const COLUMNS = [
        'suite',
        'benchmark',
        'iterations',
        'repetitions',
        'median_seconds',
        'min_seconds',
        'max_seconds',
        'mean_seconds',
        'p95_seconds',
        'mad_seconds',
        'operations_per_second',
        'php_delta_bytes',
        'peak_delta_bytes',
        'php_version',
        'cpu',
        'jit',
        'xdebug_mode',
        'allocator',
        'commit',
    ];

    public function format(BenchmarkReport $report): string
    {
        $environment = $report->environment;
        $rows = [self::COLUMNS];

        foreach ($report->results as $result) {
            $rows[] = [
                $report->suite,
                $result->name,
                (string) $result->iterations,
                (string) $result->repetitions,
                sprintf('%.9f', $result->timings->median),
                sprintf('%.9f', $result->timings->min),
                sprintf('%.9f', $result->timings->max),
                sprintf('%.9f', $result->timings->mean),
                sprintf('%.9f', $result->timings->p95),
                sprintf('%.9f', $result->timings->mad),
                sprintf('%.3f', $result->operationsPerSecond()),
                (string) $result->phpDelta,
                (string) $result->peakDelta,
                $environment->phpVersion,
                $environment->cpu,
                $environment->jitEnabled ? '1' : '0',
                $environment->xdebugMode,
                $environment->allocator,
                $environment->commit,
            ];
        }

        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw new RuntimeException('Unable to open a buffer for CSV output');
        }

        foreach ($rows as $row) {
            fputcsv($handle, $row, ',', '"', '\\');
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }
}
