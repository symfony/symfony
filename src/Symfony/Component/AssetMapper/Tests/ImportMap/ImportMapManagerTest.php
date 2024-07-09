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

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\AssetMapper\ImportMap\ImportMapConfigReader;
use Symfony\Component\AssetMapper\ImportMap\ImportMapEntries;
use Symfony\Component\AssetMapper\ImportMap\ImportMapEntry;
use Symfony\Component\AssetMapper\ImportMap\ImportMapManager;
use Symfony\Component\AssetMapper\ImportMap\ImportMapType;
use Symfony\Component\AssetMapper\ImportMap\PackageRequireOptions;
use Symfony\Component\AssetMapper\ImportMap\RemotePackageDownloader;
use Symfony\Component\AssetMapper\ImportMap\Resolver\PackageResolverInterface;
use Symfony\Component\AssetMapper\ImportMap\Resolver\ResolvedImportMapPackage;
use Symfony\Component\AssetMapper\MappedAsset;
use Symfony\Component\Filesystem\Filesystem;

class ImportMapManagerTest extends TestCase
{
    private AssetMapperInterface&MockObject $assetMapper;
    private PackageResolverInterface&MockObject $packageResolver;
    private ImportMapConfigReader&MockObject $configReader;
    private RemotePackageDownloader&MockObject $remotePackageDownloader;

    private Filesystem $filesystem;
    private static string $writableRoot = __DIR__.'/../Fixtures/importmap_manager';

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        if (!file_exists(__DIR__.'/../Fixtures/importmap_manager/assets')) {
            $this->filesystem->mkdir(self::$writableRoot.'/assets');
        }
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove(self::$writableRoot);
    }

    /**
     * @dataProvider getRequirePackageTests
     */
    public function testRequire(array $packages, int $expectedProviderPackageArgumentCount, array $resolvedPackages, array $expectedImportMap)
    {
        $manager = $this->createImportMapManager();
        // physical file we point to in one test
        $this->writeFile('assets/some_file.js', 'some file contents');

        $this->assetMapper->expects($this->any())
            ->method('getAssetFromSourcePath')
            ->willReturnCallback(function (string $sourcePath) {
                if (str_ends_with($sourcePath, 'some_file.js')) {
                    // physical file we point to in one test
                    return new MappedAsset('some_file.js', $sourcePath);
                }

                return null;
            })
        ;

        $this->configReader->expects($this->any())
            ->method('convertPathToFilesystemPath')
            ->willReturnCallback(function ($path) {
                if (str_ends_with($path, 'some_file.js')) {
                    return '/path/to/assets/some_file.js';
                }

                throw new \Exception(\sprintf('Unexpected path "%s"', $path));
            });
        $this->configReader->expects($this->any())
            ->method('convertFilesystemPathToPath')
            ->willReturnCallback(function ($path) {
                return match ($path) {
                    '/path/to/assets/some_file.js' => './assets/some_file.js',
                    default => throw new \Exception(\sprintf('Unexpected path "%s"', $path)),
                };
            });
        $this->configReader->expects($this->once())
            ->method('getEntries')
            ->willReturn(new ImportMapEntries())
        ;

        $this->configReader->expects($this->once())
            ->method('writeEntries')
            ->with($this->callback(function (ImportMapEntries $entries) use ($expectedImportMap) {
                // assert the $entries look as expected
                $this->assertCount(\count($expectedImportMap), $entries);
                $simplifiedEntries = [];
                foreach ($entries as $entry) {
                    $simplifiedEntries[$entry->importName] = [
                        'path' => $entry->path,
                        'type' => $entry->type->value,
                        'entrypoint' => $entry->isEntrypoint,
                    ];
                    if ($entry->isRemotePackage()) {
                        $simplifiedEntries[$entry->importName]['version'] = $entry->version;
                        $simplifiedEntries[$entry->importName]['packageModuleSpecifier'] = $entry->packageModuleSpecifier;
                    }
                }

                $this->assertSame(array_keys($expectedImportMap), array_keys($simplifiedEntries));
                foreach ($expectedImportMap as $name => $expectedData) {
                    foreach ($expectedData as $key => $val) {
                        // correct windows paths for comparison
                        $actualPath = str_replace('\\', '/', $simplifiedEntries[$name][$key]);
                        $this->assertSame($val, $actualPath);
                    }
                }

                return true;
            }))
        ;

        $this->packageResolver->expects($this->exactly(0 === $expectedProviderPackageArgumentCount ? 0 : 1))
            ->method('resolvePackages')
            ->with($this->callback(function (array $packages) use ($expectedProviderPackageArgumentCount) {
                return \count($packages) === $expectedProviderPackageArgumentCount;
            }))
            ->willReturn($resolvedPackages)
        ;

        $manager->require($packages);
    }

    public static function getRequirePackageTests(): iterable
    {
        yield 'require single lodash package' => [
            'packages' => [new PackageRequireOptions('lodash')],
            'expectedProviderPackageArgumentCount' => 1,
            'resolvedPackages' => [
                self::resolvedPackage('lodash', '1.2.3'),
            ],
            'expectedImportMap' => [
                'lodash' => [
                    'version' => '1.2.3',
                ],
            ],
        ];

        yield 'require two packages' => [
            'packages' => [new PackageRequireOptions('lodash'), new PackageRequireOptions('cowsay')],
            'expectedProviderPackageArgumentCount' => 2,
            'resolvedPackages' => [
                self::resolvedPackage('lodash', '1.2.3'),
                self::resolvedPackage('cowsay', '4.5.6'),
            ],
            'expectedImportMap' => [
                'lodash' => [
                    'version' => '1.2.3',
                ],
                'cowsay' => [
                    'version' => '4.5.6',
                ],
            ],
        ];

        yield 'single_package_that_returns_as_two' => [
            'packages' => [new PackageRequireOptions('lodash')],
            'expectedProviderPackageArgumentCount' => 1,
            'resolvedPackages' => [
                self::resolvedPackage('lodash', '1.2.3'),
                self::resolvedPackage('lodash-dependency', '9.8.7'),
            ],
            'expectedImportMap' => [
                'lodash' => [
                    'version' => '1.2.3',
                ],
                'lodash-dependency' => [
                    'version' => '9.8.7',
                ],
            ],
        ];

        yield 'single_package_with_version_constraint' => [
            'packages' => [new PackageRequireOptions('lodash', '^1.2.3')],
            'expectedProviderPackageArgumentCount' => 1,
            'resolvedPackages' => [
                self::resolvedPackage('lodash', '1.2.7'),
            ],
            'expectedImportMap' => [
                'lodash' => [
                    'version' => '1.2.7',
                ],
            ],
        ];

        yield 'single_package_with_a_path' => [
            'packages' => [new PackageRequireOptions('some/module', path: self::$writableRoot.'/assets/some_file.js')],
            'expectedProviderPackageArgumentCount' => 0,
            'resolvedPackages' => [],
            'expectedImportMap' => [
                'some/module' => [
                    // converted to relative path
                    'path' => './assets/some_file.js',
                ],
            ],
        ];
    }

    public function testRemove()
    {
        $manager = $this->createImportMapManager();
        $this->mockImportMap([
            self::createRemoteEntry('lodash', version: '1.2.3', path: '/vendor/lodash.js'),
            self::createRemoteEntry('cowsay', version: '4.5.6', path: '/vendor/cowsay.js'),
            self::createRemoteEntry('chance', version: '7.8.9', path: '/vendor/chance.js'),
            self::createLocalEntry('app', path: './app.js'),
            self::createLocalEntry('other', path: './other.js'),
        ]);

        $this->configReader->expects($this->once())
            ->method('writeEntries')
            ->with($this->callback(function (ImportMapEntries $entries) {
                $this->assertCount(3, $entries);
                $this->assertTrue($entries->has('lodash'));
                $this->assertTrue($entries->has('chance'));
                $this->assertTrue($entries->has('other'));

                return true;
            }))
        ;

        $manager->remove(['cowsay', 'app']);
    }

    public function testUpdateAll()
    {
        $manager = $this->createImportMapManager();
        $this->mockImportMap([
            self::createRemoteEntry('lodash', version: '1.2.3', path: '/vendor/lodash.js'),
            self::createRemoteEntry('bootstrap', version: '5.1.3', path: '/vendor/bootstrap.js'),
            self::createLocalEntry('app', path: 'app.js'),
        ]);

        $this->packageResolver->expects($this->once())
            ->method('resolvePackages')
            ->with($this->callback(function ($packages) {
                $this->assertInstanceOf(PackageRequireOptions::class, $packages[0]);
                /* @var PackageRequireOptions[] $packages */
                $this->assertCount(2, $packages);

                $this->assertSame('lodash', $packages[0]->packageModuleSpecifier);
                $this->assertSame('bootstrap', $packages[1]->packageModuleSpecifier);

                return true;
            }))
            ->willReturn([
                self::resolvedPackage('lodash', '1.2.9'),
                self::resolvedPackage('bootstrap', '5.2.3'),
            ])
        ;

        $this->configReader->expects($this->once())
            ->method('writeEntries')
            ->with($this->callback(function (ImportMapEntries $entries) {
                $this->assertCount(3, $entries);
                $this->assertTrue($entries->has('lodash'));
                $this->assertTrue($entries->has('bootstrap'));
                $this->assertTrue($entries->has('app'));

                $this->assertSame('1.2.9', $entries->get('lodash')->version);
                $this->assertSame('5.2.3', $entries->get('bootstrap')->version);

                return true;
            }))
        ;

        $manager->update();
    }

    public function testUpdateWithSpecificPackages()
    {
        $manager = $this->createImportMapManager();
        $this->mockImportMap([
            self::createRemoteEntry('lodash', version: '1.2.3'),
            self::createRemoteEntry('cowsay', version: '4.5.6'),
            self::createRemoteEntry('bootstrap', version: '5.1.3'),
            self::createLocalEntry('app', path: 'app.js'),
        ]);

        $this->packageResolver->expects($this->once())
            ->method('resolvePackages')
            ->willReturn([
                self::resolvedPackage('cowsay', '4.5.9'),
            ])
        ;

        $this->remotePackageDownloader->expects($this->once())
            ->method('downloadPackages');
        $this->configReader->expects($this->once())
            ->method('writeEntries')
            ->with($this->callback(function (ImportMapEntries $entries) {
                $this->assertCount(4, $entries);

                $this->assertSame('1.2.3', $entries->get('lodash')->version);
                $this->assertSame('4.5.9', $entries->get('cowsay')->version);

                return true;
            }))
        ;

        $manager->update(['cowsay']);
    }

    /**
     * @dataProvider getPackageNameTests
     */
    public function testParsePackageName(string $packageName, array $expectedReturn)
    {
        $parsed = ImportMapManager::parsePackageName($packageName);
        $this->assertIsArray($parsed);

        // remove integer keys - they're noise
        $parsed = array_filter($parsed, fn ($key) => !\is_int($key), \ARRAY_FILTER_USE_KEY);
        $this->assertEquals($expectedReturn, $parsed);

        $parsedWithAlias = ImportMapManager::parsePackageName($packageName.'=some_alias');
        $this->assertIsArray($parsedWithAlias);
        $parsedWithAlias = array_filter($parsedWithAlias, fn ($key) => !\is_int($key), \ARRAY_FILTER_USE_KEY);
        $expectedReturnWithAlias = $expectedReturn + ['alias' => 'some_alias'];
        $this->assertEquals($expectedReturnWithAlias, $parsedWithAlias, 'Asserting with alias');
    }

    public static function getPackageNameTests(): iterable
    {
        yield 'simple' => [
            'lodash',
            [
                'package' => 'lodash',
            ],
        ];

        yield 'with_version_constraint' => [
            'lodash@^1.2.3',
            [
                'package' => 'lodash',
                'version' => '^1.2.3',
            ],
        ];

        yield 'namespaced_package_simple' => [
            '@hotwired/stimulus',
            [
                'package' => '@hotwired/stimulus',
            ],
        ];

        yield 'namespaced_package_with_version_constraint' => [
            '@hotwired/stimulus@^1.2.3',
            [
                'package' => '@hotwired/stimulus',
                'version' => '^1.2.3',
            ],
        ];
    }

    private function createImportMapManager(): ImportMapManager
    {
        $this->assetMapper = $this->createMock(AssetMapperInterface::class);
        $this->configReader = $this->createMock(ImportMapConfigReader::class);
        $this->packageResolver = $this->createMock(PackageResolverInterface::class);
        $this->remotePackageDownloader = $this->createMock(RemotePackageDownloader::class);

        // mock this to behave like normal
        $this->configReader->expects($this->any())
            ->method('createRemoteEntry')
            ->willReturnCallback(function (string $importName, ImportMapType $type, string $version, string $packageModuleSpecifier, bool $isEntrypoint) {
                $path = '/path/to/vendor/'.$packageModuleSpecifier.'.js';

                return ImportMapEntry::createRemote($importName, $type, $path, $version, $packageModuleSpecifier, $isEntrypoint);
            });

        return new ImportMapManager(
            $this->assetMapper,
            $this->configReader,
            $this->remotePackageDownloader,
            $this->packageResolver,
        );
    }

    private static function resolvedPackage(string $packageName, string $version, ImportMapType $type = ImportMapType::JS)
    {
        return new ResolvedImportMapPackage(
            new PackageRequireOptions($packageName),
            $version,
            $type,
        );
    }

    private function mockImportMap(array $importMapEntries): void
    {
        $this->configReader->expects($this->any())
            ->method('getEntries')
            ->willReturn(new ImportMapEntries($importMapEntries))
        ;
    }

    private function writeFile(string $filename, string $content): void
    {
        $path = \dirname(self::$writableRoot.'/'.$filename);
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
        file_put_contents(self::$writableRoot.'/'.$filename, $content);
    }

    private static function createLocalEntry(string $importName, string $path, ImportMapType $type = ImportMapType::JS, bool $isEntrypoint = false): ImportMapEntry
    {
        return ImportMapEntry::createLocal($importName, $type, path: $path, isEntrypoint: $isEntrypoint);
    }

    private static function createRemoteEntry(string $importName, string $version, ?string $path = null, ImportMapType $type = ImportMapType::JS, ?string $packageSpecifier = null): ImportMapEntry
    {
        $packageSpecifier = $packageSpecifier ?? $importName;
        $path = $path ?? '/vendor/any-path.js';

        return ImportMapEntry::createRemote($importName, $type, path: $path, version: $version, packageModuleSpecifier: $packageSpecifier, isEntrypoint: false);
    }
}
