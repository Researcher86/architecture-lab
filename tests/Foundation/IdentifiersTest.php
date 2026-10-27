<?php

declare(strict_types=1);

namespace ArchitectureLab\Tests\Foundation;

use ArchitectureLab\Shared\EnrollmentId;
use ArchitectureLab\Shared\InvoiceId;
use ArchitectureLab\Shared\OfferingId;
use ArchitectureLab\Shared\StudentId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class IdentifiersTest extends TestCase
{
    public function testTypedIdentifiersCompareByValue(): void
    {
        self::assertTrue(new StudentId('s-1')->equals(new StudentId('s-1')));
        self::assertFalse(new StudentId('s-1')->equals(new StudentId('s-2')));
        self::assertTrue(new OfferingId('off-1')->equals(new OfferingId('off-1')));
        self::assertTrue(new EnrollmentId('enr-1')->equals(new EnrollmentId('enr-1')));
        self::assertTrue(new InvoiceId('inv-1')->equals(new InvoiceId('inv-1')));
    }

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function identifierClasses(): iterable
    {
        yield 'student' => [StudentId::class];
        yield 'offering' => [OfferingId::class];
        yield 'enrollment' => [EnrollmentId::class];
        yield 'invoice' => [InvoiceId::class];
    }

    /**
     * @param class-string $class
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('identifierClasses')]
    public function testEmptyIdentifierIsRefused(string $class): void
    {
        $this->expectException(InvalidArgumentException::class);

        new $class('   ');
    }
}
