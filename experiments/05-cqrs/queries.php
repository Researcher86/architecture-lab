<?php

declare(strict_types=1);

use ArchitectureLab\Enrollment\Domain\EnrollmentStatus;
use ArchitectureLab\Experiment\Experiment;
use ArchitectureLab\Experiment\Options;
use ArchitectureLab\Experiment\Output;
use ArchitectureLab\Runtime\LabRuntime;
use ArchitectureLab\Shared\StudentId;

return new Experiment(
    name: 'cqrs:queries',
    description: 'a projection lookup against a write-side scan, including the stale window',
    supports: ['students', 'capacity'],
    run: static function (Options $options, Output $output): void {
        $students = $options->students(100);
        $capacity = $options->capacity(max($students, 1));
        $runtime = LabRuntime::sqlite(':memory:', 'sync', 0);
        $runtime->publishCourse('arch-101', 'Architecture', 12000, $capacity, '2026-02-01T10:00:00+00:00');

        for ($student = 1; $student <= $students; ++$student) {
            $runtime->requestEnrollment(sprintf('cqrs-%03d', $student), 'arch-101');
        }

        $pendingBefore = $runtime->pendingEvents();
        $stale = $runtime->offeringCapacity('arch-101');
        $runtime->pump();

        $runtime->counters()->reset();
        $readStart = hrtime(true);
        $readEntries = 0;

        for ($round = 0; $round < 5; ++$round) {
            for ($student = 1; $student <= $students; ++$student) {
                $readEntries += count($runtime->studentSchedule(sprintf('cqrs-%03d', $student))->entries);
            }

            $runtime->offeringCapacity('arch-101');
        }

        $readElapsed = (hrtime(true) - $readStart) / 1e9;
        $readReads = $runtime->counters()->storeReads;
        $runtime->counters()->reset();
        $writeStart = hrtime(true);
        $writeEntries = 0;

        for ($round = 0; $round < 5; ++$round) {
            for ($student = 1; $student <= $students; ++$student) {
                $writeEntries += count($runtime->enrollments()->forStudent(new StudentId(sprintf('cqrs-%03d', $student))));
            }

            $runtime->catalog()->offering(new ArchitectureLab\Shared\OfferingId('arch-101'));
        }

        $writeElapsed = (hrtime(true) - $writeStart) / 1e9;
        $writeReads = $runtime->counters()->storeReads;
        $confirmed = 0;

        foreach ($runtime->enrollments()->all() as $enrollment) {
            if ($enrollment->status === EnrollmentStatus::Confirmed) {
                ++$confirmed;
            }
        }

        $output->heading('CQRS read models', 'One projection lookup versus scanning the write side.');
        $output->line(sprintf('students: %d, capacity: %d', $students, $capacity));
        $output->line(sprintf('before delivery: %d pending events, projection confirmed=%d', $pendingBefore, $stale->confirmed));
        $output->line(sprintf('after delivery: pending=%d, projection confirmed=%d, write confirmed=%d', $runtime->pendingEvents(), $runtime->offeringCapacity('arch-101')->confirmed, $confirmed));
        $output->line(sprintf('read model:  %.3f ms, %d store reads, %d entries', $readElapsed * 1000, $readReads, $readEntries));
        $output->line(sprintf('write model: %.3f ms, %d store reads, %d entries', $writeElapsed * 1000, $writeReads, $writeEntries));
        $output->line(sprintf('speedup: %.2fx', $readElapsed > 0 ? $writeElapsed / $readElapsed : 0));
        $output->measure('students', $students);
        $output->measure('pending_before', $pendingBefore);
        $output->measure('stale_confirmed', $stale->confirmed);
        $output->measure('read_model_ms', round($readElapsed * 1000, 3));
        $output->measure('write_model_ms', round($writeElapsed * 1000, 3));
        $output->measure('read_store_reads', $readReads);
        $output->measure('write_store_reads', $writeReads);
        $output->measure('converged', $runtime->offeringCapacity('arch-101')->confirmed === $confirmed);
    },
);
