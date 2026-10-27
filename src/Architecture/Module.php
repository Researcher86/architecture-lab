<?php

declare(strict_types=1);

namespace ArchitectureLab\Architecture;

/** One bounded context's public surface and the namespace that owns it. */
final readonly class Module
{
    public function __construct(
        public string $name,
        public string $namespace,
        public string $publicApiNamespace,
        public string $description,
    ) {
    }
}
