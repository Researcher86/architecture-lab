<?php

declare(strict_types=1);

use ArchitectureLab\Architecture\ArchitectureAnalyzer;
use ArchitectureLab\Baseline\Layered\Application\LayeredEnrollmentService;
use ArchitectureLab\Baseline\Layered\Infrastructure\LayeredCourseRepository;
use ArchitectureLab\Baseline\Layered\Infrastructure\LayeredEnrollmentRepository;
use ArchitectureLab\Baseline\Layered\Infrastructure\Schema;
use ArchitectureLab\Baseline\Layered\Presentation\LayeredEnrollmentController;
use ArchitectureLab\Experiment\Experiment;
use ArchitectureLab\Experiment\Options;
use ArchitectureLab\Experiment\Output;
use ArchitectureLab\Foundation\ProjectRoot;
use ArchitectureLab\Instrumentation\Counters;

return new Experiment(
    name: 'layered:cost',
    description: 'the transaction-script baseline: SQL in the service, a layer skip, no read model',
    supports: ['students', 'capacity'],
    run: static function (Options $options, Output $output): void {
        $students = $options->students(200);
        $capacity = $options->capacity(max($students, 1));
        $counters = new Counters();
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        Schema::create($pdo);
        $pdo->exec(sprintf(
            "INSERT INTO layered_courses (id, title, capacity, seats_taken, price_cents)
             VALUES ('arch-101', 'Architecture', %d, 0, 12000)",
            $capacity,
        ));

        $courses = new LayeredCourseRepository($pdo, $counters);
        $enrollments = new LayeredEnrollmentRepository($pdo, $counters);
        $service = new LayeredEnrollmentService($pdo, $courses, $enrollments, $counters);
        $controller = new LayeredEnrollmentController($service, $enrollments);

        $counters->reset();
        $start = hrtime(true);

        for ($student = 1; $student <= $students; ++$student) {
            $controller->enroll(sprintf('layered-%03d', $student), 'arch-101');
        }

        $controller->report('arch-101');
        $elapsed = (hrtime(true) - $start) / 1e9;
        $layered = ArchitectureAnalyzer::create()->analyzeLayered(ProjectRoot::path());

        $output->heading('Layered baseline', 'A working flow, measured as the "before" architecture.');
        $output->line(sprintf('students: %d, capacity: %d', $students, $capacity));
        $output->line(sprintf('SQL statements: %d', $counters->sqlQueries));
        $output->line(sprintf('elapsed: %.3f ms', $elapsed * 1000));
        $output->line(sprintf('layer violations: %d', count($layered->violations)));

        foreach ($layered->violations as $violation) {
            $output->line(sprintf('  %-28s %s -> %s', $violation->rule, $violation->fromClass, $violation->toClass));
        }

        $output->measure('students', $students);
        $output->measure('sql_queries', $counters->sqlQueries);
        $output->measure('elapsed_ms', round($elapsed * 1000, 3));
        $output->measure('layer_violations', count($layered->violations));
        $output->measure('files', $layered->fileCount());
        $output->measure('classes', $layered->classCount());
        $output->measure('lines', $layered->lines());
    },
);
