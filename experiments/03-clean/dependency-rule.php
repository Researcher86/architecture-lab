<?php

declare(strict_types=1);

use ArchitectureLab\Architecture\ArchitectureAnalyzer;
use ArchitectureLab\Experiment\Experiment;
use ArchitectureLab\Experiment\Options;
use ArchitectureLab\Experiment\Output;
use ArchitectureLab\Foundation\ProjectRoot;

return new Experiment(
    name: 'clean:dependency-rule',
    description: 'scan the source tree and compare the layered baseline with the dependency rule',
    supports: [],
    run: static function (Options $options, Output $output): void {
        $analyzer = ArchitectureAnalyzer::create();
        $root = ProjectRoot::path();
        $start = hrtime(true);
        $clean = $analyzer->analyzeClean($root);
        $layered = $analyzer->analyzeLayered($root);
        $elapsed = (hrtime(true) - $start) / 1e9;

        $output->heading('Clean Architecture dependency rule', 'Imports, violations, cycles and module metrics.');
        $output->line(sprintf(
            'clean tree: files=%d classes=%d interfaces=%d lines=%d edges=%d violations=%d cycles=%d',
            $clean->fileCount(),
            $clean->classCount(),
            $clean->interfaceCount(),
            $clean->lines(),
            count($clean->edges),
            count($clean->violations),
            count($clean->cycles),
        ));
        $output->line(sprintf(
            'layered baseline: files=%d classes=%d lines=%d violations=%d',
            $layered->fileCount(),
            $layered->classCount(),
            $layered->lines(),
            count($layered->violations),
        ));
        $output->line();
        $output->line(sprintf('%-14s %7s %10s %6s %6s %7s %12s %9s', 'module', 'classes', 'interfaces', 'Ca', 'Ce', 'I', 'abstractness', 'distance'));

        foreach ($clean->metrics as $metrics) {
            $output->line(sprintf(
                '%-14s %7d %10d %6d %6d %7.3f %12.3f %9.3f',
                $metrics->module,
                $metrics->classes,
                $metrics->interfaces,
                $metrics->afferent,
                $metrics->efferent,
                $metrics->instability,
                $metrics->abstractness,
                $metrics->distance,
            ));
        }

        $output->line();
        $output->line('Layered violations:');

        foreach ($layered->violations as $violation) {
            $output->line(sprintf('  %-28s %s -> %s', $violation->rule, $violation->fromClass, $violation->toClass));
        }

        $output->measure('clean_files', $clean->fileCount());
        $output->measure('clean_classes', $clean->classCount());
        $output->measure('clean_interfaces', $clean->interfaceCount());
        $output->measure('clean_lines', $clean->lines());
        $output->measure('clean_violations', count($clean->violations));
        $output->measure('clean_cycles', count($clean->cycles));
        $output->measure('layered_violations', count($layered->violations));
        $output->measure('scan_ms', round($elapsed * 1000, 3));
    },
);
