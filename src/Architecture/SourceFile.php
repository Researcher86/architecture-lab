<?php

declare(strict_types=1);

namespace ArchitectureLab\Architecture;

final readonly class SourceFile
{
    /**
     * @param list<string> $imports fully qualified imported names
     * @param list<ClassInfo> $classes
     */
    public function __construct(
        public string $path,
        public string $namespace,
        public array $imports,
        public array $classes,
        public int $lines,
    ) {
    }

    public function primaryClass(): string
    {
        if ($this->classes === []) {
            return $this->namespace;
        }

        return $this->classes[0]->name;
    }
}
