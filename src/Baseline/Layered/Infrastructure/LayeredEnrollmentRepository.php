<?php

declare(strict_types=1);

namespace ArchitectureLab\Baseline\Layered\Infrastructure;

use ArchitectureLab\Instrumentation\Counters;
use PDO;

final readonly class LayeredEnrollmentRepository
{
    public function __construct(
        private PDO $pdo,
        private Counters $counters,
    ) {
    }

    public function exists(string $studentId, string $courseId): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT 1 FROM layered_enrollments WHERE student_id = :student AND course_id = :course',
        );
        $statement->execute(['student' => $studentId, 'course' => $courseId]);
        $exists = $statement->fetchColumn() !== false;
        ++$this->counters->sqlQueries;

        return $exists;
    }

    /**
     * @param array{student_id: string, course_id: string, course_title: string, status: string, paid: int, amount_cents: int, created_at: string} $row
     */
    public function insert(array $row): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO layered_enrollments
                (student_id, course_id, course_title, status, paid, amount_cents, created_at)
             VALUES
                (:student_id, :course_id, :course_title, :status, :paid, :amount_cents, :created_at)',
        );
        $statement->execute($row);
        ++$this->counters->sqlQueries;

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forCourse(string $courseId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM layered_enrollments WHERE course_id = :course ORDER BY id',
        );
        $statement->execute(['course' => $courseId]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        ++$this->counters->sqlQueries;

        /** @var list<array<string, mixed>> $rows */
        return $rows;
    }
}
