<?php

declare(strict_types=1);

namespace ArchitectureLab\Tests\Foundation;

use ArchitectureLab\Foundation\ByteFormatter;
use ArchitectureLab\Foundation\DeterministicRandom;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class FoundationHelpersTest extends TestCase
{
    public function testSameSeedProducesTheSameSequence(): void
    {
        $first = new DeterministicRandom('book');
        $second = new DeterministicRandom('book');

        self::assertSame(
            [$first->nextInt(), $first->nextInt(), $first->nextInt()],
            [$second->nextInt(), $second->nextInt(), $second->nextInt()],
        );
    }

    public function testDifferentSeedsProduceDifferentSequences(): void
    {
        self::assertNotSame(
            new DeterministicRandom('left')->nextInt(),
            new DeterministicRandom('right')->nextInt(),
        );
    }

    public function testIntegersStayInsideTheRequestedRange(): void
    {
        $random = new DeterministicRandom('range');

        for ($index = 0; $index < 100; ++$index) {
            $value = $random->intBetween(10, 12);
            self::assertGreaterThanOrEqual(10, $value);
            self::assertLessThanOrEqual(12, $value);
        }
    }

    public function testFloatsStayBelowOne(): void
    {
        $random = new DeterministicRandom('float');

        for ($index = 0; $index < 100; ++$index) {
            $value = $random->nextFloat();
            self::assertGreaterThanOrEqual(0.0, $value);
            self::assertLessThan(1.0, $value);
        }
    }

    public function testInvalidRangeIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DeterministicRandom('invalid')->intBetween(5, 4);
    }

    public function testFormatsBytesWithBinaryUnits(): void
    {
        self::assertSame('0 B', ByteFormatter::format(0));
        self::assertSame('1023 B', ByteFormatter::format(1023));
        self::assertSame('1.0 KiB', ByteFormatter::format(1024));
        self::assertSame('1.5 MiB', ByteFormatter::format(1024 * 1024 + 512 * 1024));
        self::assertSame('+1.0 KiB', ByteFormatter::formatSigned(1024));
        self::assertSame('-1.0 KiB', ByteFormatter::formatSigned(-1024));
    }
}
