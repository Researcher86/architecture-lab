<?php

declare(strict_types=1);

namespace ArchitectureLab\Reporting\Application;

use ArchitectureLab\Reporting\Projection\StudentScheduleProjection;
use ArchitectureLab\Reporting\PublicApi\Query\StudentScheduleQuery;
use ArchitectureLab\Reporting\PublicApi\ScheduleEntry;
use ArchitectureLab\Reporting\PublicApi\StudentScheduleView;
use ArchitectureLab\Shared\Port\DocumentStore;

final readonly class StudentScheduleQueryHandler
{
    public function __construct(
        private DocumentStore $store,
    ) {
    }

    public function handle(StudentScheduleQuery $query): StudentScheduleView
    {
        $document = $this->store->get(StudentScheduleProjection::COLLECTION, $query->studentId) ?? [];
        /** @var array<string, array<string, string>> $raw */
        $raw = is_array($document['entries'] ?? null) ? $document['entries'] : [];
        $entries = [];

        foreach ($raw as $entry) {
            $entries[] = new ScheduleEntry(
                enrollmentId: (string) $entry['enrollment_id'],
                offeringId: (string) $entry['offering_id'],
                courseTitle: (string) $entry['course_title'],
                status: (string) $entry['status'],
            );
        }

        usort(
            $entries,
            static fn (ScheduleEntry $left, ScheduleEntry $right): int => $left->enrollmentId <=> $right->enrollmentId,
        );

        return new StudentScheduleView((string) ($document['student_id'] ?? $query->studentId), $entries);
    }
}
