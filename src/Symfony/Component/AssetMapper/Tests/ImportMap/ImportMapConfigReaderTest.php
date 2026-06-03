<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper\Tests\ImportMap;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\AssetMapper\Exception\RuntimeException;
use Symfony\Component\AssetMapper\ImportMap\ImportMapConfigReader;
use Symfony\Component\AssetMapper\ImportMap\ImportMapEntries;
use Symfony\Component\AssetMapper\ImportMap\ImportMapEntry;
use Symfony\Component\AssetMapper\ImportMap\ImportMapType;
use Symfony\Component\AssetMapper\ImportMap\RemotePackageStorage;
use Symfony\Component\Filesystem\Filesystem;

class ImportMapConfigReaderTest extends TestCase
{
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        if (!file_exists(__DIR__.'/../Fixtures/importmap_config_reader/assets')) {
            $this->filesystem->mkdir(__DIR__.'/../Fixtures/importmap_config_reader/assets');
        }
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove(__DIR__.'/../Fixtures/importmap_config_reader');
    }

    public function testGetEntriesAndWriteEntries()
    {
        $importMap = <<<EOF
            <?php
            return [
                'remote_package' => [
                    'version' => '3.2.1',
                ],
                'local_package' => [
                    'path' => 'app.js',
                ],
                'type_css' => [
                    'path' => 'styles/app.css',
                    'type' => 'css',
                ],
                'entry_point' => [
                    'path' => 'entry.js',
                    'entrypoint' => true,
                ],
                'package/with_file.js' => [
                    'version' => '1.0.0',
                ],
            ];
            EOF;
        file_put_contents(__DIR__.'/../Fixtures/importmap_config_reader/importmap.php', $importMap);

        $remotePackageStorage = $this->createStub(RemotePackageStorage::class);
        $remotePackageStorage
            ->method('getDownloadPath')
            ->willReturnCallback(static fn (string $packageModuleSpecifier, ImportMapType $type) => '/path/to/vendor/'.$packageModuleSpecifier.'.'.$type->value);
        $reader = new ImportMapConfigReader(
            __DIR__.'/../Fixtures/importmap_config_reader/importmap.php',
            $remotePackageStorage,
        );
        $entries = $reader->getEntries();
        $this->assertInstanceOf(ImportMapEntries::class, $entries);
        /** @var ImportMapEntry[] $allEntries */
        $allEntries = iterator_to_array($entries);
        $this->assertCount(5, $allEntries);

        $remotePackageEntry = $allEntries[0];
        $this->assertSame('remote_package', $remotePackageEntry->importName);
        $this->assertSame('/path/to/vendor/remote_package.js', $remotePackageEntry->path);
        $this->assertSame('3.2.1', $remotePackageEntry->version);
        $this->assertSame('js', $remotePackageEntry->type->value);
        $this->assertFalse($remotePackageEntry->isEntrypoint);
        $this->assertSame('remote_package', $remotePackageEntry->packageModuleSpecifier);

        $localPackageEntry = $allEntries[1];
        $this->assertFalse($localPackageEntry->isRemotePackage());
        $this->assertSame('app.js', $localPackageEntry->path);

        $typeCssEntry = $allEntries[2];
        $this->assertSame('css', $typeCssEntry->type->value);

        $packageWithFileEntry = $allEntries[4];
        $this->assertSame('package/with_file.js', $packageWithFileEntry->packageModuleSpecifier);

        // now save the original raw data from importmap.php and delete the file
        $originalImportMapData = (static fn () => eval('?>'.file_get_contents(__DIR__.'/../Fixtures/importmap_config_reader/importmap.php')))();
        unlink(__DIR__.'/../Fixtures/importmap_config_reader/importmap.php');
        // dump the entries back to the file
        $reader->writeEntries($entries);
        $newImportMapData = (static fn () => eval('?>'.file_get_contents(__DIR__.'/../Fixtures/importmap_config_reader/importmap.php')))();

        $this->assertSame($originalImportMapData, $newImportMapData);
    }

    #[DataProvider('getPathToFilesystemPathTests')]
    public function testConvertPathToFilesystemPath(string $path, string $expectedPath)
    {
        $configReader = new ImportMapConfigReader(realpath(__DIR__.'/../Fixtures/importmap.php'), new RemotePackageStorage(sys_get_temp_dir()));
        // normalize path separators for comparison
        $expectedPath = str_replace('\\', '/', $expectedPath);
        $this->assertSame($expectedPath, $configReader->convertPathToFilesystemPath($path));
    }

    public static function getPathToFilesystemPathTests()
    {
        yield 'no change' => [
            'path' => 'dir1/file2.js',
            'expectedPath' => 'dir1/file2.js',
        ];

        yield 'prefixed with relative period' => [
            'path' => './dir1/file2.js',
            'expectedPath' => realpath(__DIR__.'/../Fixtures').'/dir1/file2.js',
        ];
    }

    #[DataProvider('getFilesystemPathToPathTests')]
    public function testConvertFilesystemPathToPath(string $path, ?string $expectedPath)
    {
        $configReader = new ImportMapConfigReader(__DIR__.'/../Fixtures/importmap.php', new RemotePackageStorage(sys_get_temp_dir()));
        $this->assertSame($expectedPath, $configReader->convertFilesystemPathToPath($path));
    }

    public static function getFilesystemPathToPathTests()
    {
        yield 'not in root directory' => [
            'path' => __FILE__,
            'expectedPath' => null,
        ];

        yield 'converted to relative path' => [
            'path' => __DIR__.'/../Fixtures/dir1/file2.js',
            'expectedPath' => './dir1/file2.js',
        ];
    }

    public function testFindRootImportMapEntry()
    {
        $configReader = new ImportMapConfigReader(__DIR__.'/../Fixtures/importmap.php', new RemotePackageStorage(sys_get_temp_dir()));
        $entry = $configReader->findRootImportMapEntry('file2');
        $this->assertSame('file2', $entry->importName);
        $this->assertSame('file2.js', $entry->path);
    }

    public function testGetEntriesThrowsWhenImportmapDoesNotReturnArray()
    {
        $configPath = __DIR__.'/../Fixtures/importmap_config_reader/importmap.php';
        file_put_contents($configPath, "<?php\n");

        $reader = new ImportMapConfigReader($configPath, $this->createStub(RemotePackageStorage::class));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(\sprintf('The "%s" file must return an array, got "int".', $configPath));

        $reader->getEntries();
    }

    public function testGetEntriesThrowsOnUnknownTypeValue()
    {
        $configPath = __DIR__.'/../Fixtures/importmap_config_reader/importmap.php';
        file_put_contents($configPath, <<<'EOF'
            <?php
            return [
                'app' => [
                    'path' => 'app.js',
                    'type' => 'wat',
                ],
            ];
            EOF);

        $reader = new ImportMapConfigReader($configPath, $this->createStub(RemotePackageStorage::class));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The importmap entry "app" has an invalid "type" value "wat". Valid values are: "js", "css", "json".');

        $reader->getEntries();
    }

    public function testGetEntriesRunsImportmapWithoutClassScope()
    {
        $configPath = __DIR__.'/../Fixtures/importmap_config_reader/importmap.php';
        file_put_contents($configPath, <<<'EOF'
            <?php
            $scope = (new \ReflectionFunction(static fn () => null))->getClosureScopeClass();

            return [
                'app' => [
                    'path' => null === $scope ? 'no-scope' : $scope->name,
                ],
            ];
            EOF);

        $reader = new ImportMapConfigReader($configPath, $this->createStub(RemotePackageStorage::class));
        $entries = iterator_to_array($reader->getEntries());

        $this->assertCount(1, $entries);
        $this->assertSame('no-scope', $entries[0]->path);
    }
}
