<?php

declare(strict_types=1);

namespace ArchitectureLab\Tests\Baseline;

use ArchitectureLab\Baseline\Layered\Application\LayeredEnrollmentService;
use ArchitectureLab\Baseline\Layered\Domain\CourseFull;
use ArchitectureLab\Baseline\Layered\Domain\DuplicateEnrollment;
use ArchitectureLab\Baseline\Layered\Infrastructure\LayeredCourseRepository;
use ArchitectureLab\Baseline\Layered\Infrastructure\LayeredEnrollmentRepository;
use ArchitectureLab\Baseline\Layered\Infrastructure\Schema;
use ArchitectureLab\Baseline\Layered\Presentation\LayeredEnrollmentController;
use ArchitectureLab\Instrumentation\Counters;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class LayeredBaselineTest extends TestCase
{
    private PDO $pdo;

    private Counters $counters;

    private LayeredEnrollmentController $controller;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        Schema::create($this->pdo);
        $this->pdo->exec(
            "INSERT INTO layered_courses (id, title, capacity, seats_taken, price_cents)
             VALUES ('arch-101', 'Architecture', 2, 0, 12000)",
        );
        $this->counters = new Counters();
        $courses = new LayeredCourseRepository($this->pdo, $this->counters);
        $enrollments = new LayeredEnrollmentRepository($this->pdo, $this->counters);
        $service = new LayeredEnrollmentService($this->pdo, $courses, $enrollments, $this->counters);
        $this->controller = new LayeredEnrollmentController($service, $enrollments);
    }

    public function testEnrollsAStudentAndUpdatesTheSeatCount(): void
    {
        $result = $this->controller->enroll('s-1', 'arch-101');

        self::assertSame('confirmed', $result['status']);
        self::assertSame(12000, $result['amount_cents']);

        $course = $this->seatCount();
        self::assertSame(1, $course);
        self::assertCount(1, $this->controller->report('arch-101'));
    }

    public function testDuplicateEnrollmentIsRefused(): void
    {
        $this->controller->enroll('s-1', 'arch-101');

        $this->expectException(DuplicateEnrollment::class);

        $this->controller->enroll('s-1', 'arch-101');
    }

    public function testFullCourseIsRefusedAndTransactionRollsBack(): void
    {
        $this->controller->enroll('s-1', 'arch-101');
        $this->controller->enroll('s-2', 'arch-101');

        try {
            $this->controller->enroll('s-3', 'arch-101');
            self::fail('The third student should not fit');
        } catch (CourseFull) {
            self::assertCount(2, $this->controller->report('arch-101'));
            self::assertFalse($this->pdo->inTransaction());
            self::assertSame(2, $this->seatCount());
        }
    }

    public function testUnknownCourseRollsBack(): void
    {
        $this->expectException(RuntimeException::class);

        $this->controller->enroll('s-1', 'missing');
    }

    public function testReportUsesTheRepositoryDirectly(): void
    {
        $this->controller->enroll('s-1', 'arch-101');
        $this->controller->enroll('s-2', 'arch-101');

        $report = $this->controller->report('arch-101');

        self::assertSame(['s-1', 's-2'], array_map(static fn (array $row): string => (string) $row['student_id'], $report));
    }

    private function seatCount(): int
    {
        $statement = $this->pdo->query("SELECT seats_taken FROM layered_courses WHERE id = 'arch-101'");

        if ($statement === false) {
            throw new RuntimeException('Unable to read the seat counter');
        }

        return (int) $statement->fetchColumn();
    }
}
