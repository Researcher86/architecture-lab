<?php

declare(strict_types=1);

namespace ArchitectureLab\Tests\Catalog;

use ArchitectureLab\Catalog\Domain\CourseOffering;
use ArchitectureLab\Catalog\Domain\SeatsExhausted;
use ArchitectureLab\Shared\Money;
use ArchitectureLab\Shared\OfferingId;
use DateTimeImmutable;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;

final class CourseOfferingTest extends TestCase
{
    public function testReservesAndReleasesSeats(): void
    {
        $offering = $this->offering(capacity: 2);

        self::assertSame(2, $offering->seatsLeft());

        $offering->reserveSeat();
        $offering->reserveSeat();

        self::assertSame(2, $offering->reservedSeats());
        self::assertSame(0, $offering->seatsLeft());

        $offering->releaseSeat();

        self::assertSame(1, $offering->reservedSeats());
        self::assertSame(1, $offering->seatsLeft());
    }

    public function testReservingBeyondCapacityIsRefused(): void
    {
        $offering = $this->offering(capacity: 1);
        $offering->reserveSeat();

        $this->expectException(SeatsExhausted::class);

        $offering->reserveSeat();
    }

    public function testReleasingAnUnreservedSeatIsRefused(): void
    {
        $this->expectException(LogicException::class);

        $this->offering()->releaseSeat();
    }

    public function testCapacityMustBePositive(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->offering(capacity: 0);
    }

    public function testTitleCannotBeEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CourseOffering(
            new OfferingId('arch-101'),
            ' ',
            new Money(12000),
            new DateTimeImmutable('2026-02-01T10:00:00+00:00'),
            4,
        );
    }

    public function testExposesItsValueObjects(): void
    {
        $offering = $this->offering();

        self::assertSame('arch-101', $offering->id()->value);
        self::assertSame('Architecture', $offering->title());
        self::assertSame(12000, $offering->price()->cents);
        self::assertSame(4, $offering->capacity());
        self::assertSame('2026-02-01T10:00:00+00:00', $offering->startsAt()->format(DATE_ATOM));
    }

    private function offering(int $capacity = 4): CourseOffering
    {
        return new CourseOffering(
            new OfferingId('arch-101'),
            'Architecture',
            new Money(12000),
            new DateTimeImmutable('2026-02-01T10:00:00+00:00'),
            $capacity,
        );
    }
}
