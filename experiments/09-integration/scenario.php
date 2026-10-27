<?php

declare(strict_types=1);

use ArchitectureLab\Experiment\Experiment;
use ArchitectureLab\Experiment\Options;
use ArchitectureLab\Experiment\Output;
use ArchitectureLab\Integration\CourseEnrollmentScenario;
use ArchitectureLab\Integration\ScenarioOptions;
use ArchitectureLab\Runtime\LabRuntime;

return new Experiment(
    name: 'scenario:course-enrollment',
    description: 'the MVP: catalog, enrollment, billing, outbox, projections and notifications',
    supports: ['students', 'offerings', 'capacity', 'fail-every', 'mode', 'seed'],
    run: static function (Options $options, Output $output): void {
        $scenarioOptions = new ScenarioOptions(
            students: $options->students(12),
            offerings: $options->offerings(2),
            capacity: $options->capacity(4),
            failEvery: $options->failEvery(4),
            seed: $options->seed('architecture-lab'),
            mode: $options->mode('event-driven'),
        );
        $runtime = LabRuntime::sqlite(':memory:', $scenarioOptions->mode, $scenarioOptions->failEvery);
        $report = new CourseEnrollmentScenario($runtime)->run($scenarioOptions);
        $numbers = $report->numbers;

        $output->heading('Course enrollment scenario', 'One deterministic flow through every chapter.');
        $output->line(sprintf(
            'mode: %s, students: %d, offerings: %d, capacity each: %d, fail every: %d',
            $report->mode,
            $scenarioOptions->students,
            $scenarioOptions->offerings,
            $scenarioOptions->capacity,
            $scenarioOptions->failEvery,
        ));
        $output->line(sprintf(
            'requests: accepted %d, rejected by capacity %d',
            $numbers['accepted'],
            $numbers['rejected_by_capacity'],
        ));
        $output->line(sprintf(
            'write model: confirmed %d, cancelled %d, pending %d',
            $numbers['write_confirmed'],
            $numbers['write_cancelled'],
            $numbers['write_pending'],
        ));
        $output->line(sprintf(
            'outbox: pending before pump %d, delivered %d, failed %d, dead-lettered %d, pending after %d',
            $numbers['pending_events_before_pump'],
            $numbers['events_delivered'],
            $numbers['events_failed'],
            $numbers['events_dead_lettered'],
            $numbers['pending_events_after_pump'],
        ));
        $output->line(sprintf(
            'read model: reserved %d, confirmed %d, cancelled %d, pending %d, entries %d',
            $numbers['read_model']['reserved_seats'],
            $numbers['read_model']['confirmed'],
            $numbers['read_model']['cancelled'],
            $numbers['read_model']['pending'],
            $numbers['read_model']['projection_entries'],
        ));
        $output->line(sprintf(
            'notifications: %d, stale entries before delivery: %d',
            $numbers['notifications'],
            $numbers['stale_projection_entries'],
        ));
        $output->line();
        $output->line('Invariants:');

        foreach ($report->checks as $name => $check) {
            $output->line(sprintf('  %-24s %s  %s', $name, $check['ok'] ? 'ok' : 'FAIL', $check['detail']));
        }

        $output->measure('mode', $report->mode);
        $output->measure('seed', $report->seed);
        $output->measure('accepted', $numbers['accepted']);
        $output->measure('rejected_by_capacity', $numbers['rejected_by_capacity']);
        $output->measure('write_confirmed', $numbers['write_confirmed']);
        $output->measure('write_cancelled', $numbers['write_cancelled']);
        $output->measure('pending_events_before_pump', $numbers['pending_events_before_pump']);
        $output->measure('events_delivered', $numbers['events_delivered']);
        $output->measure('stale_projection_entries', $numbers['stale_projection_entries']);
        $output->measure('notifications', $numbers['notifications']);
        $output->measure('read_model_confirmed', $numbers['read_model']['confirmed']);
        $output->measure('sql_queries', $numbers['counters']['sqlQueries']);
        $output->measure('ok', $report->ok());

        $artifacts = dirname(__DIR__, 2) . '/var/results';
        if (is_dir($artifacts)) {
            file_put_contents(
                $artifacts . '/architecture-lab-scenario.txt',
                $output->measurements() === [] ? '' : json_encode($report->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
            );
        }

        if (!$report->ok()) {
            throw new RuntimeException('Scenario invariants failed: ' . implode('; ', $report->failures()));
        }
    },
);
