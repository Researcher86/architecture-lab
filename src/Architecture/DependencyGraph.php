<?php

declare(strict_types=1);

namespace ArchitectureLab\Architecture;

/** Builds internal class dependencies from `use` statements. */
final class DependencyGraph
{
    private const ROOT = 'ArchitectureLab\\';

    /**
     * @param list<SourceFile> $files
     *
     * @return list<DependencyEdge>
     */
    public function edges(array $files): array
    {
        $edges = [];

        foreach ($files as $file) {
            foreach ($file->imports as $import) {
                if (!str_starts_with($import, self::ROOT)) {
                    continue;
                }

                $edges[] = new DependencyEdge(
                    fromClass: $file->primaryClass(),
                    fromNamespace: $file->namespace,
                    toClass: $import,
                    toNamespace: $this->namespaceOfClass($import),
                    path: $file->path,
                );
            }
        }

        return $edges;
    }

    private function namespaceOfClass(string $class): string
    {
        $position = strrpos($class, '\\');

        return $position === false ? '' : substr($class, 0, $position);
    }
}
