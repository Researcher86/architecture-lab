<?php

declare(strict_types=1);

use ArchitectureLab\Architecture\ArchitectureAnalyzer;
use ArchitectureLab\Architecture\ModuleMap;
use ArchitectureLab\Experiment\Experiment;
use ArchitectureLab\Experiment\Options;
use ArchitectureLab\Experiment\Output;
use ArchitectureLab\Foundation\ProjectRoot;

return new Experiment(
    name: 'modules:coupling',
    description: 'the module graph, cross-context edges and the public-API rule',
    supports: [],
    run: static function (Options $options, Output $output): void {
        $analyzer = ArchitectureAnalyzer::create();
        $report = $analyzer->analyzeClean(ProjectRoot::path());
        $map = ModuleMap::architectureLab();
        $crossContext = [];
        $publicApiEdges = 0;
        $moduleEdges = 0;

        foreach ($report->edges as $edge) {
            $from = $map->moduleFor($edge->fromClass);
            $to = $map->moduleFor($edge->toClass);

            if ($from === null || $to === null || $from->name === $to->name) {
                continue;
            }

            ++$moduleEdges;
            $key = $from->name . ' -> ' . $to->name;

            if (!str_starts_with($edge->toClass, $to->publicApiNamespace)) {
                $crossContext[$key] = ($crossContext[$key] ?? 0) + 1;
            } else {
                ++$publicApiEdges;
            }
        }

        $output->heading('Modular monolith', 'A module is a namespace with a public API, not a deployment unit.');
        $output->line(sprintf('modules: %d, cross-context imports: %d, through PublicApi: %d', count($map->modules), $moduleEdges, $publicApiEdges));
        $output->line(sprintf('violations: %d, cycles: %d', count($report->violations), count($report->cycles)));
        $output->line();
        $output->line(sprintf('%-14s %7s %6s %6s %7s %12s', 'module', 'classes', 'Ca', 'Ce', 'I', 'abstractness'));

        foreach ($report->metrics as $metrics) {
            $output->line(sprintf(
                '%-14s %7d %6d %6d %7.3f %12.3f',
                $metrics->module,
                $metrics->classes,
                $metrics->afferent,
                $metrics->efferent,
                $metrics->instability,
                $metrics->abstractness,
            ));
        }

        if ($crossContext !== []) {
            $output->line();
            $output->line('Imports that bypass PublicApi:');

            foreach ($crossContext as $edge => $count) {
                $output->line(sprintf('  %-32s %d', $edge, $count));
            }
        }

        $output->measure('modules', count($map->modules));
        $output->measure('module_edges', $moduleEdges);
        $output->measure('public_api_edges', $publicApiEdges);
        $output->measure('bypass_edges', array_sum($crossContext));
        $output->measure('violations', count($report->violations));
        $output->measure('cycles', count($report->cycles));
    },
);
