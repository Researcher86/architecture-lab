<?php

declare(strict_types=1);

use ArchitectureLab\Architecture\ArchitectureAnalyzer;
use ArchitectureLab\Architecture\ModuleMap;
use ArchitectureLab\Architecture\SourceScanner;
use ArchitectureLab\Experiment\Experiment;
use ArchitectureLab\Experiment\Options;
use ArchitectureLab\Experiment\Output;
use ArchitectureLab\Foundation\ProjectRoot;

return new Experiment(
    name: 'ddd:contexts',
    description: 'the context map, anti-corruption adapters and the cost of a shared model',
    supports: [],
    run: static function (Options $options, Output $output): void {
        $root = ProjectRoot::path();
        $scanner = new SourceScanner();
        $files = $scanner->scan($root . '/src', $root);
        $map = ModuleMap::architectureLab();
        $report = ArchitectureAnalyzer::create()->analyzeClean($root);
        $publicApiClasses = 0;
        $aclAdapters = 0;
        $publicApiFiles = 0;

        foreach ($files as $file) {
            $source = $map->moduleFor($file->primaryClass());

            if ($source === null) {
                continue;
            }

            if (str_starts_with($file->namespace, $source->publicApiNamespace)) {
                ++$publicApiFiles;
                $publicApiClasses += count($file->classes);
            }

            foreach ($file->imports as $import) {
                $target = $map->moduleFor($import);

                if ($target === null || $target->name === $source->name) {
                    continue;
                }

                if (str_contains($file->namespace, '\\Infrastructure')
                    && str_starts_with($import, $target->publicApiNamespace)) {
                    ++$aclAdapters;
                }
            }
        }

        $output->heading('Bounded contexts', 'Five models in one deployable unit, with explicit translations.');
        $output->line(sprintf('contexts: %d, public API files: %d, public API types: %d', count($map->modules), $publicApiFiles, $publicApiClasses));
        $output->line(sprintf('anti-corruption adapters: %d, dependency violations: %d, cycles: %d', $aclAdapters, count($report->violations), count($report->cycles)));
        $output->line();
        $output->line(sprintf('%-14s %-44s %7s %6s', 'context', 'public API', 'classes', 'Ca'));

        foreach ($report->metrics as $metrics) {
            $module = $map->moduleByName($metrics->module);
            $output->line(sprintf(
                '%-14s %-44s %7d %6d',
                $metrics->module,
                $module === null ? '-' : $module->publicApiNamespace,
                $metrics->classes,
                $metrics->afferent,
            ));
        }

        $layered = ArchitectureAnalyzer::create()->analyzeLayered($root);
        $output->line();
        $output->line(sprintf('baseline shared model: %d namespace-level layers, %d layer violations', 4, count($layered->violations)));
        $output->measure('contexts', count($map->modules));
        $output->measure('public_api_files', $publicApiFiles);
        $output->measure('public_api_types', $publicApiClasses);
        $output->measure('acl_adapters', $aclAdapters);
        $output->measure('violations', count($report->violations));
        $output->measure('cycles', count($report->cycles));
        $output->measure('baseline_layer_violations', count($layered->violations));
    },
);
