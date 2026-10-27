<?php

declare(strict_types=1);

namespace ArchitectureLab\Reporting\PublicApi;

final readonly class StudentScheduleView
{
    /**
     * @param list<ScheduleEntry> $entries
     */
    public function __construct(
        public string $studentId,
        public array $entries,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'student_id' => $this->studentId,
            'entries' => array_map(
                static fn (ScheduleEntry $entry): array => [
                    'enrollment_id' => $entry->enrollmentId,
                    'offering_id' => $entry->offeringId,
                    'course_title' => $entry->courseTitle,
                    'status' => $entry->status,
                ],
                $this->entries,
            ),
        ];
    }
}
