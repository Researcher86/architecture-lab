<?php

declare(strict_types=1);

namespace ArchitectureLab\Tests\Experiment;

use ArchitectureLab\Experiment\Options;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class OptionsTest extends TestCase
{
    public function testParsesKnownOptions(): void
    {
        $options = Options::fromStrings([
            'students' => '12',
            'capacity' => '4',
            'fail-every' => '0',
            'mode' => 'sync',
            'seed' => 'book',
        ]);

        self::assertSame(12, $options->students(1));
        self::assertSame(4, $options->capacity(1));
        self::assertSame(0, $options->failEvery(3));
        self::assertSame('sync', $options->mode('event-driven'));
        self::assertSame('book', $options->seed('default'));
        self::assertSame(
            ['students' => 12, 'capacity' => 4, 'fail-every' => 0, 'mode' => 'sync', 'seed' => 'book'],
            $options->toArray(),
        );
        self::assertSame(['students', 'capacity', 'fail-every', 'mode', 'seed'], $options->names());
    }

    public function testUsesDefaultsWhenAnOptionIsMissing(): void
    {
        $options = Options::none();

        self::assertSame(10, $options->students(10));
        self::assertSame('event-driven', $options->mode('event-driven'));
        self::assertSame(0, $options->failEvery(0));
        self::assertSame([], $options->toArray());
    }

    public function testUnknownOptionIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown option --threads');

        Options::fromStrings(['threads' => '4']);
    }

    public function testPositiveOptionRejectsZero(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Options::fromStrings(['students' => '0']);
    }

    public function testPositiveOptionRejectsANonNumber(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Options::fromStrings(['students' => 'twelve']);
    }

    public function testFailEveryAllowsZero(): void
    {
        self::assertSame(0, Options::fromStrings(['fail-every' => '0'])->failEvery(5));
    }

    public function testFailEveryRejectsNegative(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Options::fromStrings(['fail-every' => '-1']);
    }

    public function testModeIsClosed(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Options::fromStrings(['mode' => 'async']);
    }

    public function testSeedCannotBeEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Options::fromStrings(['seed' => '  ']);
    }
}
