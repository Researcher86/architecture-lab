<?php

declare(strict_types=1);

namespace ArchitectureLab\Tests\Cli;

use ArchitectureLab\Cli\Arguments;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ArgumentsTest extends TestCase
{
    public function testParsesACommandAndOptions(): void
    {
        $arguments = Arguments::parse(['scenario:course-enrollment', '--students=12', '--mode=sync']);

        self::assertSame('scenario:course-enrollment', $arguments->command);
        self::assertSame('sync', $arguments->string('mode', 'event-driven'));
        self::assertTrue($arguments->has('students'));
        self::assertSame(['students' => '12', 'mode' => 'sync'], $arguments->except());
    }

    public function testOptionsCanBeOmitted(): void
    {
        $arguments = Arguments::parse(['demo:smoke']);

        self::assertSame('demo:smoke', $arguments->command);
        self::assertFalse($arguments->has('seed'));
        self::assertSame('default', $arguments->string('seed', 'default'));
    }

    public function testSecondCommandIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Arguments::parse(['first', 'second']);
    }

    public function testUnrecognisedDashArgumentIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Arguments::parse(['--not-an-option']);
    }

    public function testPositiveIntegerIsValidated(): void
    {
        self::assertSame(12, Arguments::parse(['demo', '--students=12'])->positiveInt('students', 1));
        self::assertSame(5, Arguments::parse(['demo'])->positiveInt('students', 5));
    }

    public function testZeroIsRejectedForAPositiveOption(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Arguments::parse(['demo', '--students=0'])->positiveInt('students', 1);
    }

    public function testNonNumericValueIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Arguments::parse(['demo', '--students=many'])->positiveInt('students', 1);
    }

    public function testNonNegativeOptionAcceptsZero(): void
    {
        self::assertSame(0, Arguments::parse(['demo', '--warmups=0'])->nonNegativeInt('warmups', 1));
    }

    public function testExceptRemovesHarnessOptions(): void
    {
        $arguments = Arguments::parse(['demo', '--format=json', '--output=result.json', '--students=3']);

        self::assertSame(
            ['students' => '3'],
            $arguments->except('format', 'output'),
        );
    }
}
