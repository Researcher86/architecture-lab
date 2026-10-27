<?php

declare(strict_types=1);

use ArchitectureLab\Benchmark\Benchmark;
use ArchitectureLab\Runtime\LabRuntime;
use ArchitectureLab\Shared\StudentId;

/*
 * Chapter 05: one projection lookup versus a write-side scan.
 *
 * The read model is a single document keyed by student. The write side has no
 * per-student index in this lab: `forStudent` reads the whole collection and
 * filters it. That is exactly the kind of query a read model exists to avoid.
 * This benchmark measures the local in-process cost; a real database would
 * add a query planner and I/O to both sides.
 */

$cqrs = LabRuntime::sqlite(':memory:', 'sync', 0);
$cqrsStudents = 500;
$cqrs->publishCourse('arch-101', 'Architecture', 12000, $cqrsStudents, '2026-02-01T10:00:00+00:00');

for ($student = 1; $student <= $cqrsStudents; ++$student) {
    $cqrs->requestEnrollment(sprintf('cqrs-%04d', $student), 'arch-101');
}

$cqrs->pump();
$cqrsStudent = new StudentId('cqrs-0250');

return [
    new Benchmark('cqrs.read_model', 100, static function () use ($cqrs): void {
        count($cqrs->studentSchedule('cqrs-0250')->entries);
    }),
    new Benchmark('cqrs.write_scan', 100, static function () use ($cqrs, $cqrsStudent): void {
        count($cqrs->enrollments()->forStudent($cqrsStudent));
    }),
];
