<?php

declare(strict_types=1);

namespace ArchitectureLab\Tests\Cqrs;

use ArchitectureLab\Cqrs\CommandBus;
use ArchitectureLab\Cqrs\QueryBus;
use LogicException;
use PHPUnit\Framework\TestCase;

final class BusTest extends TestCase
{
    public function testCommandBusDispatchesToTheRegisteredHandler(): void
    {
        $bus = new CommandBus();
        $bus->register(\stdClass::class, static fn (object $command): string => 'handled');

        self::assertSame('handled', $bus->dispatch(new \stdClass()));
    }

    public function testCommandBusRefusesAnUnknownCommand(): void
    {
        $this->expectException(LogicException::class);

        new CommandBus()->dispatch(new \stdClass());
    }

    public function testAHandlerCanBeReplaced(): void
    {
        $bus = new CommandBus();
        $bus->register(\stdClass::class, static fn (): string => 'first');
        $bus->register(\stdClass::class, static fn (): string => 'second');

        self::assertSame('second', $bus->dispatch(new \stdClass()));
    }

    public function testQueryBusDispatchesToTheRegisteredHandler(): void
    {
        $bus = new QueryBus();
        $bus->register(\stdClass::class, static fn (): int => 42);

        self::assertSame(42, $bus->ask(new \stdClass()));
    }

    public function testQueryBusRefusesAnUnknownQuery(): void
    {
        $this->expectException(LogicException::class);

        new QueryBus()->ask(new \stdClass());
    }
}
