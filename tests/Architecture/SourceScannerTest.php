<?php

declare(strict_types=1);

namespace ArchitectureLab\Tests\Architecture;

use ArchitectureLab\Architecture\ClassKind;
use ArchitectureLab\Architecture\SourceScanner;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SourceScannerTest extends TestCase
{
    public function testParsesNamespaceImportsAndDeclarations(): void
    {
        $source = <<<'PHP'
            <?php

            declare(strict_types=1);

            namespace ArchitectureLab\Catalog\Application;

            use ArchitectureLab\Catalog\Domain\CourseOffering;
            use ArchitectureLab\Shared\Money as Price;
            use function strlen;

            interface CatalogApi
            {
            }

            abstract class BaseService
            {
            }

            final class CatalogService
            {
            }

            enum Mode: string
            {
                case On = 'on';
            }

            trait HasCounters
            {
            }
            PHP;

        $file = new SourceScanner()->parse('src/Catalog/CatalogService.php', $source);

        self::assertSame('ArchitectureLab\Catalog\Application', $file->namespace);
        self::assertSame('src/Catalog/CatalogService.php', $file->path);
        self::assertSame([
            'ArchitectureLab\Catalog\Domain\CourseOffering',
            'ArchitectureLab\Shared\Money',
        ], $file->imports);
        self::assertSame('ArchitectureLab\Catalog\Application\CatalogApi', $file->primaryClass());
        self::assertCount(5, $file->classes);
        self::assertSame(ClassKind::Interface, $file->classes[0]->kind);
        self::assertTrue($file->classes[0]->abstract);
        self::assertSame(ClassKind::ClassName, $file->classes[1]->kind);
        self::assertTrue($file->classes[1]->abstract);
        self::assertSame(ClassKind::Enum, $file->classes[3]->kind);
        self::assertSame(ClassKind::Trait, $file->classes[4]->kind);
    }

    public function testIgnoresAnonymousClassesAndFunctionImports(): void
    {
        $source = <<<'PHP'
            <?php

            namespace ArchitectureLab\Test;

            use ArchitectureLab\Shared\Money;

            $object = new class () {
            };

            $other = new class extends \stdClass {
            };
            PHP;

        $file = new SourceScanner()->parse('test.php', $source);

        self::assertSame([], $file->classes);
    }

    public function testScansARealDirectoryAndSortsByPath(): void
    {
        $directory = sys_get_temp_dir() . '/architecture-lab-scanner-' . bin2hex(random_bytes(6));
        mkdir($directory . '/b', 0o777, true);
        mkdir($directory . '/a', 0o777, true);
        file_put_contents($directory . '/b/Second.php', "<?php\nnamespace Demo\\B;\nclass Second {}\n");
        file_put_contents($directory . '/a/First.php', "<?php\nnamespace Demo\\A;\nclass First {}\n");
        file_put_contents($directory . '/a/readme.txt', 'not php');

        try {
            $files = new SourceScanner()->scan($directory, $directory);

            self::assertCount(2, $files);
            self::assertSame('a/First.php', $files[0]->path);
            self::assertSame('b/Second.php', $files[1]->path);
        } finally {
            unlink($directory . '/b/Second.php');
            unlink($directory . '/a/First.php');
            unlink($directory . '/a/readme.txt');
            rmdir($directory . '/b');
            rmdir($directory . '/a');
            rmdir($directory);
        }
    }

    public function testUnreadableFileIsAnError(): void
    {
        $this->expectException(RuntimeException::class);

        new SourceScanner()->scan(__DIR__ . '/missing-directory');
    }
}
