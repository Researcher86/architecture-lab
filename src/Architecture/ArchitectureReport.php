<?php

declare(strict_types=1);

namespace ArchitectureLab\Architecture;

final readonly class ArchitectureReport
{
    /**
     * @param list<SourceFile> $files
     * @param list<DependencyEdge> $edges
     * @param list<Violation> $violations
     * @param list<ModuleMetrics> $metrics
     * @param list<list<string>> $cycles
     */
    public function __construct(
        public array $files,
        public array $edges,
        public array $violations,
        public array $metrics,
        public array $cycles,
    ) {
    }

    public function fileCount(): int
    {
        return count($this->files);
    }

    public function classCount(): int
    {
        return array_sum(array_map(static fn (SourceFile $file): int => count($file->classes), $this->files));
    }

    public function interfaceCount(): int
    {
        $count = 0;

        foreach ($this->files as $file) {
            foreach ($file->classes as $class) {
                if ($class->isInterface()) {
                    ++$count;
                }
            }
        }

        return $count;
    }

    public function lines(): int
    {
        return array_sum(array_map(static fn (SourceFile $file): int => $file->lines, $this->files));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'files' => $this->fileCount(),
            'classes' => $this->classCount(),
            'interfaces' => $this->interfaceCount(),
            'lines' => $this->lines(),
            'edges' => count($this->edges),
            'violations' => array_map(static fn (Violation $violation): array => $violation->toArray(), $this->violations),
            'metrics' => array_map(static fn (ModuleMetrics $metric): array => $metric->toArray(), $this->metrics),
            'cycles' => $this->cycles,
        ];
    }
}
