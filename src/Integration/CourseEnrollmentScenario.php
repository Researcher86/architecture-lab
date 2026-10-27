<?php

declare(strict_types=1);

namespace ArchitectureLab\Integration;

use ArchitectureLab\Catalog\PublicApi\Command\PublishCourse;
use ArchitectureLab\Enrollment\PublicApi\Command\RequestEnrollment;
use ArchitectureLab\Enrollment\PublicApi\Exception\EnrollmentRejected;
use ArchitectureLab\Runtime\LabRuntime;

/**
 * The one end-to-end scenario.
 *
 * It publishes offerings, issues more requests than the seats can satisfy,
 * leaves events in the outbox on purpose, observes the stale read model, then
 * pumps delivery and checks that the projections and the write model converge.
 */
final readonly class CourseEnrollmentScenario
{
    public function __construct(
        private LabRuntime $runtime,
    ) {
    }

    public function run(ScenarioOptions $options): ScenarioReport
    {
        $offerings = $this->publish($options);
        $this->runtime->pump();

        $accepted = 0;
        $rejected = 0;

        for ($student = 1; $student <= $options->students; ++$student) {
            $studentId = sprintf('s-%02d', $student);
            $offeringId = $offerings[($student - 1) % count($offerings)];

            try {
                $this->runtime->commands()->dispatch(new RequestEnrollment($studentId, $offeringId));
                ++$accepted;
            } catch (EnrollmentRejected) {
                ++$rejected;
            }
        }

        $pendingBefore = $this->runtime->pendingEvents();
        $stale = $this->snapshot($offerings, $options);

        $delivery = $this->runtime->pump();
        $final = $this->snapshot($offerings, $options);
        $write = $this->writeCounts();

        $checks = $this->checks($stale, $final, $write, $accepted, $rejected);

        return new ScenarioReport(
            mode: $options->mode,
            seed: $options->seed,
            numbers: [
                'students' => $options->students,
                'offerings' => $options->offerings,
                'capacity_per_offering' => $options->capacity,
                'fail_every' => $options->failEvery,
                'accepted' => $accepted,
                'rejected_by_capacity' => $rejected,
                'write_confirmed' => $write['confirmed'],
                'write_cancelled' => $write['cancelled'],
                'write_pending' => $write['pending'],
                'pending_events_before_pump' => $pendingBefore,
                'events_delivered' => $delivery->delivered,
                'events_failed' => $delivery->failed,
                'events_dead_lettered' => $delivery->deadLettered,
                'pending_events_after_pump' => $this->runtime->pendingEvents(),
                'stale_projection_entries' => $stale['projection_entries'],
                'notifications' => $this->runtime->notifications()->count(),
                'read_model' => $final,
                'counters' => $this->runtime->counters()->toArray(),
            ],
            checks: $checks,
        );
    }

    /**
     * @return list<string>
     */
    private function publish(ScenarioOptions $options): array
    {
        $offerings = [];

        for ($index = 1; $index <= $options->offerings; ++$index) {
            $offeringId = sprintf('off-%d', $index);
            $offerings[] = $offeringId;
            $this->runtime->commands()->dispatch(new PublishCourse(
                offeringId: $offeringId,
                title: sprintf('Architecture Module %d', $index),
                priceCents: 12000 + ($index - 1) * 3000,
                capacity: $options->capacity,
                startsAt: $options->startsAt,
            ));
        }

        return $offerings;
    }

    /**
     * @param list<string> $offerings
     *
     * @return array<string, int>
     */
    private function snapshot(array $offerings, ScenarioOptions $options): array
    {
        $reserved = 0;
        $pending = 0;
        $confirmed = 0;
        $cancelled = 0;
        $entries = 0;

        foreach ($offerings as $offeringId) {
            $capacity = $this->runtime->offeringCapacity($offeringId);
            $reserved += $capacity->reservedSeats;
            $pending += $capacity->pending;
            $confirmed += $capacity->confirmed;
            $cancelled += $capacity->cancelled;
        }

        for ($student = 1; $student <= $options->students; ++$student) {
            $entries += count($this->runtime->studentSchedule(sprintf('s-%02d', $student))->entries);
        }

        return [
            'reserved_seats' => $reserved,
            'pending' => $pending,
            'confirmed' => $confirmed,
            'cancelled' => $cancelled,
            'projection_entries' => $entries,
        ];
    }

    /**
     * @return array{confirmed: int, cancelled: int, pending: int}
     */
    private function writeCounts(): array
    {
        $counts = ['confirmed' => 0, 'cancelled' => 0, 'pending' => 0];

        foreach ($this->runtime->enrollments()->all() as $enrollment) {
            match ($enrollment->status->value) {
                'confirmed' => ++$counts['confirmed'],
                'cancelled' => ++$counts['cancelled'],
                'pending_payment' => ++$counts['pending'],
            };
        }

        return $counts;
    }

    /**
     * @param array<string, int> $stale
     * @param array<string, int> $final
     * @param array{confirmed: int, cancelled: int, pending: int} $write
     *
     * @return array<string, array{ok: bool, detail: string}>
     */
    private function checks(array $stale, array $final, array $write, int $accepted, int $rejected): array
    {
        $catalogReserved = 0;

        foreach ($this->runtime->catalog()->offerings() as $offering) {
            $catalogReserved += $offering->reservedSeats;
        }

        return [
            'stale-before-pump' => [
                'ok' => $stale['projection_entries'] === 0,
                'detail' => sprintf('read model had %d entries before delivery', $stale['projection_entries']),
            ],
            'no-pending-events' => [
                'ok' => $this->runtime->pendingEvents() === 0,
                'detail' => sprintf('%d events are still pending', $this->runtime->pendingEvents()),
            ],
            'no-dead-letters' => [
                'ok' => $this->runtime->deadLettered() === 0,
                'detail' => sprintf('%d events are dead-lettered', $this->runtime->deadLettered()),
            ],
            'write-converged' => [
                'ok' => $write['pending'] === 0,
                'detail' => sprintf('%d enrollments are still pending payment', $write['pending']),
            ],
            'read-model-confirmed' => [
                'ok' => $final['confirmed'] === $write['confirmed'],
                'detail' => sprintf('read model %d vs write model %d', $final['confirmed'], $write['confirmed']),
            ],
            'read-model-cancelled' => [
                'ok' => $final['cancelled'] === $write['cancelled'],
                'detail' => sprintf('read model %d vs write model %d', $final['cancelled'], $write['cancelled']),
            ],
            'capacity-conserved' => [
                'ok' => $final['reserved_seats'] === $write['confirmed'] + $write['pending'],
                'detail' => sprintf('read model reserved %d vs write reserved %d', $final['reserved_seats'], $write['confirmed'] + $write['pending']),
            ],
            'catalog-conserved' => [
                'ok' => $catalogReserved === $write['confirmed'] + $write['pending'],
                'detail' => sprintf('catalog reserved %d vs write reserved %d', $catalogReserved, $write['confirmed'] + $write['pending']),
            ],
            'no-oversubscription' => [
                'ok' => $final['reserved_seats'] <= count($this->runtime->catalog()->offerings()) * $this->capacityOfFirstOffering(),
                'detail' => sprintf('%d seats reserved, total capacity %d', $final['reserved_seats'], count($this->runtime->catalog()->offerings()) * $this->capacityOfFirstOffering()),
            ],
            'notifications-match' => [
                'ok' => $this->runtime->notifications()->count() === $write['confirmed'] + $write['cancelled'],
                'detail' => sprintf('notifications %d vs terminal enrollments %d', $this->runtime->notifications()->count(), $write['confirmed'] + $write['cancelled']),
            ],
            'requests-accounted' => [
                'ok' => $accepted === count($this->runtime->enrollments()->all()),
                'detail' => sprintf('%d accepted vs %d stored enrollments; %d rejected', $accepted, count($this->runtime->enrollments()->all()), $rejected),
            ],
        ];
    }

    private function capacityOfFirstOffering(): int
    {
        $offerings = $this->runtime->catalog()->offerings();

        return $offerings === [] ? 0 : $offerings[0]->capacity;
    }
}
