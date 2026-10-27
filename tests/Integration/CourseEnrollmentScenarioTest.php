<?php

declare(strict_types=1);

namespace ArchitectureLab\Tests\Integration;

use ArchitectureLab\Integration\CourseEnrollmentScenario;
use ArchitectureLab\Integration\ScenarioOptions;
use ArchitectureLab\Runtime\LabRuntime;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CourseEnrollmentScenarioTest extends TestCase
{
    public function testEventDrivenScenarioConvergesWithTheDocumentedNumbers(): void
    {
        $options = new ScenarioOptions();
        $runtime = LabRuntime::sqlite(':memory:', $options->mode, $options->failEvery);
        $report = new CourseEnrollmentScenario($runtime)->run($options);

        self::assertTrue($report->ok(), implode('; ', $report->failures()));
        self::assertSame(8, $report->numbers['accepted']);
        self::assertSame(4, $report->numbers['rejected_by_capacity']);
        self::assertSame(6, $report->numbers['write_confirmed']);
        self::assertSame(2, $report->numbers['write_cancelled']);
        self::assertSame(0, $report->numbers['write_pending']);
        self::assertSame(8, $report->numbers['notifications']);
        self::assertSame(6, $report->numbers['read_model']['confirmed']);
        self::assertSame(0, $report->numbers['stale_projection_entries']);
        self::assertSame(0, $report->numbers['pending_events_after_pump']);
    }

    public function testSyncScenarioReusesSeatsReleasedByFailedPayments(): void
    {
        $options = new ScenarioOptions(mode: 'sync');
        $runtime = LabRuntime::sqlite(':memory:', $options->mode, $options->failEvery);
        $report = new CourseEnrollmentScenario($runtime)->run($options);

        self::assertTrue($report->ok(), implode('; ', $report->failures()));
        self::assertSame(10, $report->numbers['accepted']);
        self::assertSame(2, $report->numbers['rejected_by_capacity']);
        self::assertSame(8, $report->numbers['write_confirmed']);
        self::assertSame(2, $report->numbers['write_cancelled']);
        self::assertSame(10, $report->numbers['notifications']);
    }

    public function testScenarioWithNoPaymentFailures(): void
    {
        $options = new ScenarioOptions(students: 6, offerings: 2, capacity: 3, failEvery: 0);
        $runtime = LabRuntime::sqlite(':memory:', $options->mode, $options->failEvery);
        $report = new CourseEnrollmentScenario($runtime)->run($options);

        self::assertTrue($report->ok(), implode('; ', $report->failures()));
        self::assertSame(6, $report->numbers['accepted']);
        self::assertSame(0, $report->numbers['write_cancelled']);
        self::assertSame(6, $report->numbers['notifications']);
    }

    public function testScenarioReportSerializes(): void
    {
        $options = new ScenarioOptions(students: 2, offerings: 1, capacity: 2, failEvery: 0);
        $runtime = LabRuntime::inMemory('event-driven', 0);
        $report = new CourseEnrollmentScenario($runtime)->run($options);
        $array = $report->toArray();

        self::assertSame('event-driven', $array['mode']);
        self::assertSame('architecture-lab', $array['seed']);
        self::assertTrue($array['ok']);
        self::assertArrayHasKey('read_model', $array['numbers']);
        self::assertArrayHasKey('write-converged', $array['checks']);
    }

    public function testZeroStudentsExplainsTheProblem(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('students must be positive');

        new ScenarioOptions(students: 0);
    }

    public function testZeroOfferingsExplainsTheProblem(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('offerings must be positive');

        new ScenarioOptions(offerings: 0);
    }

    public function testZeroCapacityExplainsTheProblem(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('capacity must be positive');

        new ScenarioOptions(capacity: 0);
    }

    public function testNegativeFailEveryExplainsTheProblem(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('failEvery cannot be negative');

        new ScenarioOptions(failEvery: -2);
    }
}
