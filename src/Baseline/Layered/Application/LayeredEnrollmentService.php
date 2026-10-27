<?php

declare(strict_types=1);

namespace ArchitectureLab\Baseline\Layered\Application;

use ArchitectureLab\Baseline\Layered\Domain\CourseFull;
use ArchitectureLab\Baseline\Layered\Domain\DuplicateEnrollment;
use ArchitectureLab\Baseline\Layered\Infrastructure\LayeredCourseRepository;
use ArchitectureLab\Baseline\Layered\Infrastructure\LayeredEnrollmentRepository;
use ArchitectureLab\Instrumentation\Counters;
use DateTimeImmutable;
use PDO;
use RuntimeException;
use Throwable;

/**
 * The transaction-script version of enrollment.
 *
 * This is the baseline the later chapters refactor. It has a service layer on
 * paper, but the service imports PDO, owns the transaction, knows the schema
 * shape, and calls two concrete repositories. It cannot run without SQLite.
 */
final readonly class LayeredEnrollmentService
{
    public function __construct(
        private PDO $pdo,
        private LayeredCourseRepository $courses,
        private LayeredEnrollmentRepository $enrollments,
        private Counters $counters,
    ) {
    }

    /**
     * @return array{id: int, student_id: string, course_id: string, status: string, amount_cents: int}
     */
    public function enroll(string $studentId, string $courseId): array
    {
        if ($this->enrollments->exists($studentId, $courseId)) {
            throw new DuplicateEnrollment(sprintf('%s already enrolled in %s', $studentId, $courseId));
        }

        $this->pdo->beginTransaction();
        ++$this->counters->sqlQueries;

        try {
            $course = $this->courses->find($courseId);

            if ($course === null) {
                throw new RuntimeException(sprintf('Unknown course: %s', $courseId));
            }

            if ($course['seats_taken'] >= $course['capacity']) {
                throw new CourseFull(sprintf('Course %s is full', $courseId));
            }

            $id = $this->enrollments->insert([
                'student_id' => $studentId,
                'course_id' => $courseId,
                'course_title' => $course['title'],
                'status' => 'confirmed',
                'paid' => 1,
                'amount_cents' => $course['price_cents'],
                'created_at' => new DateTimeImmutable()->format(DATE_ATOM),
            ]);
            $this->courses->incrementSeats($courseId);
            $this->pdo->commit();
            ++$this->counters->sqlQueries;

            return [
                'id' => $id,
                'student_id' => $studentId,
                'course_id' => $courseId,
                'status' => 'confirmed',
                'amount_cents' => $course['price_cents'],
            ];
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            ++$this->counters->sqlQueries;

            throw $exception;
        }
    }
}
