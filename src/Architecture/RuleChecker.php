<?php

declare(strict_types=1);

namespace ArchitectureLab\Architecture;

/**
 * Checks the dependency rule, not file placement.
 *
 * A violation is an import that points in a forbidden direction. The checker
 * sees `use` statements, so a class loaded dynamically can escape it; the lab
 * does not pretend this is a sandbox.
 */
final class RuleChecker
{
    /**
     * @param list<SourceFile> $files
     *
     * @return list<Violation>
     */
    public function checkClean(array $files, ModuleMap $map): array
    {
        $violations = [];

        foreach ($files as $file) {
            foreach ($file->imports as $import) {
                if (!str_starts_with($import, 'ArchitectureLab\\')) {
                    continue;
                }

                $sourceModule = $map->moduleFor($file->primaryClass());
                $targetModule = $map->moduleFor($import);
                $sourceInDomain = str_contains($file->namespace, '\\Domain');
                $sourceInApplication = str_contains($file->namespace, '\\Application\\')
                    || str_ends_with($file->namespace, '\\Application');

                if ($sourceInDomain && !str_starts_with($import, 'ArchitectureLab\\Shared\\')) {
                    $violations[] = $this->violation(
                        'domain-purity',
                        $file,
                        $import,
                        'Domain code may depend only on the shared kernel',
                    );
                }

                if ($sourceInApplication) {
                    if (str_starts_with($import, 'ArchitectureLab\\Persistence\\')) {
                        $violations[] = $this->violation(
                            'application-port-only',
                            $file,
                            $import,
                            'Application code must use a port, not a persistence adapter',
                        );
                    }

                    if ($sourceModule !== null && str_starts_with($import, $sourceModule->namespace . '\\Infrastructure\\')) {
                        $violations[] = $this->violation(
                            'application-no-adapter',
                            $file,
                            $import,
                            'Application code must not import its own infrastructure adapter',
                        );
                    }

                    if ($sourceModule !== null && $targetModule !== null && $sourceModule->name !== $targetModule->name) {
                        $violations[] = $this->violation(
                            'application-no-foreign-context',
                            $file,
                            $import,
                            'Application code must reach another context through a port',
                        );
                    }
                }

                if ($sourceModule !== null && $targetModule !== null && $sourceModule->name !== $targetModule->name
                    && !str_starts_with($import, $targetModule->publicApiNamespace)) {
                    $violations[] = $this->violation(
                        'cross-context-public-api',
                        $file,
                        $import,
                        'Cross-context dependencies may use only the target PublicApi namespace',
                    );
                }

                if ($this->isDelivery($file->namespace) && $targetModule !== null
                    && !str_starts_with($import, $targetModule->publicApiNamespace)) {
                    $violations[] = $this->violation(
                        'delivery-public-api',
                        $file,
                        $import,
                        'Delivery code may see PublicApi DTOs, not a context internals',
                    );
                }

                if ($this->isDelivery($file->namespace) && str_starts_with($import, 'ArchitectureLab\\Persistence\\')) {
                    $violations[] = $this->violation(
                        'delivery-no-persistence',
                        $file,
                        $import,
                        'Delivery code must not know the persistence adapter',
                    );
                }
            }
        }

        return $violations;
    }

    /**
     * Baseline-specific checks: the layered code is allowed to be layered, but
     * a presentation class that reaches infrastructure is still a layer skip.
     *
     * @param list<SourceFile> $files
     *
     * @return list<Violation>
     */
    public function checkLayered(array $files): array
    {
        $violations = [];

        foreach ($files as $file) {
            foreach ($file->imports as $import) {
                $sourceLayer = $this->layerOf($file->namespace);
                $targetLayer = $this->layerOf($import);

                if ($sourceLayer === 'Presentation' && $targetLayer === 'Infrastructure') {
                    $violations[] = $this->violation(
                        'layer-skip',
                        $file,
                        $import,
                        'Presentation reaches infrastructure directly, skipping the application layer',
                    );
                }

                if ($sourceLayer === 'Application' && ($import === 'PDO' || $import === 'PDOStatement')) {
                    $violations[] = $this->violation(
                        'framework-in-application',
                        $file,
                        $import,
                        'Application code imports the database driver and cannot be tested without it',
                    );
                }

                if ($sourceLayer === 'Domain'
                    && str_starts_with($import, 'ArchitectureLab\\')
                    && $targetLayer !== 'Domain'
                    && !str_starts_with($import, 'ArchitectureLab\\Shared\\')) {
                    $violations[] = $this->violation(
                        'domain-leak',
                        $file,
                        $import,
                        'The layered domain is not isolated from outer layers',
                    );
                }
            }
        }

        return $violations;
    }

    private function isDelivery(string $namespace): bool
    {
        return $namespace === 'ArchitectureLab\\Integration'
            || str_starts_with($namespace, 'ArchitectureLab\\Integration\\')
            || $namespace === 'ArchitectureLab\\Cli'
            || str_starts_with($namespace, 'ArchitectureLab\\Cli\\');
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

    private function violation(string $rule, SourceFile $file, string $import, string $message): Violation
    {
        return new Violation($rule, $file->primaryClass(), $import, $file->path, $message);
    }
}
