<?php

declare(strict_types=1);

namespace ArchitectureLab\Cqrs;

use Closure;
use LogicException;

/** The read-side counterpart of CommandBus. */
final class QueryBus
{
    /** @var array<class-string, Closure(object): mixed> */
    private array $handlers = [];

    /**
     * @param class-string $query
     * @param callable(object): mixed $handler
     */
    public function register(string $query, callable $handler): void
    {
        $this->handlers[$query] = Closure::fromCallable($handler);
    }

    public function ask(object $query): mixed
    {
        $class = $query::class;
        $handler = $this->handlers[$class] ?? null;

        if ($handler === null) {
            throw new LogicException(sprintf('No handler registered for %s', $class));
        }

        return $handler($query);
    }
}
