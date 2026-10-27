<?php

declare(strict_types=1);

namespace ArchitectureLab\Cqrs;

use Closure;
use LogicException;

/**
 * One handler per command class.
 *
 * The bus is intentionally tiny: its job is to make the write/read split
 * explicit and to keep delivery code from constructing application services.
 */
final class CommandBus
{
    /** @var array<class-string, Closure(object): mixed> */
    private array $handlers = [];

    /**
     * @param class-string $command
     * @param callable(object): mixed $handler
     */
    public function register(string $command, callable $handler): void
    {
        $this->handlers[$command] = Closure::fromCallable($handler);
    }

    public function dispatch(object $command): mixed
    {
        $class = $command::class;
        $handler = $this->handlers[$class] ?? null;

        if ($handler === null) {
            throw new LogicException(sprintf('No handler registered for %s', $class));
        }

        return $handler($command);
    }
}
