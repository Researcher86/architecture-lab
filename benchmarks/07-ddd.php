<?php

declare(strict_types=1);

use ArchitectureLab\Benchmark\Benchmark;
use ArchitectureLab\Runtime\LabRuntime;

/*
 * Chapter 07: cross-context collaboration, two ways.
 *
 * In sync mode Enrollment calls Billing through a PaymentProcessor port while
 * the request is being handled. In event-driven mode the same decision is made
 * by listeners after the request transaction has committed. Both operations
 * enroll 50 students and drain the outbox, so the comparison includes the
 * events each mode produces.
 */

$dddSync = LabRuntime::sqlite(':memory:', 'sync', 0);
$dddSync->publishCourse('arch-101', 'Architecture', 12000, 1000000, '2026-02-01T10:00:00+00:00');
$dddSync->pump();

$dddEventDriven = LabRuntime::sqlite(':memory:', 'event-driven', 0);
$dddEventDriven->publishCourse('arch-101', 'Architecture', 12000, 1000000, '2026-02-01T10:00:00+00:00');
$dddEventDriven->pump();

$dddSyncStudent = 1;
$dddEventDrivenStudent = 1;

return [
    new Benchmark('ddd.sync_contexts', 20, static function () use ($dddSync, &$dddSyncStudent): void {
        for ($index = 0; $index < 50; ++$index) {
            $dddSync->requestEnrollment(sprintf('sync-%06d', $dddSyncStudent), 'arch-101');
            ++$dddSyncStudent;
        }

        $dddSync->pump();
    }),
    new Benchmark('ddd.event_driven_contexts', 20, static function () use ($dddEventDriven, &$dddEventDrivenStudent): void {
        for ($index = 0; $index < 50; ++$index) {
            $dddEventDriven->requestEnrollment(sprintf('async-%06d', $dddEventDrivenStudent), 'arch-101');
            ++$dddEventDrivenStudent;
        }

        $dddEventDriven->pump();
    }),
];
