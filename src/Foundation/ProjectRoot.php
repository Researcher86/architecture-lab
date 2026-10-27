<?php

declare(strict_types=1);

namespace ArchitectureLab\Foundation;

use RuntimeException;

/**
 * Resolves the repository root for command-line experiments and benchmarks.
 *
 * getcwd() is typed as string|false because the working directory can be
 * removed underneath a running process. Failing loudly here keeps that
 * edge case out of every experiment.
 */
final class ProjectRoot
{
    public static function path(): string
    {
        $path = getcwd();

        if ($path === false) {
            throw new RuntimeException('Cannot determine the current working directory.');
        }

        return $path;
    }
}
