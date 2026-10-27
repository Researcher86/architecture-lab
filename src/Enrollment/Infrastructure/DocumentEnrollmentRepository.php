<?php

declare(strict_types=1);

namespace ArchitectureLab\Enrollment\Infrastructure;

use ArchitectureLab\Enrollment\Application\Port\EnrollmentRepository;
use ArchitectureLab\Enrollment\Domain\Enrollment;
use ArchitectureLab\Enrollment\Domain\EnrollmentStatus;
use ArchitectureLab\Instrumentation\Counters;
use ArchitectureLab\Shared\EnrollmentId;
use ArchitectureLab\Shared\Money;
use ArchitectureLab\Shared\OfferingId;
use ArchitectureLab\Shared\Port\DocumentStore;
use ArchitectureLab\Shared\StudentId;
use DateTimeImmutable;

final readonly class DocumentEnrollmentRepository implements EnrollmentRepository
{
    private const COLLECTION = 'enrollment_enrollments';

    public function __construct(
        private DocumentStore $store,
        private Counters $counters,
    ) {
    }

    public function save(Enrollment $enrollment): void
    {
        $this->store->put(self::COLLECTION, $enrollment->id()->value, [
            'id' => $enrollment->id()->value,
            'student_id' => $enrollment->studentId()->value,
            'offering_id' => $enrollment->offeringId()->value,
            'course_title' => $enrollment->courseTitle(),
            'price_cents' => $enrollment->price()->cents,
            'currency' => $enrollment->price()->currency,
            'requested_at' => $enrollment->requestedAt()->format(DATE_ATOM),
            'status' => $enrollment->status()->value,
            'cancellation_reason' => $enrollment->cancellationReason(),
            'decided_at' => $enrollment->decidedAt()?->format(DATE_ATOM),
        ]);
        ++$this->counters->repositoryCalls;
    }

    public function get(EnrollmentId $id): ?Enrollment
    {
        $document = $this->store->get(self::COLLECTION, $id->value);
        ++$this->counters->repositoryCalls;

        return $document === null ? null : $this->hydrate($document);
    }

    public function all(): array
    {
        return $this->hydrateAll($this->store->all(self::COLLECTION));
    }

    public function forStudent(StudentId $studentId): array
    {
        $documents = array_values(array_filter(
            $this->store->all(self::COLLECTION),
            static fn (array $document): bool => ($document['student_id'] ?? null) === $studentId->value,
        ));

        return $this->hydrateAll($documents);
    }

    /**
     * @param list<array<string, mixed>> $documents
     *
     * @return list<Enrollment>
     */
    private function hydrateAll(array $documents): array
    {
        $enrollments = [];

        foreach ($documents as $document) {
            $enrollments[] = $this->hydrate($document);
        }

        ++$this->counters->repositoryCalls;

        return $enrollments;
    }

    /**
     * @param array<string, mixed> $document
     */
    private function hydrate(array $document): Enrollment
    {
        $enrollment = new Enrollment(
            new EnrollmentId((string) $document['id']),
            new StudentId((string) $document['student_id']),
            new OfferingId((string) $document['offering_id']),
            (string) $document['course_title'],
            new Money((int) $document['price_cents'], (string) $document['currency']),
            new DateTimeImmutable((string) $document['requested_at']),
        );

        $status = EnrollmentStatus::from((string) $document['status']);
        $decidedAt = $document['decided_at'] === null ? null : new DateTimeImmutable((string) $document['decided_at']);

        if ($status === EnrollmentStatus::Confirmed && $decidedAt !== null) {
            $enrollment->confirm($decidedAt);
        }

        if ($status === EnrollmentStatus::Cancelled && $decidedAt !== null) {
            $enrollment->cancel((string) ($document['cancellation_reason'] ?? 'unknown'), $decidedAt);
        }

        return $enrollment;
    }
}
