<?php

declare(strict_types=1);

namespace ArchitectureLab\Architecture;

/**
 * Builds facts about one source tree: imports, violations, cycles and
 * coupling metrics. It does not decide whether the architecture is good; the
 * rules and the report do.
 */
final readonly class ArchitectureAnalyzer
{
    public function __construct(
        private SourceScanner $scanner,
        private DependencyGraph $graph,
        private RuleChecker $rules,
        private CycleDetector $cycles,
    ) {
    }

    public static function create(): self
    {
        return new self(new SourceScanner(), new DependencyGraph(), new RuleChecker(), new CycleDetector());
    }

    public function analyzeClean(string $projectRoot): ArchitectureReport
    {
        $files = array_values(array_filter(
            $this->scanner->scan($projectRoot . '/src', $projectRoot),
            static fn (SourceFile $file): bool => !str_starts_with($file->namespace, 'ArchitectureLab\\Baseline\\'),
        ));
        $map = ModuleMap::architectureLab();
        $edges = $this->graph->edges($files);
        $adjacency = $this->moduleAdjacency($edges, $map);

        return new ArchitectureReport(
            files: $files,
            edges: $edges,
            violations: $this->rules->checkClean($files, $map),
            metrics: $this->metrics($files, $adjacency, $map),
            cycles: $this->cycles->find($adjacency),
        );
    }

    public function analyzeLayered(string $projectRoot): ArchitectureReport
    {
        $files = $this->scanner->scan($projectRoot . '/src/Baseline/Layered', $projectRoot);
        $edges = $this->graph->edges($files);
        $adjacency = [];

        foreach ($edges as $edge) {
            $from = $this->layerOf($edge->fromNamespace);
            $to = $this->layerOf($edge->toNamespace);

            if ($from !== null && $to !== null && $from !== $to) {
                $adjacency[$from][] = $to;
            }
        }

        foreach ($adjacency as $layer => $targets) {
            sort($adjacency[$layer]);
            $adjacency[$layer] = array_values(array_unique($targets));
        }

        return new ArchitectureReport(
            files: $files,
            edges: $edges,
            violations: $this->rules->checkLayered($files),
            metrics: $this->metrics($files, $adjacency, null),
            cycles: $this->cycles->find($adjacency),
        );
    }

    /**
     * @param list<DependencyEdge> $edges
     *
     * @return array<string, list<string>>
     */
    private function moduleAdjacency(array $edges, ModuleMap $map): array
    {
        $adjacency = [];

        foreach ($map->modules as $module) {
            $adjacency[$module->name] = [];
        }

        foreach ($edges as $edge) {
            $from = $map->moduleFor($edge->fromClass);
            $to = $map->moduleFor($edge->toClass);

            if ($from === null || $to === null || $from->name === $to->name) {
                continue;
            }

            $adjacency[$from->name][] = $to->name;
        }

        foreach ($adjacency as $module => $targets) {
            sort($targets);
            $adjacency[$module] = array_values(array_unique($targets));
        }

        return $adjacency;
    }

    /**
     * @param list<SourceFile> $files
     * @param array<string, list<string>> $adjacency
     *
     * @return list<ModuleMetrics>
     */
    private function metrics(array $files, array $adjacency, ?ModuleMap $map): array
    {
        $classes = [];
        $interfaces = [];

        foreach ($files as $file) {
            $module = $map?->moduleFor($file->primaryClass());
            $group = $module !== null ? $module->name : $this->layerOf($file->namespace);

            if ($group === null) {
                continue;
            }

            foreach ($file->classes as $class) {
                $classes[$group] = ($classes[$group] ?? 0) + 1;

                if ($class->isInterface()) {
                    $interfaces[$group] = ($interfaces[$group] ?? 0) + 1;
                }
            }
        }

        $efferent = array_map(static fn (array $targets): int => count($targets), $adjacency);
        $afferent = [];

        foreach ($adjacency as $targets) {
            foreach ($targets as $target) {
                $afferent[$target] = ($afferent[$target] ?? 0) + 1;
            }
        }

        $metrics = [];

        foreach (array_keys($adjacency) as $module) {
            $ce = $efferent[$module] ?? 0;
            $ca = $afferent[$module] ?? 0;
            $instability = $ca + $ce === 0 ? 0.0 : $ce / ($ca + $ce);
            $classCount = $classes[$module] ?? 0;
            $interfaceCount = $interfaces[$module] ?? 0;
            $abstractness = $classCount === 0 ? 0.0 : $interfaceCount / $classCount;
            $metrics[] = new ModuleMetrics(
                module: $module,
                classes: $classCount,
                interfaces: $interfaceCount,
                afferent: $ca,
                efferent: $ce,
                instability: $instability,
                abstractness: $abstractness,
                distance: abs($abstractness + $instability - 1.0),
            );
        }

        usort($metrics, static fn (ModuleMetrics $left, ModuleMetrics $right): int => $left->module <=> $right->module);

        return $metrics;
    }

    private function layerOf(string $namespace): ?string
    {
        foreach (['Presentation', 'Application', 'Domain', 'Infrastructure'] as $layer) {
            if (str_contains($namespace, '\\' . $layer . '\\') || str_ends_with($namespace, '\\' . $layer)) {
                return $layer;
            }
        }

        return null;
    }
}
