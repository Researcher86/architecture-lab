<?php

declare(strict_types=1);

use ArchitectureLab\Architecture\ArchitectureAnalyzer;
use ArchitectureLab\Benchmark\Benchmark;
use ArchitectureLab\Foundation\ProjectRoot;

/*
 * Chapter 03: the cost of checking the dependency rule.
 *
 * The analyzer reads every PHP file, extracts imports, builds the graph,
 * runs the rules and computes module metrics. The clean tree is larger than
 * the layered baseline, so the two scans are not directly comparable per file
 * — they answer "what does an architecture gate cost?" and "what does the
 * baseline look like?".
 */

$root = ProjectRoot::path();
$analyzer = ArchitectureAnalyzer::create();

return [
    new Benchmark('clean.scan', 3, static function () use ($analyzer, $root): void {
        $analyzer->analyzeClean($root);
    }),
    new Benchmark('layered.scan', 20, static function () use ($analyzer, $root): void {
        $analyzer->analyzeLayered($root);
    }),
];
