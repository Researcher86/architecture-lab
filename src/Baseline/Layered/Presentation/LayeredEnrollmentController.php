<?php

declare(strict_types=1);

namespace ArchitectureLab\Baseline\Layered\Presentation;

use ArchitectureLab\Baseline\Layered\Application\LayeredEnrollmentService;
use ArchitectureLab\Baseline\Layered\Infrastructure\LayeredEnrollmentRepository;

/**
 * The presentation layer.
 *
 * `report()` is the intentional layer skip: the controller talks to a
 * repository directly because the service had no method for it. It is a small
 * shortcut with a large consequence - the UI now knows the data layer.
 */
final readonly class LayeredEnrollmentController
{
    public function __construct(
        private LayeredEnrollmentService $service,
        private LayeredEnrollmentRepository $enrollments,
    ) {
    }

    /**
     * @return array{id: int, student_id: string, course_id: string, status: string, amount_cents: int}
     */
    public function enroll(string $studentId, string $courseId): array
    {
        return $this->service->enroll($studentId, $courseId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function report(string $courseId): array
    {
        return $this->enrollments->forCourse($courseId);
    }
}
