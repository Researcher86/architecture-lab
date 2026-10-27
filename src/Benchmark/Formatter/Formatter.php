<?php

declare(strict_types=1);

namespace ArchitectureLab\Benchmark\Formatter;

use ArchitectureLab\Benchmark\BenchmarkReport;

/** Renders a finished report. One implementation per output format. */
interface Formatter
{
    public function format(BenchmarkReport $report): string;
}
