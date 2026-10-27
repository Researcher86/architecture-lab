<?php

declare(strict_types=1);

namespace ArchitectureLab\Benchmark\Formatter;

use ArchitectureLab\Benchmark\BenchmarkReport;
use ArchitectureLab\Foundation\ByteFormatter;

/** For reading. Fixed columns, so two runs can be diffed by eye. */
final readonly class TextFormatter implements Formatter
{
    public function format(BenchmarkReport $report): string
    {
        $environment = $report->environment;
        $output = sprintf("Benchmark suite: %s\n", $report->suite);
        $output .= sprintf(
            "PHP %s on %s %s, %s x %d, memory_limit %s, cgroup %s\n",
            $environment->phpVersion,
            $environment->os,
            $environment->kernel,
            $environment->cpu,
            $environment->cpuCount,
            $environment->memoryLimit,
            $environment->cgroupMemoryLimit === null
                ? 'unlimited'
                : ByteFormatter::format($environment->cgroupMemoryLimit),
        );
        $output .= sprintf(
            "OPcache %s, JIT %s, Xdebug %s, allocator %s, git %s\n\n",
            $environment->opcacheEnabled ? 'on' : 'off',
            $environment->jitEnabled ? 'on' : 'off',
            $environment->xdebugMode,
            $environment->allocator,
            $environment->commit,
        );

        $output .= sprintf(
            "%-24s | %8s | %11s %11s %11s | %12s | %10s %10s\n",
            'benchmark',
            'iters',
            'median',
            'p95',
            'max',
            'ops/sec',
            'PHP',
            'peak',
        );
        $output .= str_repeat('-', 116) . "\n";

        foreach ($report->results as $result) {
            $output .= sprintf(
                "%-24s | %8s | %8.3f ms %8.3f ms %8.3f ms | %12s | %10s %10s\n",
                $result->name,
                number_format($result->iterations),
                $result->timings->median * 1000,
                $result->timings->p95 * 1000,
                $result->timings->max * 1000,
                number_format($result->operationsPerSecond()),
                ByteFormatter::formatSigned($result->phpDelta),
                ByteFormatter::formatSigned($result->peakDelta),
            );
        }

        $output .= "\nTimings are per repetition of the whole iteration loop; ops/sec is\n";
        $output .= "derived from the median. No number here is universal: it describes\n";
        $output .= "the machine in the header and nothing else.\n";

        return $output;
    }
}
