<?php

declare(strict_types=1);

namespace ArchitectureLab\Architecture;

/** The five contexts and the core namespaces they may share. */
final readonly class ModuleMap
{
    /** @var list<Module> */
    public array $modules;

    /**
     * @param list<Module> $modules
     */
    public function __construct(array $modules)
    {
        $this->modules = $modules;
    }

    public static function architectureLab(): self
    {
        return new self([
            new Module(
                'Catalog',
                'ArchitectureLab\\Catalog',
                'ArchitectureLab\\Catalog\\PublicApi',
                'Course offerings, capacity and seat ownership',
            ),
            new Module(
                'Enrollment',
                'ArchitectureLab\\Enrollment',
                'ArchitectureLab\\Enrollment\\PublicApi',
                'Student requests and enrollment state transitions',
            ),
            new Module(
                'Billing',
                'ArchitectureLab\\Billing',
                'ArchitectureLab\\Billing\\PublicApi',
                'Invoices and payment decisions',
            ),
            new Module(
                'Reporting',
                'ArchitectureLab\\Reporting',
                'ArchitectureLab\\Reporting\\PublicApi',
                'Event-built read models',
            ),
            new Module(
                'Notifications',
                'ArchitectureLab\\Notifications',
                'ArchitectureLab\\Notifications',
                'The observable notification side effect',
            ),
        ]);
    }

    public function moduleFor(string $class): ?Module
    {
        foreach ($this->modules as $module) {
            if (str_starts_with($class, $module->namespace . '\\')) {
                return $module;
            }
        }

        return null;
    }

    public function moduleByName(string $name): ?Module
    {
        foreach ($this->modules as $module) {
            if ($module->name === $name) {
                return $module;
            }
        }

        return null;
    }
}
