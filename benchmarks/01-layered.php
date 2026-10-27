<?php

declare(strict_types=1);

use ArchitectureLab\Baseline\Layered\Application\LayeredEnrollmentService;
use ArchitectureLab\Baseline\Layered\Infrastructure\LayeredCourseRepository;
use ArchitectureLab\Baseline\Layered\Infrastructure\LayeredEnrollmentRepository;
use ArchitectureLab\Baseline\Layered\Infrastructure\Schema;
use ArchitectureLab\Baseline\Layered\Presentation\LayeredEnrollmentController;
use ArchitectureLab\Benchmark\Benchmark;
use ArchitectureLab\Instrumentation\Counters;

/*
 * Chapter 01: the transaction-script baseline.
 *
 * One operation is one enrollment: check duplicate, open a transaction, read
 * the course, insert the enrollment, increment the seat counter, commit. The
 * report benchmark measures the controller's direct repository call.
 */

$pdo = new \PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
Schema::create($pdo);
$pdo->exec(
    "INSERT INTO layered_courses (id, title, capacity, seats_taken, price_cents)
     VALUES ('arch-101', 'Architecture', 1000000, 0, 12000)",
);
$counters = new Counters();
$layeredCourses = new LayeredCourseRepository($pdo, $counters);
$layeredEnrollments = new LayeredEnrollmentRepository($pdo, $counters);
$layeredService = new LayeredEnrollmentService($pdo, $layeredCourses, $layeredEnrollments, $counters);
$layeredController = new LayeredEnrollmentController($layeredService, $layeredEnrollments);
$layeredStudent = 1;

return [
    new Benchmark('layered.enroll', 200, static function () use ($layeredController, &$layeredStudent): void {
        $layeredController->enroll(sprintf('bench-%06d', $layeredStudent), 'arch-101');
        ++$layeredStudent;
    }),
    new Benchmark('layered.report', 500, static function () use ($layeredController): void {
        $layeredController->report('arch-101');
    }),
];
