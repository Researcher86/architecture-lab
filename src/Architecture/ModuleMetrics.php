<?php

declare(strict_types=1);

namespace ArchitectureLab\Architecture;

final readonly class ModuleMetrics
{
    public function __construct(
        public string $module,
        public int $classes,
        public int $interfaces,
        public int $afferent,
        public int $efferent,
        public float $instability,
        public float $abstractness,
        public float $distance,
    ) {
    }

    /**
     * @return array<string, int|float|string>
     */
    public function toArray(): array
    {
        return [
            'module' => $this->module,
            'classes' => $this->classes,
            'interfaces' => $this->interfaces,
            'afferent' => $this->afferent,
            'efferent' => $this->efferent,
            'instability' => round($this->instability, 3),
            'abstractness' => round($this->abstractness, 3),
            'distance' => round($this->distance, 3),
        ];
    }
}
