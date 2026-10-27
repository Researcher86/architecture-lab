<?php

declare(strict_types=1);

namespace ArchitectureLab\Enrollment\PublicApi;

use ArchitectureLab\Enrollment\Application\Port\EnrollmentRepository;
use ArchitectureLab\Enrollment\PublicApi\Exception\EnrollmentNotFound;
use ArchitectureLab\Shared\EnrollmentId;
use ArchitectureLab\Shared\StudentId;

final readonly class EnrollmentQueryService implements EnrollmentApi
{
    public function __construct(
        private EnrollmentRepository $enrollments,
    ) {
    }

    public function enrollment(EnrollmentId $id): EnrollmentView
    {
        $enrollment = $this->enrollments->get($id);

        if ($enrollment === null) {
            throw new EnrollmentNotFound(sprintf('Unknown enrollment: %s', $id->value));
        }

        return EnrollmentView::fromDomain($enrollment);
    }

    public function all(): array
    {
        return array_map(
            static fn ($enrollment): EnrollmentView => EnrollmentView::fromDomain($enrollment),
            $this->enrollments->all(),
        );
    }

    public function forStudent(StudentId $studentId): array
    {
        return array_map(
            static fn ($enrollment): EnrollmentView => EnrollmentView::fromDomain($enrollment),
            $this->enrollments->forStudent($studentId),
        );
    }
}
