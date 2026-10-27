<?php

declare(strict_types=1);

namespace ArchitectureLab\Tests\Runtime;

use ArchitectureLab\Enrollment\Domain\EnrollmentStatus;
use ArchitectureLab\Enrollment\PublicApi\Exception\EnrollmentRejected;
use ArchitectureLab\Runtime\LabRuntime;
use PHPUnit\Framework\TestCase;

final class LabRuntimeTest extends TestCase
{
    public function testSyncModeReturnsATerminalReceipt(): void
    {
        $runtime = LabRuntime::sqlite(':memory:', 'sync', 0);
        $runtime->publishCourse('arch-101', 'Architecture', 12000, 4, '2026-02-01T10:00:00+00:00');

        $receipt = $runtime->requestEnrollment('s-1', 'arch-101');

        self::assertSame('confirmed', $receipt->status);
        self::assertTrue($receipt->paid);
        self::assertNotNull($receipt->invoiceId);
        self::assertSame('sync', $runtime->mode());
        self::assertGreaterThan(0, $runtime->pendingEvents());

        $runtime->pump();

        self::assertSame(0, $runtime->pendingEvents());
        self::assertSame(1, $runtime->notifications()->count());
        $schedule = $runtime->studentSchedule('s-1');
        self::assertCount(1, $schedule->entries);
        $entry = $schedule->entries[0] ?? throw new \RuntimeException('Expected one schedule entry');
        self::assertSame('confirmed', $entry->status);
        self::assertSame(1, $runtime->offeringCapacity('arch-101')->confirmed);
    }

    public function testEventDrivenModeIsStaleUntilTheOutboxIsPumped(): void
    {
        $runtime = LabRuntime::sqlite(':memory:', 'event-driven', 0);
        $runtime->publishCourse('arch-101', 'Architecture', 12000, 4, '2026-02-01T10:00:00+00:00');
        $runtime->pump();

        $receipt = $runtime->requestEnrollment('s-1', 'arch-101');

        self::assertSame('pending_payment', $receipt->status);
        self::assertFalse($receipt->paid);
        self::assertSame([], $runtime->studentSchedule('s-1')->entries);
        self::assertSame(0, $runtime->offeringCapacity('arch-101')->confirmed);
        self::assertGreaterThan(0, $runtime->pendingEvents());

        $runtime->pump();
        $schedule = $runtime->studentSchedule('s-1');
        self::assertCount(1, $schedule->entries);
        $entry = $schedule->entries[0] ?? throw new \RuntimeException('Expected one schedule entry');

        self::assertSame('confirmed', $entry->status);
        self::assertSame(1, $runtime->offeringCapacity('arch-101')->confirmed);
        self::assertSame(1, $runtime->notifications()->count());
    }

    public function testEventDrivenFailureCancelsAndReleasesTheSeat(): void
    {
        $runtime = LabRuntime::sqlite(':memory:', 'event-driven', 1);
        $runtime->publishCourse('arch-101', 'Architecture', 12000, 1, '2026-02-01T10:00:00+00:00');
        $runtime->pump();

        $runtime->requestEnrollment('s-1', 'arch-101');
        $runtime->pump();

        $enrollments = $runtime->enrollments()->all();
        self::assertCount(1, $enrollments);
        $enrollment = $enrollments[0] ?? throw new \RuntimeException('Expected one enrollment');

        self::assertSame(EnrollmentStatus::Cancelled, $enrollment->status);
        self::assertSame('card_declined', $enrollment->cancellationReason);
        self::assertSame(0, $runtime->offeringCapacity('arch-101')->reservedSeats);
        self::assertSame(1, $runtime->notifications()->count());
    }

    public function testAFullOfferingRejectsTheRequest(): void
    {
        $runtime = LabRuntime::inMemory('sync', 0);
        $runtime->publishCourse('arch-101', 'Architecture', 12000, 1, '2026-02-01T10:00:00+00:00');
        $runtime->requestEnrollment('s-1', 'arch-101');

        $this->expectException(EnrollmentRejected::class);

        $runtime->requestEnrollment('s-2', 'arch-101');
    }

    public function testQueriesReturnEmptyViewsForUnknownData(): void
    {
        $runtime = LabRuntime::inMemory();

        self::assertSame('missing', $runtime->offeringCapacity('missing')->offeringId);
        self::assertSame(0, $runtime->offeringCapacity('missing')->capacity);
        self::assertSame([], $runtime->studentSchedule('nobody')->entries);
    }

    public function testInMemoryAndSqliteAdaptersMakeTheSameDecision(): void
    {
        $memory = LabRuntime::inMemory('event-driven', 3);
        $sqlite = LabRuntime::sqlite(':memory:', 'event-driven', 3);

        foreach ([$memory, $sqlite] as $runtime) {
            $runtime->publishCourse('arch-101', 'Architecture', 12000, 4, '2026-02-01T10:00:00+00:00');
            $runtime->pump();

            for ($student = 1; $student <= 4; ++$student) {
                $runtime->requestEnrollment(sprintf('s-%d', $student), 'arch-101');
            }

            $runtime->pump();
        }

        self::assertSame(
            array_map(static fn ($view): string => $view->status->value, $memory->enrollments()->all()),
            array_map(static fn ($view): string => $view->status->value, $sqlite->enrollments()->all()),
        );
        self::assertSame($memory->notifications()->count(), $sqlite->notifications()->count());
        self::assertSame($memory->offeringCapacity('arch-101')->toArray(), $sqlite->offeringCapacity('arch-101')->toArray());
    }

    public function testPumpIsIdempotentWhenNothingIsPending(): void
    {
        $runtime = LabRuntime::inMemory();
        $report = $runtime->pump();

        self::assertSame(0, $report->delivered);
        self::assertSame(0, $report->failed);
        self::assertSame(0, $runtime->deadLettered());
    }
}
