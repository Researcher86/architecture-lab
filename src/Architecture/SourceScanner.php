<?php

declare(strict_types=1);

namespace ArchitectureLab\Architecture;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use UnexpectedValueException;

/**
 * Reads just enough PHP to build a dependency graph.
 *
 * A full AST parser would be more precise, but this lab only needs top-level
 * namespace/use/class declarations. Keeping the scanner dependency-free makes
 * the architecture check itself part of the measured code.
 */
final class SourceScanner
{
    /**
     * @return list<SourceFile>
     */
    public function scan(string $directory, ?string $base = null): array
    {
        $base ??= $directory;
        $files = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $path = $file->getPathname();
            $relative = ltrim(str_replace($base, '', $path), DIRECTORY_SEPARATOR);
            $source = file_get_contents($path);

            if ($source === false) {
                throw new UnexpectedValueException(sprintf('Unable to read %s', $path));
            }

            $files[] = $this->parse($relative, $source);
        }

        usort(
            $files,
            static fn (SourceFile $left, SourceFile $right): int => $left->path <=> $right->path,
        );

        return $files;
    }

    public function parse(string $path, string $source): SourceFile
    {
        $tokens = token_get_all($source);
        $namespace = '';
        $imports = [];
        $classes = [];
        $depth = 0;
        $abstractPending = false;
        $count = count($tokens);

        for ($index = 0; $index < $count; ++$index) {
            $token = $tokens[$index];

            if (is_string($token)) {
                if ($token === '{') {
                    ++$depth;
                } elseif ($token === '}') {
                    --$depth;
                }

                continue;
            }

            if ($token[0] === T_NAMESPACE) {
                [$namespace, $index] = $this->readNamespace($tokens, $index + 1);

                continue;
            }

            if ($token[0] === T_ABSTRACT) {
                $abstractPending = true;

                continue;
            }

            if ($token[0] === T_USE && $depth === 0) {
                [$statement, $index] = $this->readStatement($tokens, $index + 1);
                $statement = trim($statement);

                if ($statement === '' || str_starts_with($statement, 'function ') || str_starts_with($statement, 'const ')) {
                    continue;
                }

                foreach (explode(',', $statement) as $import) {
                    $import = trim($import);
                    $aliasPosition = stripos($import, ' as ');

                    if ($aliasPosition !== false) {
                        $import = substr($import, 0, $aliasPosition);
                    }

                    $import = ltrim(trim($import), '\\');

                    if ($import !== '') {
                        $imports[] = $import;
                    }
                }

                continue;
            }

            if (!in_array($token[0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)) {
                continue;
            }

            $previous = $this->previousMeaningful($tokens, $index);

            if ($token[0] === T_CLASS && in_array($previous, [T_NEW, T_DOUBLE_COLON], true)) {
                continue;
            }

            $name = $this->nextIdentifier($tokens, $index + 1);

            if ($name === null) {
                continue;
            }

            $kind = match ($token[0]) {
                T_INTERFACE => ClassKind::Interface,
                T_TRAIT => ClassKind::Trait,
                T_ENUM => ClassKind::Enum,
                default => ClassKind::ClassName,
            };
            $classes[] = new ClassInfo(
                name: ltrim($namespace . '\\' . $name, '\\'),
                kind: $kind,
                abstract: $kind === ClassKind::Interface || $abstractPending,
                line: (int) $token[2],
            );
            $abstractPending = false;
        }

        return new SourceFile(
            path: $path,
            namespace: $namespace,
            imports: array_values(array_unique($imports)),
            classes: $classes,
            lines: substr_count($source, "\n") + 1,
        );
    }

    /**
     * @param list<array{int, string, int}|string> $tokens
     *
     * @return array{string, int}
     */
    private function readNamespace(array $tokens, int $index): array
    {
        [$statement, $index] = $this->readStatement($tokens, $index);

        return [trim($statement, " \t\n\r\0\x0B\\{"), $index];
    }

    /**
     * @param list<array{int, string, int}|string> $tokens
     *
     * @return array{string, int}
     */
    private function readStatement(array $tokens, int $index): array
    {
        $statement = '';
        $count = count($tokens);

        for (; $index < $count; ++$index) {
            $token = $tokens[$index];

            if (is_string($token)) {
                if ($token === ';' || $token === '{') {
                    return [$statement, $index];
                }

                $statement .= $token;

                continue;
            }

            if ($token[0] === T_WHITESPACE) {
                $statement .= ' ';

                continue;
            }

            $statement .= $token[1];
        }

        return [$statement, $index];
    }

    /**
     * @param list<array{int, string, int}|string> $tokens
     */
    private function previousMeaningful(array $tokens, int $index): ?int
    {
        for ($cursor = $index - 1; $cursor >= 0; --$cursor) {
            $token = $tokens[$cursor];

            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return is_array($token) ? $token[0] : null;
        }

        return null;
    }

    /**
     * @param list<array{int, string, int}|string> $tokens
     */
    private function nextIdentifier(array $tokens, int $index): ?string
    {
        $count = count($tokens);

        for (; $index < $count; ++$index) {
            $token = $tokens[$index];

            if (is_array($token) && $token[0] === T_STRING) {
                return $token[1];
            }

            if (!is_array($token) || !in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                return null;
            }
        }

        return null;
    }
}
