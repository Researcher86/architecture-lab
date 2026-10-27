<?php

declare(strict_types=1);

namespace ArchitectureLab\Reporting\Projection;

use ArchitectureLab\Eventing\EventEnvelope;
use ArchitectureLab\Eventing\EventListener;
use ArchitectureLab\Eventing\EventNames;
use ArchitectureLab\Instrumentation\Counters;
use ArchitectureLab\Shared\Port\DocumentStore;

/** One document per student with a map of visible enrollment entries. */
final class StudentScheduleProjection implements EventListener
{
    public const COLLECTION = 'reporting_student_schedule';

    public function __construct(
        private readonly DocumentStore $store,
        private readonly Counters $counters,
    ) {
    }

    public function handle(EventEnvelope $event): void
    {
        if (!in_array($event->name, [
            EventNames::ENROLLMENT_REQUESTED,
            EventNames::ENROLLMENT_CONFIRMED,
            EventNames::ENROLLMENT_CANCELLED,
        ], true)) {
            return;
        }

        $studentId = (string) $event->payload['student_id'];
        $enrollmentId = (string) $event->payload['enrollment_id'];
        $document = $this->store->get(self::COLLECTION, $studentId) ?? [
            'student_id' => $studentId,
            'entries' => [],
        ];
        /** @var array<string, array<string, string>> $entries */
        $entries = is_array($document['entries'] ?? null) ? $document['entries'] : [];
        $statuses = [
            EventNames::ENROLLMENT_REQUESTED => 'pending_payment',
            EventNames::ENROLLMENT_CONFIRMED => 'confirmed',
            EventNames::ENROLLMENT_CANCELLED => 'cancelled',
        ];
        $status = $statuses[$event->name];
        $entries[$enrollmentId] = [
            'enrollment_id' => $enrollmentId,
            'offering_id' => (string) $event->payload['offering_id'],
            'course_title' => (string) $event->payload['course_title'],
            'status' => $status,
        ];
        $document['entries'] = $entries;
        $this->store->put(self::COLLECTION, $studentId, $document);
        ++$this->counters->projectionUpdates;
    }
}
