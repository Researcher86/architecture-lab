<?php

declare(strict_types=1);

namespace ArchitectureLab\Instrumentation;

use InvalidArgumentException;

/**
 * The operation counts a chapter reports beside its timings.
 *
 * Time says how fast this machine ran. These counters say what the algorithm
 * actually did, which is what a complexity claim is about. They are public
 * integers so a hot loop can increment them without a method call; reset()
 * delimits one measured operation.
 */
final class Counters
{
    public int $storeReads = 0;
    public int $storeWrites = 0;
    public int $sqlQueries = 0;
    public int $repositoryCalls = 0;
    public int $eventsPublished = 0;
    public int $eventHandlers = 0;
    public int $projectionUpdates = 0;
    public int $handlerSkips = 0;
    public int $aclTranslations = 0;
    public int $moduleCalls = 0;

    public function reset(): void
    {
        $this->storeReads = 0;
        $this->storeWrites = 0;
        $this->sqlQueries = 0;
        $this->repositoryCalls = 0;
        $this->eventsPublished = 0;
        $this->eventHandlers = 0;
        $this->projectionUpdates = 0;
        $this->handlerSkips = 0;
        $this->aclTranslations = 0;
        $this->moduleCalls = 0;
    }

    public function add(string $name, int $amount = 1): void
    {
        match ($name) {
            'storeReads' => $this->storeReads += $amount,
            'storeWrites' => $this->storeWrites += $amount,
            'sqlQueries' => $this->sqlQueries += $amount,
            'repositoryCalls' => $this->repositoryCalls += $amount,
            'eventsPublished' => $this->eventsPublished += $amount,
            'eventHandlers' => $this->eventHandlers += $amount,
            'projectionUpdates' => $this->projectionUpdates += $amount,
            'handlerSkips' => $this->handlerSkips += $amount,
            'aclTranslations' => $this->aclTranslations += $amount,
            'moduleCalls' => $this->moduleCalls += $amount,
            default => throw new InvalidArgumentException(sprintf('Unknown counter: %s', $name)),
        };
    }

    /**
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return [
            'storeReads' => $this->storeReads,
            'storeWrites' => $this->storeWrites,
            'sqlQueries' => $this->sqlQueries,
            'repositoryCalls' => $this->repositoryCalls,
            'eventsPublished' => $this->eventsPublished,
            'eventHandlers' => $this->eventHandlers,
            'projectionUpdates' => $this->projectionUpdates,
            'handlerSkips' => $this->handlerSkips,
            'aclTranslations' => $this->aclTranslations,
            'moduleCalls' => $this->moduleCalls,
        ];
    }
}
