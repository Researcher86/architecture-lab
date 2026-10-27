<?php

declare(strict_types=1);

use ArchitectureLab\Experiment\Experiment;
use ArchitectureLab\Experiment\Options;
use ArchitectureLab\Experiment\Output;
use ArchitectureLab\Foundation\ProjectRoot;
use ArchitectureLab\Runtime\LabRuntime;

return new Experiment(
    name: 'hexagonal:adapter-swap',
    description: 'the same enrollment use case behind an in-memory and a SQLite adapter',
    supports: ['students', 'capacity', 'fail-every'],
    run: static function (Options $options, Output $output): void {
        $students = $options->students(100);
        $capacity = $options->capacity(max($students, 1));
        $failEvery = $options->failEvery(0);

        $memory = LabRuntime::inMemory('sync', $failEvery);
        $sqlite = LabRuntime::sqlite(':memory:', 'sync', $failEvery);
        $runEnrollment = static function (LabRuntime $runtime, int $students, int $capacity): array {
            $runtime->publishCourse('arch-101', 'Architecture', 12000, $capacity, '2026-02-01T10:00:00+00:00');
            $start = hrtime(true);

            for ($student = 1; $student <= $students; ++$student) {
                $runtime->requestEnrollment(sprintf('hex-%03d', $student), 'arch-101');
            }

            $elapsed = (hrtime(true) - $start) / 1e9;
            $runtime->pump();
            $confirmed = 0;

            foreach ($runtime->enrollments()->all() as $enrollment) {
                if ($enrollment->status === ArchitectureLab\Enrollment\Domain\EnrollmentStatus::Confirmed) {
                    ++$confirmed;
                }
            }

            return ['confirmed' => $confirmed, 'elapsed_ms' => round($elapsed * 1000, 3)];
        };
        $memoryOutcome = $runEnrollment($memory, $students, $capacity);
        $sqliteOutcome = $runEnrollment($sqlite, $students, $capacity);

        $output->heading('Ports and adapters', 'The application sees the same port; only the adapter changes.');
        $output->line(sprintf('students: %d, capacity: %d', $students, $capacity));
        $output->line(sprintf(
            'in-memory: %.3f ms, SQL statements %d, confirmed %d',
            $memoryOutcome['elapsed_ms'],
            $memory->counters()->sqlQueries,
            $memoryOutcome['confirmed'],
        ));
        $output->line(sprintf(
            'SQLite:    %.3f ms, SQL statements %d, confirmed %d',
            $sqliteOutcome['elapsed_ms'],
            $sqlite->counters()->sqlQueries,
            $sqliteOutcome['confirmed'],
        ));
        $output->line(sprintf('same decisions: %s', $memoryOutcome['confirmed'] === $sqliteOutcome['confirmed'] ? 'yes' : 'no'));

        $scanner = new ArchitectureLab\Architecture\SourceScanner();
        $root = ProjectRoot::path();
        $files = $scanner->scan($root . '/src', $root);
        $ports = 0;
        $adapters = 0;

        foreach ($files as $file) {
            if (str_contains($file->namespace, '\\Application\\Port') || str_contains($file->namespace, '\\Shared\\Port')) {
                ++$ports;
            }

            if (str_contains($file->namespace, '\\Infrastructure') || str_starts_with($file->namespace, 'ArchitectureLab\\Persistence')) {
                ++$adapters;
            }
        }

        $output->line(sprintf('ports: %d, adapter classes: %d', $ports, $adapters));
        $output->measure('students', $students);
        $output->measure('in_memory_ms', $memoryOutcome['elapsed_ms']);
        $output->measure('sqlite_ms', $sqliteOutcome['elapsed_ms']);
        $output->measure('in_memory_sql_queries', $memory->counters()->sqlQueries);
        $output->measure('sqlite_sql_queries', $sqlite->counters()->sqlQueries);
        $output->measure('same_decisions', $memoryOutcome['confirmed'] === $sqliteOutcome['confirmed']);
        $output->measure('ports', $ports);
        $output->measure('adapters', $adapters);
    },
);
