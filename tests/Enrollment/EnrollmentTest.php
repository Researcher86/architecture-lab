<?php

declare(strict_types=1);

namespace ArchitectureLab\Tests\Enrollment;

use ArchitectureLab\Enrollment\Domain\Enrollment;
use ArchitectureLab\Enrollment\Domain\EnrollmentStatus;
use ArchitectureLab\Shared\EnrollmentId;
use ArchitectureLab\Shared\Money;
use ArchitectureLab\Shared\OfferingId;
use ArchitectureLab\Shared\StudentId;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class EnrollmentTest extends TestCase
{
    public function testStartsPendingPayment(): void
    {
        $enrollment = $this->enrollment();

        self::assertSame(EnrollmentStatus::PendingPayment, $enrollment->status());
        self::assertFalse($enrollment->status()->isTerminal());
        self::assertNull($enrollment->decidedAt());
        self::assertNull($enrollment->cancellationReason());
    }

    public function testConfirmsAPendingEnrollment(): void
    {
        $enrollment = $this->enrollment();
        $decidedAt = new DateTimeImmutable('2026-02-01T11:00:00+00:00');

        $enrollment->confirm($decidedAt);

        self::assertSame(EnrollmentStatus::Confirmed, $enrollment->status());
        self::assertTrue($enrollment->status()->isTerminal());
        self::assertSame($decidedAt, $enrollment->decidedAt());
    }

    public function testCancelsAPendingEnrollmentWithAReason(): void
    {
        $enrollment = $this->enrollment();
        $decidedAt = new DateTimeImmutable('2026-02-01T11:00:00+00:00');

        $enrollment->cancel('card_declined', $decidedAt);

        self::assertSame(EnrollmentStatus::Cancelled, $enrollment->status());
        self::assertSame('card_declined', $enrollment->cancellationReason());
        self::assertSame($decidedAt, $enrollment->decidedAt());
    }

    public function testCannotConfirmTwice(): void
    {
        $enrollment = $this->enrollment();
        $enrollment->confirm(new DateTimeImmutable());

        $this->expectException(DomainException::class);

        $enrollment->confirm(new DateTimeImmutable());
    }

    public function testCannotCancelAConfirmedEnrollment(): void
    {
        $enrollment = $this->enrollment();
        $enrollment->confirm(new DateTimeImmutable());

        $this->expectException(DomainException::class);

        $enrollment->cancel('late', new DateTimeImmutable());
    }

    public function testTitleCannotBeEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Enrollment(
            new EnrollmentId('enr-1'),
            new StudentId('s-1'),
            new OfferingId('arch-101'),
            ' ',
            new Money(12000),
            new DateTimeImmutable('2026-02-01T10:00:00+00:00'),
        );
    }

    public function testExposesItsIdentityAndPrice(): void
    {
        $enrollment = $this->enrollment();

        self::assertSame('enr-1', $enrollment->id()->value);
        self::assertSame('s-1', $enrollment->studentId()->value);
        self::assertSame('arch-101', $enrollment->offeringId()->value);
        self::assertSame('Architecture', $enrollment->courseTitle());
        self::assertSame(12000, $enrollment->price()->cents);
        self::assertSame('2026-02-01T10:00:00+00:00', $enrollment->requestedAt()->format(DATE_ATOM));
    }

    private function enrollment(): Enrollment
    {
        return new Enrollment(
            new EnrollmentId('enr-1'),
            new StudentId('s-1'),
            new OfferingId('arch-101'),
            'Architecture',
            new Money(12000),
            new DateTimeImmutable('2026-02-01T10:00:00+00:00'),
        );
    }
}
