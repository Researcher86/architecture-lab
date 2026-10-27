<?php

declare(strict_types=1);

namespace ArchitectureLab\Tests\Experiment;

use ArchitectureLab\Experiment\ExperimentRunner;
use ArchitectureLab\Experiment\Options;
use ArchitectureLab\Experiment\Output;
use ArchitectureLab\Experiment\Registry;
use ArchitectureLab\Foundation\ProjectRoot;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ExperimentHarnessTest extends TestCase
{
    public function testRegistryDiscoversEveryExperiment(): void
    {
        $registry = $this->registry();

        self::assertCount(9, $registry->all());
        self::assertTrue($registry->has('demo:smoke'));
        self::assertTrue($registry->has('scenario:course-enrollment'));
        self::assertSame('demo:smoke', $registry->get('demo:smoke')->name);
    }

    public function testUnknownExperimentIsRefused(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->registry()->get('missing:experiment');
    }

    public function testRunnerExecutesAnExperimentAndCollectsMeasurements(): void
    {
        $stream = fopen('php://temp', 'r+');
        self::assertIsResource($stream);
        $output = new Output($stream, quiet: true);
        $result = new ExperimentRunner($this->registry())->run('demo:smoke', Options::none(), $output);

        self::assertSame('demo:smoke', $result->name);
        self::assertSame(PHP_VERSION, $result->measurements['php_version']);
        self::assertSame(42, $result->measurements['demo_value']);
        self::assertGreaterThanOrEqual(0.0, $result->elapsedSeconds);
        self::assertEquals(
            ['experiment' => 'demo:smoke', 'description' => $result->description, 'options' => (object) [], 'measurements' => (object) $result->measurements, 'elapsed_seconds' => $result->elapsedSeconds],
            $result->toArray(),
        );

        fclose($stream);
    }

    public function testRunnerRefusesAnOptionTheExperimentDoesNotRead(): void
    {
        $stream = fopen('php://temp', 'r+');
        self::assertIsResource($stream);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('demo:smoke does not use --students');

        new ExperimentRunner($this->registry())->run(
            'demo:smoke',
            Options::fromStrings(['students' => '3']),
            new Output($stream, quiet: true),
        );
    }

    public function testOutputWritesProseAndSuppressesItWhenQuiet(): void
    {
        $stream = fopen('php://temp', 'r+');
        self::assertIsResource($stream);
        $output = new Output($stream);
        $output->heading('Title', 'Description');
        $output->line('Body');
        $output->printf('%d/%s', 1, 'two');
        $output->measure('answer', 42);
        rewind($stream);
        $text = (string) stream_get_contents($stream);

        self::assertSame("Experiment: Title\nDescription\n\nBody\n1/two", $text);
        self::assertSame(['answer' => 42], $output->measurements());

        $quietStream = fopen('php://temp', 'r+');
        self::assertIsResource($quietStream);
        $quiet = new Output($quietStream, quiet: true);
        $quiet->line('hidden');
        rewind($quietStream);

        self::assertSame('', stream_get_contents($quietStream));

        fclose($stream);
        fclose($quietStream);
    }

    private function registry(): Registry
    {
        return new Registry(ProjectRoot::path() . '/experiments');
    }
}
