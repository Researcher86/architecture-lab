<?php

declare(strict_types=1);

namespace ArchitectureLab\Enrollment\Application\Port;

use ArchitectureLab\Enrollment\Domain\Enrollment;
use ArchitectureLab\Shared\EnrollmentId;
use ArchitectureLab\Shared\StudentId;

interface EnrollmentRepository
{
    public function save(Enrollment $enrollment): void;

    public function get(EnrollmentId $id): ?Enrollment;

    /**
     * @return list<Enrollment>
     */
    public function all(): array;

    /**
     * @return list<Enrollment>
     */
    public function forStudent(StudentId $studentId): array;
}
