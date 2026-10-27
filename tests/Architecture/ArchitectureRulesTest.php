<?php

declare(strict_types=1);

namespace ArchitectureLab\Tests\Architecture;

use ArchitectureLab\Architecture\ArchitectureAnalyzer;
use ArchitectureLab\Architecture\ArchitectureReport;
use ArchitectureLab\Architecture\ClassInfo;
use ArchitectureLab\Architecture\ClassKind;
use ArchitectureLab\Architecture\CycleDetector;
use ArchitectureLab\Architecture\ModuleMap;
use ArchitectureLab\Architecture\RuleChecker;
use ArchitectureLab\Architecture\SourceFile;
use ArchitectureLab\Foundation\ProjectRoot;
use PHPUnit\Framework\TestCase;

final class ArchitectureRulesTest extends TestCase
{
    public function testCleanTreeHasNoViolationsAndNoCycles(): void
    {
        $report = ArchitectureAnalyzer::create()->analyzeClean(ProjectRoot::path());

        self::assertGreaterThan(100, $report->fileCount());
        self::assertGreaterThan(100, $report->classCount());
        self::assertGreaterThan(15, $report->interfaceCount());
        self::assertSame([], $report->violations);
        self::assertSame([], $report->cycles);
        self::assertNotEmpty($report->edges);
        self::assertNotEmpty($report->metrics);
    }

    public function testLayeredBaselineHasExactlyTheIntendedViolations(): void
    {
        $report = ArchitectureAnalyzer::create()->analyzeLayered(ProjectRoot::path());
        $rules = array_map(static fn ($violation): string => $violation->rule, $report->violations);

        sort($rules);

        self::assertSame(['framework-in-application', 'layer-skip'], $rules);
        self::assertSame(7, $report->fileCount());
        self::assertSame(7, $report->classCount());
    }

    public function testDomainPurityIsEnforced(): void
    {
        $files = [
            $this->file('ArchitectureLab\Billing\Domain', ['ArchitectureLab\Enrollment\Domain\Enrollment']),
        ];

        $violations = new RuleChecker()->checkClean($files, ModuleMap::architectureLab());

        self::assertNotEmpty($violations);
        self::assertContains('domain-purity', array_map(static fn ($violation): string => $violation->rule, $violations));
    }

    public function testDomainMayUseTheSharedKernel(): void
    {
        $files = [
            $this->file('ArchitectureLab\Billing\Domain', ['ArchitectureLab\Shared\Money']),
        ];

        self::assertSame([], new RuleChecker()->checkClean($files, ModuleMap::architectureLab()));
    }

    public function testApplicationMustNotImportThePersistenceAdapter(): void
    {
        $files = [
            $this->file('ArchitectureLab\Catalog\Application', ['ArchitectureLab\Persistence\SqliteDocumentStore']),
        ];

        $rules = array_map(
            static fn ($violation): string => $violation->rule,
            new RuleChecker()->checkClean($files, ModuleMap::architectureLab()),
        );

        self::assertContains('application-port-only', $rules);
    }

    public function testApplicationMustNotImportItsOwnInfrastructure(): void
    {
        $files = [
            $this->file('ArchitectureLab\Catalog\Application', ['ArchitectureLab\Catalog\Infrastructure\DocumentCourseOfferingRepository']),
        ];

        $rules = array_map(
            static fn ($violation): string => $violation->rule,
            new RuleChecker()->checkClean($files, ModuleMap::architectureLab()),
        );

        self::assertContains('application-no-adapter', $rules);
    }

    public function testApplicationMustNotReachAnotherContextDirectly(): void
    {
        $files = [
            $this->file('ArchitectureLab\Enrollment\Application', ['ArchitectureLab\Billing\PublicApi\BillingApi']),
        ];

        $rules = array_map(
            static fn ($violation): string => $violation->rule,
            new RuleChecker()->checkClean($files, ModuleMap::architectureLab()),
        );

        self::assertContains('application-no-foreign-context', $rules);
    }

    public function testCrossContextImportsMustUsePublicApi(): void
    {
        $files = [
            $this->file('ArchitectureLab\Enrollment\Infrastructure', ['ArchitectureLab\Catalog\Domain\CourseOffering']),
        ];

        $rules = array_map(
            static fn ($violation): string => $violation->rule,
            new RuleChecker()->checkClean($files, ModuleMap::architectureLab()),
        );

        self::assertContains('cross-context-public-api', $rules);
    }

    public function testInfrastructureMayUseAnotherContextPublicApi(): void
    {
        $files = [
            $this->file('ArchitectureLab\Enrollment\Infrastructure', ['ArchitectureLab\Catalog\PublicApi\CatalogApi']),
        ];

        self::assertSame([], new RuleChecker()->checkClean($files, ModuleMap::architectureLab()));
    }

    public function testDeliveryMustNotImportContextInternals(): void
    {
        $files = [
            $this->file('ArchitectureLab\Integration', ['ArchitectureLab\Enrollment\Domain\Enrollment']),
        ];

        $rules = array_map(
            static fn ($violation): string => $violation->rule,
            new RuleChecker()->checkClean($files, ModuleMap::architectureLab()),
        );

        self::assertContains('delivery-public-api', $rules);
    }

    public function testLayeredCheckerFindsTheLayerSkipAndPdoImport(): void
    {
        $files = [
            $this->file('ArchitectureLab\Baseline\Layered\Presentation', [
                'ArchitectureLab\Baseline\Layered\Infrastructure\LayeredEnrollmentRepository',
            ]),
            $this->file('ArchitectureLab\Baseline\Layered\Application', ['PDO']),
            $this->file('ArchitectureLab\Baseline\Layered\Domain', ['ArchitectureLab\Baseline\Layered\Application\LayeredEnrollmentService']),
        ];

        $rules = array_map(
            static fn ($violation): string => $violation->rule,
            new RuleChecker()->checkLayered($files),
        );

        self::assertContains('layer-skip', $rules);
        self::assertContains('framework-in-application', $rules);
        self::assertContains('domain-leak', $rules);
    }

    public function testCycleDetectorFindsAStronglyConnectedComponent(): void
    {
        $cycles = new CycleDetector()->find([
            'Catalog' => ['Enrollment'],
            'Enrollment' => ['Billing'],
            'Billing' => ['Catalog'],
            'Reporting' => [],
        ]);

        self::assertSame([['Billing', 'Catalog', 'Enrollment']], $cycles);
    }

    public function testCycleDetectorIgnoresSelfLoopsAndAcyclicGraphs(): void
    {
        $detector = new CycleDetector();

        self::assertSame([], $detector->find(['A' => ['A'], 'B' => ['C'], 'C' => []]));
    }

    public function testReportSerializesItsFacts(): void
    {
        $report = new ArchitectureReport([], [], [], [], []);
        $array = $report->toArray();

        self::assertSame(0, $array['files']);
        self::assertSame(0, $array['classes']);
        self::assertSame([], $array['violations']);
        self::assertSame([], $array['cycles']);
    }

    /**
     * @param list<string> $imports
     */
    private function file(string $namespace, array $imports): SourceFile
    {
        $class = $namespace . '\Example';

        return new SourceFile(
            path: 'src/Example.php',
            namespace: $namespace,
            imports: $imports,
            classes: [new ClassInfo($class, ClassKind::ClassName, false, 5)],
            lines: 20,
        );
    }
}
