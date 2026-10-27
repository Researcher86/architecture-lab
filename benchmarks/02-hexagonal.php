<?php

declare(strict_types=1);

use ArchitectureLab\Benchmark\Benchmark;
use ArchitectureLab\Runtime\LabRuntime;

/*
 * Chapter 02: the same enrollment use case behind two adapters.
 *
 * Both runtimes receive the same command and the same synchronous payment
 * result. The only difference is the DocumentStore implementation behind the
 * port: PHP arrays or SQLite.
 */

$hexagonalMemory = LabRuntime::inMemory('sync', 0);
$hexagonalSqlite = LabRuntime::sqlite(':memory:', 'sync', 0);

foreach ([$hexagonalMemory, $hexagonalSqlite] as $runtime) {
    $runtime->publishCourse('arch-101', 'Architecture', 12000, 1000000, '2026-02-01T10:00:00+00:00');
    $runtime->pump();
}

$hexagonalMemoryStudent = 1;
$hexagonalSqliteStudent = 1;

return [
    new Benchmark('hexagonal.in_memory', 200, static function () use ($hexagonalMemory, &$hexagonalMemoryStudent): void {
        $hexagonalMemory->requestEnrollment(sprintf('mem-%06d', $hexagonalMemoryStudent), 'arch-101');
        ++$hexagonalMemoryStudent;
    }),
    new Benchmark('hexagonal.sqlite', 200, static function () use ($hexagonalSqlite, &$hexagonalSqliteStudent): void {
        $hexagonalSqlite->requestEnrollment(sprintf('sql-%06d', $hexagonalSqliteStudent), 'arch-101');
        ++$hexagonalSqliteStudent;
    }),
];
