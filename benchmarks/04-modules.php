<?php

declare(strict_types=1);

use ArchitectureLab\Benchmark\Benchmark;
use ArchitectureLab\Catalog\PublicApi\Command\PublishCourse;
use ArchitectureLab\Runtime\LabRuntime;
use ArchitectureLab\Shared\OfferingId;

/*
 * Chapter 04: what a module boundary costs at runtime.
 *
 * `modules.public_api` reads through CatalogApi: repository call, hydration
 * into the aggregate, and a DTO mapping. `modules.store_read` reads the same
 * document directly, which the application is not allowed to do. The
 * difference is the price of the boundary, not an argument for removing it.
 */

$modules = LabRuntime::inMemory('sync', 0);
$modules->publishCourse('arch-101', 'Architecture', 12000, 1000000, '2026-02-01T10:00:00+00:00');
$modules->pump();
$modulesOffering = new OfferingId('arch-101');
$modulesPublication = 1;

return [
    new Benchmark('modules.public_api', 1000, static function () use ($modules, $modulesOffering): void {
        $modules->catalog()->offering($modulesOffering);
    }),
    new Benchmark('modules.store_read', 1000, static function () use ($modules): void {
        $modules->store()->get('catalog_course_offerings', 'arch-101');
    }),
    new Benchmark('modules.command_bus', 200, static function () use ($modules, &$modulesPublication): void {
        $modules->commands()->dispatch(new PublishCourse(
            offeringId: sprintf('bench-off-%06d', $modulesPublication),
            title: 'Benchmark offering',
            priceCents: 12000,
            capacity: 10,
            startsAt: '2026-02-01T10:00:00+00:00',
        ));
        ++$modulesPublication;
    }),
];
