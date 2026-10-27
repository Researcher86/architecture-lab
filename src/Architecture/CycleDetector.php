<?php

declare(strict_types=1);

namespace ArchitectureLab\Architecture;

/** Finds strongly connected components with more than one module. */
final class CycleDetector
{
    /**
     * @param array<string, list<string>> $adjacency
     *
     * @return list<list<string>>
     */
    public function find(array $adjacency): array
    {
        $index = 0;
        $indices = [];
        $lowLinks = [];
        $stack = [];
        $onStack = [];
        $components = [];

        $strongConnect = function (string $node) use (
            &$strongConnect,
            &$index,
            &$indices,
            &$lowLinks,
            &$stack,
            &$onStack,
            &$components,
            $adjacency,
        ): void {
            $indices[$node] = $index;
            $lowLinks[$node] = $index;
            ++$index;
            $stack[] = $node;
            $onStack[$node] = true;

            foreach ($adjacency[$node] ?? [] as $target) {
                if (!isset($indices[$target])) {
                    $strongConnect($target);
                    $lowLinks[$node] = min($lowLinks[$node], $lowLinks[$target]);
                } elseif ($onStack[$target] ?? false) {
                    $lowLinks[$node] = min($lowLinks[$node], $indices[$target]);
                }
            }

            if ($lowLinks[$node] !== $indices[$node]) {
                return;
            }

            $component = [];

            do {
                $member = array_pop($stack);

                if ($member === null) {
                    break;
                }

                $onStack[$member] = false;
                $component[] = $member;
            } while ($member !== $node);

            if (count($component) > 1) {
                sort($component);
                $components[] = $component;
            }
        };

        foreach (array_keys($adjacency) as $node) {
            if (!isset($indices[$node])) {
                $strongConnect($node);
            }
        }

        return $components;
    }
}
