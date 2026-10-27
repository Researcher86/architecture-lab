<?php

declare(strict_types=1);

namespace ArchitectureLab\Enrollment\PublicApi;

use ArchitectureLab\Shared\EnrollmentId;
use ArchitectureLab\Shared\StudentId;

/** Write-side inspection without exposing the aggregate to delivery code. */
interface EnrollmentApi
{
    public function enrollment(EnrollmentId $id): EnrollmentView;

    /**
     * @return list<EnrollmentView>
     */
    public function all(): array;

    /**
     * @return list<EnrollmentView>
     */
    public function forStudent(StudentId $studentId): array;
}
