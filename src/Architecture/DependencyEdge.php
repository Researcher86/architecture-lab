<?php

declare(strict_types=1);

namespace ArchitectureLab\Architecture;

final readonly class DependencyEdge
{
    public function __construct(
        public string $fromClass,
        public string $fromNamespace,
        public string $toClass,
        public string $toNamespace,
        public string $path,
    ) {
    }
}
