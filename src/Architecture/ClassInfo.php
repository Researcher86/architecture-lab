<?php

declare(strict_types=1);

namespace ArchitectureLab\Architecture;

final readonly class ClassInfo
{
    public function __construct(
        public string $name,
        public ClassKind $kind,
        public bool $abstract,
        public int $line,
    ) {
    }

    public function isInterface(): bool
    {
        return $this->kind === ClassKind::Interface;
    }
}
