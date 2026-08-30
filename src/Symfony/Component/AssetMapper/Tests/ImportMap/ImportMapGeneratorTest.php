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
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\AssetMapper\CompiledAssetMapperConfigReader;
use Symfony\Component\AssetMapper\Exception\LogicException;
use Symfony\Component\AssetMapper\ImportMap\ImportMapConfigReader;
use Symfony\Component\AssetMapper\ImportMap\ImportMapEntries;
use Symfony\Component\AssetMapper\ImportMap\ImportMapEntry;
use Symfony\Component\AssetMapper\ImportMap\ImportMapGenerator;
use Symfony\Component\AssetMapper\ImportMap\ImportMapType;
use Symfony\Component\AssetMapper\ImportMap\JavaScriptImport;
use Symfony\Component\AssetMapper\MappedAsset;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

class ImportMapGeneratorTest extends TestCase
{
    private const FIXTURE_JS = __DIR__.'/../Fixtures/dir1/file2.js';

    private AssetMapperInterface $assetMapper;
    private CompiledAssetMapperConfigReader $compiledConfigReader;
    private ImportMapConfigReader $configReader;

    private Filesystem $filesystem;
    private static string $writableRoot = __DIR__.'/../Fixtures/importmap_generator';

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        if (!file_exists(self::$writableRoot.'/assets')) {
            $this->filesystem->mkdir(self::$writableRoot.'/assets');
        }
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove(self::$writableRoot);
    }

    public function testGetEntrypointNames()
    {
        $manager = $this->createImportMapGenerator();
        $this->mockImportMap([
            ImportMapEntry::createLocal('entry1', ImportMapType::JS, '/any', true),
            ImportMapEntry::createLocal('entry2', ImportMapType::JS, '/any', true),
            ImportMapEntry::createLocal('not_entrypoint', ImportMapType::JS, '/any', false),
        ]);

        $this->assertEquals(['entry1', 'entry2'], $manager->getEntrypointNames());
    }

    public function testGetImportMapDataLimitedToTheReachableEntries()
    {
        $manager = $this->createImportMapGenerator(entries: ImportMapGenerator::ENTRIES_REACHABLE);
        $this->mockImportMap([
            self::createLocalEntry('login', path: 'login.js', isEntrypoint: true),
            self::createLocalEntry('admin', path: 'admin.js', isEntrypoint: true),
            self::createLocalEntry('lazy_controller', path: 'lazy_controller.js'),
            self::createLocalEntry('lazy_dependency', path: 'lazy_dependency.js'),
            self::createLocalEntry('admin_dependency', path: 'admin_dependency.js'),
            self::createLocalEntry('never_imported', path: 'never_imported.js'),
            self::createRemoteEntry('es-module-shims', version: '1.8.2', path: '/path/to/es-module-shims.js'),
        ]);
        $this->configReader->method('convertPathToFilesystemPath')->willReturnArgument(0);

        $lazyController = new MappedAsset(
            'lazy_controller.js',
            '/path/to/lazy_controller.js',
            publicPath: '/assets/lazy_controller-d1g35t.js',
            javaScriptImports: [
                new JavaScriptImport('lazy_dependency', assetLogicalPath: 'lazy_dependency.js', assetSourcePath: '/path/to/lazy_dependency.js', isLazy: false),
            ],
        );
        $this->mockAssetMapper([
            new MappedAsset(
                'login.js',
                '/path/to/login.js',
                publicPath: '/assets/login-d1g35t.js',
                javaScriptImports: [
                    // the lazy import must stay resolvable through the import map, its own dependencies included
                    new JavaScriptImport('lazy_controller', assetLogicalPath: $lazyController->logicalPath, assetSourcePath: $lazyController->sourcePath, isLazy: true),
                ],
            ),
            new MappedAsset(
                'admin.js',
                '/path/to/admin.js',
                publicPath: '/assets/admin-d1g35t.js',
                javaScriptImports: [
                    new JavaScriptImport('admin_dependency', assetLogicalPath: 'admin_dependency.js', assetSourcePath: '/path/to/admin_dependency.js', isLazy: false),
                ],
            ),
            $lazyController,
            new MappedAsset('lazy_dependency.js', '/path/to/lazy_dependency.js', publicPath: '/assets/lazy_dependency-d1g35t.js'),
            new MappedAsset('admin_dependency.js', '/path/to/admin_dependency.js', publicPath: '/assets/admin_dependency-d1g35t.js'),
            new MappedAsset('never_imported.js', '/path/to/never_imported.js', publicPath: '/assets/never_imported-d1g35t.js'),
            new MappedAsset('es-module-shims.js', '/path/to/es-module-shims.js', publicPath: '/assets/es-module-shims-d1g35t.js'),
        ]);

        $actualImportMapData = $manager->getImportMapData(['login'], 'es-module-shims');

        $this->assertEquals([
            // the entrypoint and its eager imports are preloaded
            'login' => ['path' => '/assets/login-d1g35t.js', 'type' => 'js', 'preload' => true],
            // the lazy import and its own dependencies stay in the map, unpreloaded
            'lazy_controller' => ['path' => '/assets/lazy_controller-d1g35t.js', 'type' => 'js'],
            'lazy_dependency' => ['path' => '/assets/lazy_dependency-d1g35t.js', 'type' => 'js'],
            // the polyfill is always kept
            'es-module-shims' => ['path' => '/assets/es-module-shims-d1g35t.js', 'type' => 'js'],
            // the other entrypoint, its dependencies and the entries imported by nothing are left out
        ], $actualImportMapData);
    }

    public function testGetImportMapDataLimitedToTheReachableEntriesUsesTheCompiledMetadata()
    {
        $this->compiledConfigReader = $this->createMock(CompiledAssetMapperConfigReader::class);
        $this->compiledConfigReader
            ->method('configExists')
            ->willReturnCallback(static fn (string $file) => 'entrypoint.reachable.app.json' === $file);
        $this->compiledConfigReader->expects($this->once())
            ->method('loadConfig')
            ->with('entrypoint.reachable.app.json')
            ->willReturn(['dependency']);

        $manager = $this->createImportMapGenerator(entries: ImportMapGenerator::ENTRIES_REACHABLE);
        $this->mockImportMap([
            self::createLocalEntry('app', path: 'app.js', isEntrypoint: true),
            self::createLocalEntry('dependency', path: 'dependency.js'),
            self::createLocalEntry('never_imported', path: 'never_imported.js'),
        ]);
        $this->mockAssetMapper([
            new MappedAsset('app.js', '/path/to/app.js', publicPath: '/assets/app-d1g35t.js'),
            new MappedAsset('dependency.js', '/path/to/dependency.js', publicPath: '/assets/dependency-d1g35t.js'),
            new MappedAsset('never_imported.js', '/path/to/never_imported.js', publicPath: '/assets/never_imported-d1g35t.js'),
        ]);

        $this->assertEquals([
            'app' => ['path' => '/assets/app-d1g35t.js', 'type' => 'js', 'preload' => true],
            'dependency' => ['path' => '/assets/dependency-d1g35t.js', 'type' => 'js'],
        ], $manager->getImportMapData(['app']));
    }

    public function testInvalidEntries()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Unsupported import map entries "everything".');

        $this->createImportMapGenerator(entries: 'everything');
    }

    public function testGetImportMapData()
    {
        $manager = $this->createImportMapGenerator();
        $this->mockImportMap([
            self::createLocalEntry(
                'entry1',
                path: 'entry1.js',
                isEntrypoint: true,
            ),
            self::createLocalEntry(
                'entry2',
                path: 'entry2.js',
                isEntrypoint: true,
            ),
            self::createLocalEntry(
                'entry3',
                path: 'entry3.js',
                isEntrypoint: true,
            ),
            self::createLocalEntry(
                'normal_js_file',
                path: 'normal_js_file.js',
            ),
            self::createLocalEntry(
                'css_in_importmap',
                path: 'styles/css_in_importmap.css',
                type: ImportMapType::CSS,
            ),
            self::createLocalEntry(
                'never_imported_css',
                path: 'styles/never_imported_css.css',
                type: ImportMapType::CSS,
            ),
        ]);

        $importedFile1 = new MappedAsset(
            'imported_file1.js',
            '/path/to/imported_file1.js',
            publicPathWithoutDigest: '/assets/imported_file1.js',
            publicPath: '/assets/imported_file1-d1g35t.js',
        );
        $importedFile2 = new MappedAsset(
            'imported_file2.js',
            '/path/to/imported_file2.js',
            publicPathWithoutDigest: '/assets/imported_file2.js',
            publicPath: '/assets/imported_file2-d1g35t.js',
        );
        $importedFile3 = new MappedAsset(
            'imported_file3.js',
            '/path/to/imported_file3.js',
            publicPathWithoutDigest: '/assets/imported_file3.js',
            publicPath: '/assets/imported_file3-d1g35t.js',
        );
        $normalJsFile = new MappedAsset(
            'normal_js_file.js',
            '/path/to/normal_js_file.js',
            publicPathWithoutDigest: '/assets/normal_js_file.js',
            publicPath: '/assets/normal_js_file-d1g35t.js',
        );
        $importedCss1 = new MappedAsset(
            'styles/file1.css',
            '/path/to/styles/file1.css',
            publicPathWithoutDigest: '/assets/styles/file1.css',
            publicPath: '/assets/styles/file1-d1g35t.css',
        );
        $importedCss2 = new MappedAsset(
            'styles/file2.css',
            '/path/to/styles/file2.css',
            publicPathWithoutDigest: '/assets/styles/file2.css',
            publicPath: '/assets/styles/file2-d1g35t.css',
        );
        $importedCssInImportmap = new MappedAsset(
            'styles/css_in_importmap.css',
            '/path/to/styles/css_in_importmap.css',
            publicPathWithoutDigest: '/assets/styles/css_in_importmap.css',
            publicPath: '/assets/styles/css_in_importmap-d1g35t.css',
        );
        $neverImportedCss = new MappedAsset(
            'styles/never_imported_css.css',
            '/path/to/styles/never_imported_css.css',
            publicPathWithoutDigest: '/assets/styles/never_imported_css.css',
            publicPath: '/assets/styles/never_imported_css-d1g35t.css',
        );
        $this->mockAssetMapper([
            new MappedAsset(
                'entry1.js',
                '/path/to/entry1.js',
                publicPath: '/assets/entry1-d1g35t.js',
                javaScriptImports: [
                    new JavaScriptImport('/assets/imported_file1.js', assetLogicalPath: $importedFile1->logicalPath, assetSourcePath: $importedFile1->sourcePath, isLazy: false, addImplicitlyToImportMap: true),
                    new JavaScriptImport('/assets/styles/file1.css', assetLogicalPath: $importedCss1->logicalPath, assetSourcePath: $importedCss1->sourcePath, isLazy: false, addImplicitlyToImportMap: true),
                    new JavaScriptImport('normal_js_file', assetLogicalPath: $normalJsFile->logicalPath, assetSourcePath: $normalJsFile->sourcePath, isLazy: false),
                ]
            ),
            new MappedAsset(
                'entry2.js',
                '/path/to/entry2.js',
                publicPath: '/assets/entry2-d1g35t.js',
                javaScriptImports: [
                    new JavaScriptImport('/assets/imported_file2.js', assetLogicalPath: $importedFile2->logicalPath, assetSourcePath: $importedFile2->sourcePath, isLazy: false, addImplicitlyToImportMap: true),
                    new JavaScriptImport('css_in_importmap', assetLogicalPath: $importedCssInImportmap->logicalPath, assetSourcePath: $importedCssInImportmap->sourcePath, isLazy: false),
                    new JavaScriptImport('/assets/styles/file2.css', assetLogicalPath: $importedCss2->logicalPath, assetSourcePath: $importedCss2->sourcePath, isLazy: false, addImplicitlyToImportMap: true),
                ]
            ),
            new MappedAsset(
                'entry3.js',
                '/path/to/entry3.js',
                publicPath: '/assets/entry3-d1g35t.js',
                javaScriptImports: [
                    new JavaScriptImport('/assets/imported_file3.js', assetLogicalPath: $importedFile3->logicalPath, assetSourcePath: $importedFile3->sourcePath, isLazy: false),
                ],
            ),
            $importedFile1,
            $importedFile2,
            // $importedFile3,
            $normalJsFile,
            $importedCss1,
            $importedCss2,
            $importedCssInImportmap,
            $neverImportedCss,
        ]);

        $actualImportMapData = $manager->getImportMapData(['entry2', 'entry1']);

        $this->assertEquals([
            'entry1' => [
                'path' => '/assets/entry1-d1g35t.js',
                'type' => 'js',
                'preload' => true, // Rendered entry points are preloaded
            ],
            '/assets/imported_file1.js' => [
                'path' => '/assets/imported_file1-d1g35t.js',
                'type' => 'js',
                'preload' => true,
            ],
            'entry2' => [
                'path' => '/assets/entry2-d1g35t.js',
                'type' => 'js',
                'preload' => true,  // Rendered entry points are preloaded
            ],
            '/assets/imported_file2.js' => [
                'path' => '/assets/imported_file2-d1g35t.js',
                'type' => 'js',
                'preload' => true,
            ],
            'normal_js_file' => [
                'path' => '/assets/normal_js_file-d1g35t.js',
                'type' => 'js',
                'preload' => true, // preloaded as it's a non-lazy dependency of an entry
            ],
            '/assets/styles/file1.css' => [
                'path' => '/assets/styles/file1-d1g35t.css',
                'type' => 'css',
                'preload' => true,
            ],
            '/assets/styles/file2.css' => [
                'path' => '/assets/styles/file2-d1g35t.css',
                'type' => 'css',
                'preload' => true,
            ],
            'css_in_importmap' => [
                'path' => '/assets/styles/css_in_importmap-d1g35t.css',
                'type' => 'css',
                'preload' => true,
            ],
            'entry3' => [
                'path' => '/assets/entry3-d1g35t.js',
                'type' => 'js', // No preload (entry point not "rendered")
            ],
            'never_imported_css' => [
                'path' => '/assets/styles/never_imported_css-d1g35t.css',
                'type' => 'css',
            ],
        ], $actualImportMapData);

        // now check the order
        $this->assertEquals([
            // entry2 & its dependencies
            'entry2',
            '/assets/imported_file2.js',
            'css_in_importmap', // in the importmap, but brought earlier because it's a dependency of entry2
            '/assets/styles/file2.css',

            // entry1 & its dependencies
            'entry1',
            '/assets/imported_file1.js',
            '/assets/styles/file1.css',
            'normal_js_file',

            // importmap entries never imported
            'entry3',
            'never_imported_css',
        ], array_keys($actualImportMapData));
    }

    #[DataProvider('getRawImportMapDataTests')]
    public function testGetRawImportMapData(array $importMapEntries, array $mappedAssets, array $expectedData)
    {
        $manager = $this->createImportMapGenerator();
        $this->mockImportMap($importMapEntries);
        $this->mockAssetMapper($mappedAssets);
        $this->configReader
            ->method('convertPathToFilesystemPath')
            ->willReturnCallback(static function (string $path) {
                if (!str_starts_with($path, '.')) {
                    return $path;
                }

                return Path::join('/fake/root', $path);
            });

        $this->assertEquals($expectedData, $manager->getRawImportMapData());
    }

    public function testGetRawImportMapDataUsesCompiledFileWhenNotDebug()
    {
        $compiledData = ['app' => ['path' => '/assets/app-compiled.js', 'type' => 'js']];

        $this->compiledConfigReader = new CompiledAssetMapperConfigReader(self::$writableRoot, debug: false);
        $this->compiledConfigReader->saveConfig(ImportMapGenerator::IMPORT_MAP_CACHE_FILENAME, $compiledData);

        $manager = $this->createImportMapGenerator();
        // the live configuration resolves to a different path; the compiled file must win
        $this->mockImportMap([self::createLocalEntry('app', path: 'app.js')]);
        $this->mockAssetMapper([new MappedAsset('app.js', publicPath: '/assets/app-live.js')]);

        $this->assertSame($compiledData, $manager->getRawImportMapData());
    }

    public function testGetRawImportMapDataIgnoresCompiledFileInDebug()
    {
        $compiledData = ['app' => ['path' => '/assets/app-compiled.js', 'type' => 'js']];

        $this->compiledConfigReader = new CompiledAssetMapperConfigReader(self::$writableRoot, debug: true);
        $this->compiledConfigReader->saveConfig(ImportMapGenerator::IMPORT_MAP_CACHE_FILENAME, $compiledData);

        $manager = $this->createImportMapGenerator();
        $this->mockImportMap([self::createLocalEntry('app', path: 'app.js')]);
        $this->mockAssetMapper([new MappedAsset('app.js', publicPath: '/assets/app-live.js')]);
        $this->configReader
            ->method('convertPathToFilesystemPath')
            ->willReturnCallback(static fn (string $path) => str_starts_with($path, '.') ? Path::join('/fake/root', $path) : $path);

        // debug ignores the compiled importmap.json and recomputes from source
        $this->assertSame(
            ['app' => ['path' => '/assets/app-live.js', 'type' => 'js']],
            $manager->getRawImportMapData(),
        );
    }

    public function testGetRawImportMapDataOmitsIntegrityByDefault()
    {
        $manager = $this->createImportMapGenerator();
        $this->mockImportMap([self::createLocalEntry('app', path: 'app.js')]);
        $this->mockAssetMapper([new MappedAsset('app.js', self::FIXTURE_JS, publicPath: '/assets/app-d13g35t.js')]);

        $this->assertSame([
            'app' => ['path' => '/assets/app-d13g35t.js', 'type' => 'js'],
        ], $manager->getRawImportMapData());
    }

    public function testGetRawImportMapDataIncludesAssetIntegrity()
    {
        $manager = $this->createImportMapGenerator(['sha384']);
        $this->mockImportMap([self::createLocalEntry('app', path: 'app.js')]);
        $this->mockAssetMapper([new MappedAsset('app.js', self::FIXTURE_JS, publicPath: '/assets/app-d13g35t.js')]);

        $this->assertSame([
            'app' => [
                'path' => '/assets/app-d13g35t.js',
                'type' => 'js',
                'integrity' => 'sha384-'.base64_encode(hash_file('sha384', self::FIXTURE_JS, true)),
            ],
        ], $manager->getRawImportMapData());
    }

    public function testGetRawImportMapDataIncludesEveryConfiguredAlgorithm()
    {
        $manager = $this->createImportMapGenerator(['sha256', 'sha384']);
        $this->mockImportMap([self::createLocalEntry('app', path: 'app.js')]);
        $this->mockAssetMapper([new MappedAsset('app.js', self::FIXTURE_JS, publicPath: '/assets/app-d13g35t.js')]);

        $this->assertSame(
            'sha256-'.base64_encode(hash_file('sha256', self::FIXTURE_JS, true))
            .' sha384-'.base64_encode(hash_file('sha384', self::FIXTURE_JS, true)),
            $manager->getRawImportMapData()['app']['integrity'],
        );
    }

    public function testGetRawImportMapDataHashesTheCompiledContent()
    {
        $manager = $this->createImportMapGenerator(['sha384']);
        $this->mockImportMap([self::createLocalEntry('app', path: 'app.js')]);
        $this->mockAssetMapper([new MappedAsset('app.js', self::FIXTURE_JS, publicPath: '/assets/app-d13g35t.js', content: 'compiled();')]);

        $this->assertSame(
            'sha384-'.base64_encode(hash('sha384', 'compiled();', true)),
            $manager->getRawImportMapData()['app']['integrity'],
        );
    }

    public function testUnsupportedIntegrityAlgorithmThrows()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Unsupported integrity hash algorithm "sha1". Supported ones are "sha256", "sha384", "sha512".');

        $this->createImportMapGenerator(['sha1']);
    }

    public static function getRawImportMapDataTests(): iterable
    {
        yield 'it returns remote downloaded entry' => [
            [
                self::createRemoteEntry(
                    '@hotwired/stimulus',
                    version: '1.2.3',
                    path: '/assets/vendor/stimulus.js'
                ),
            ],
            [
                new MappedAsset(
                    'vendor/@hotwired/stimulus.js',
                    '/assets/vendor/stimulus.js',
                    publicPath: '/assets/vendor/@hotwired/stimulus-d1g35t.js',
                ),
            ],
            [
                '@hotwired/stimulus' => [
                    'path' => '/assets/vendor/@hotwired/stimulus-d1g35t.js',
                    'type' => 'js',
                ],
            ],
        ];

        yield 'it returns basic local javascript file' => [
            [
                self::createLocalEntry(
                    'app',
                    path: 'app.js'
                ),
            ],
            [
                new MappedAsset(
                    'app.js',
                    publicPath: '/assets/app-d13g35t.js',
                ),
            ],
            [
                'app' => [
                    'path' => '/assets/app-d13g35t.js',
                    'type' => 'js',
                ],
            ],
        ];

        yield 'it returns basic local css file' => [
            [
                self::createLocalEntry(
                    'app.css',
                    path: 'styles/app.css',
                    type: ImportMapType::CSS,
                ),
            ],
            [
                new MappedAsset(
                    'styles/app.css',
                    publicPath: '/assets/styles/app-d13g35t.css',
                ),
            ],
            [
                'app.css' => [
                    'path' => '/assets/styles/app-d13g35t.css',
                    'type' => 'css',
                ],
            ],
        ];

        $simpleAsset = new MappedAsset(
            'simple.js',
            '/path/to/simple.js',
            publicPathWithoutDigest: '/assets/simple.js',
            publicPath: '/assets/simple-d1g3st.js',
        );
        yield 'it adds dependency to the importmap' => [
            [
                self::createLocalEntry(
                    'app',
                    path: 'app.js',
                ),
            ],
            [
                new MappedAsset(
                    'app.js',
                    publicPath: '/assets/app-d1g3st.js',
                    javaScriptImports: [new JavaScriptImport('/assets/simple.js', assetLogicalPath: $simpleAsset->logicalPath, assetSourcePath: $simpleAsset->sourcePath, isLazy: false, addImplicitlyToImportMap: true)]
                ),
                $simpleAsset,
            ],
            [
                'app' => [
                    'path' => '/assets/app-d1g3st.js',
                    'type' => 'js',
                ],
                '/assets/simple.js' => [
                    'path' => '/assets/simple-d1g3st.js',
                    'type' => 'js',
                ],
            ],
        ];

        yield 'it adds dependency to the importmap from a remote asset' => [
            [
                self::createRemoteEntry(
                    'bootstrap',
                    version: '1.2.3',
                    path: '/assets/vendor/bootstrap.js'
                ),
            ],
            [
                new MappedAsset(
                    'app.js',
                    sourcePath: '/assets/vendor/bootstrap.js',
                    publicPath: '/assets/vendor/bootstrap-d1g3st.js',
                    javaScriptImports: [new JavaScriptImport('/assets/simple.js', assetLogicalPath: $simpleAsset->logicalPath, assetSourcePath: $simpleAsset->sourcePath, isLazy: false, addImplicitlyToImportMap: true)]
                ),
                $simpleAsset,
            ],
            [
                'bootstrap' => [
                    'path' => '/assets/vendor/bootstrap-d1g3st.js',
                    'type' => 'js',
                ],
                '/assets/simple.js' => [
                    'path' => '/assets/simple-d1g3st.js',
                    'type' => 'js',
                ],
            ],
        ];

        $eagerImportsSimpleAsset = new MappedAsset(
            'imports_simple.js',
            '/path/to/imports_simple.js',
            publicPathWithoutDigest: '/assets/imports_simple.js',
            publicPath: '/assets/imports_simple-d1g3st.js',
            javaScriptImports: [new JavaScriptImport('/assets/simple.js', assetLogicalPath: $simpleAsset->logicalPath, assetSourcePath: $simpleAsset->sourcePath, isLazy: false, addImplicitlyToImportMap: true)]
        );
        yield 'it processes imports recursively' => [
            [
                self::createLocalEntry(
                    'app',
                    path: 'app.js',
                ),
            ],
            [
                new MappedAsset(
                    'app.js',
                    publicPath: '/assets/app-d1g3st.js',
                    javaScriptImports: [new JavaScriptImport('/assets/imports_simple.js', assetLogicalPath: $eagerImportsSimpleAsset->logicalPath, assetSourcePath: $eagerImportsSimpleAsset->sourcePath, isLazy: true, addImplicitlyToImportMap: true)]
                ),
                $eagerImportsSimpleAsset,
                $simpleAsset,
            ],
            [
                'app' => [
                    'path' => '/assets/app-d1g3st.js',
                    'type' => 'js',
                ],
                '/assets/imports_simple.js' => [
                    'path' => '/assets/imports_simple-d1g3st.js',
                    'type' => 'js',
                ],
                '/assets/simple.js' => [
                    'path' => '/assets/simple-d1g3st.js',
                    'type' => 'js',
                ],
            ],
        ];

        yield 'it process can skip adding one importmap entry but still add a child' => [
            [
                self::createLocalEntry(
                    'app',
                    path: 'app.js',
                ),
                self::createLocalEntry(
                    'imports_simple',
                    path: 'imports_simple.js',
                ),
            ],
            [
                new MappedAsset(
                    'app.js',
                    publicPath: '/assets/app-d1g3st.js',
                    javaScriptImports: [new JavaScriptImport('imports_simple', assetLogicalPath: $eagerImportsSimpleAsset->logicalPath, assetSourcePath: $eagerImportsSimpleAsset->logicalPath, isLazy: true, addImplicitlyToImportMap: false)]
                ),
                $eagerImportsSimpleAsset,
                $simpleAsset,
            ],
            [
                'app' => [
                    'path' => '/assets/app-d1g3st.js',
                    'type' => 'js',
                ],
                '/assets/simple.js' => [
                    'path' => '/assets/simple-d1g3st.js',
                    'type' => 'js',
                ],
                'imports_simple' => [
                    'path' => '/assets/imports_simple-d1g3st.js',
                    'type' => 'js',
                ],
            ],
        ];

        yield 'imports with a module name are not added to the importmap' => [
            [
                self::createLocalEntry(
                    'app',
                    path: 'app.js',
                ),
            ],
            [
                new MappedAsset(
                    'app.js',
                    publicPath: '/assets/app-d1g3st.js',
                    javaScriptImports: [new JavaScriptImport('simple', assetLogicalPath: $simpleAsset->logicalPath, assetSourcePath: $simpleAsset->sourcePath, isLazy: false)]
                ),
                $simpleAsset,
            ],
            [
                'app' => [
                    'path' => '/assets/app-d1g3st.js',
                    'type' => 'js',
                ],
            ],
        ];

        yield 'it does not process dependencies of CSS files' => [
            [
                self::createLocalEntry(
                    'app.css',
                    path: 'app.css',
                    type: ImportMapType::CSS,
                ),
            ],
            [
                new MappedAsset(
                    'app.css',
                    publicPath: '/assets/app-d1g3st.css',
                    javaScriptImports: [new JavaScriptImport('/assets/simple.js', assetLogicalPath: $simpleAsset->logicalPath, assetSourcePath: $simpleAsset->sourcePath)]
                ),
            ],
            [
                'app.css' => [
                    'path' => '/assets/app-d1g3st.css',
                    'type' => 'css',
                ],
            ],
        ];

        yield 'it handles a relative path file' => [
            [
                self::createLocalEntry(
                    'app',
                    path: './assets/app.js',
                ),
            ],
            [
                new MappedAsset(
                    'app.js',
                    // /fake/root is the mocked root directory
                    '/fake/root/assets/app.js',
                    publicPath: '/assets/app-d1g3st.js',
                ),
            ],
            [
                'app' => [
                    'path' => '/assets/app-d1g3st.js',
                    'type' => 'js',
                ],
            ],
        ];

        yield 'it handles an absolute path file' => [
            [
                self::createLocalEntry(
                    'app',
                    path: '/some/path/assets/app.js',
                ),
            ],
            [
                new MappedAsset(
                    'app.js',
                    '/some/path/assets/app.js',
                    publicPath: '/assets/app-d1g3st.js',
                ),
            ],
            [
                'app' => [
                    'path' => '/assets/app-d1g3st.js',
                    'type' => 'js',
                ],
            ],
        ];
    }

    public function testGetRawImportMapDataExpandsBareImportedEntriesOnce()
    {
        $manager = $this->createImportMapGenerator();
        $this->mockImportMap([
            self::createLocalEntry('app', path: 'app.js'),
            self::createLocalEntry('module-a', path: 'module-a.js'),
            self::createLocalEntry('module-b', path: 'module-b.js'),
            self::createLocalEntry('shared', path: 'shared.js'),
        ]);

        $mappedAssets = [
            new MappedAsset(
                'app.js',
                publicPath: '/assets/app-d1g3st.js',
                javaScriptImports: [
                    new JavaScriptImport('module-a', assetLogicalPath: 'module-a.js', assetSourcePath: '/path/to/module-a.js', isLazy: false),
                    new JavaScriptImport('module-b', assetLogicalPath: 'module-b.js', assetSourcePath: '/path/to/module-b.js', isLazy: false),
                ],
            ),
            new MappedAsset(
                'module-a.js',
                publicPath: '/assets/module-a-d1g3st.js',
                javaScriptImports: [
                    new JavaScriptImport('shared', assetLogicalPath: 'shared.js', assetSourcePath: '/path/to/shared.js', isLazy: false),
                ],
            ),
            new MappedAsset(
                'module-b.js',
                publicPath: '/assets/module-b-d1g3st.js',
                javaScriptImports: [
                    new JavaScriptImport('shared', assetLogicalPath: 'shared.js', assetSourcePath: '/path/to/shared.js', isLazy: false),
                ],
            ),
            new MappedAsset(
                'shared.js',
                publicPath: '/assets/shared-d1g3st.js',
            ),
        ];
        $resolvedAssets = [];
        $this->mockAssetMapper($mappedAssets, $resolvedAssets);

        $this->assertSame([
            'app' => ['path' => '/assets/app-d1g3st.js', 'type' => 'js'],
            'module-a' => ['path' => '/assets/module-a-d1g3st.js', 'type' => 'js'],
            'module-b' => ['path' => '/assets/module-b-d1g3st.js', 'type' => 'js'],
            'shared' => ['path' => '/assets/shared-d1g3st.js', 'type' => 'js'],
        ], $manager->getRawImportMapData());
        $this->assertSame(1, $resolvedAssets['shared.js']);
    }

    public function testGetRawImportMapDataDoesNotExpandInterRootBareImportsTwice()
    {
        $manager = $this->createImportMapGenerator();
        $this->mockImportMap([
            self::createLocalEntry('app', path: 'app.js'),
            self::createLocalEntry('admin', path: 'admin.js'),
        ]);

        $mappedAssets = [
            new MappedAsset(
                'app.js',
                publicPath: '/assets/app-d1g3st.js',
                javaScriptImports: [
                    new JavaScriptImport('admin', assetLogicalPath: 'admin.js', assetSourcePath: '/path/to/admin.js', isLazy: false),
                ],
            ),
            new MappedAsset(
                'admin.js',
                publicPath: '/assets/admin-d1g3st.js',
            ),
        ];
        $resolvedAssets = [];
        $this->mockAssetMapper($mappedAssets, $resolvedAssets);

        $this->assertSame([
            'app' => ['path' => '/assets/app-d1g3st.js', 'type' => 'js'],
            'admin' => ['path' => '/assets/admin-d1g3st.js', 'type' => 'js'],
        ], $manager->getRawImportMapData());
        $this->assertSame(1, $resolvedAssets['admin.js']);
    }

    public function testGetRawImportDataUsesCacheFile()
    {
        $this->compiledConfigReader = $this->createMock(CompiledAssetMapperConfigReader::class);
        $manager = $this->createImportMapGenerator();
        $importmapData = [
            'app' => [
                'path' => 'app.js',
                'entrypoint' => true,
            ],
            '@hotwired/stimulus' => [
                'path' => 'https://anyurl.com/stimulus',
            ],
        ];
        $this->compiledConfigReader->expects($this->once())
            ->method('configExists')
            ->with('importmap.json')
            ->willReturn(true);
        $this->compiledConfigReader->expects($this->once())
            ->method('loadConfig')
            ->willReturn($importmapData);

        $this->assertEquals($importmapData, $manager->getRawImportMapData());
    }

    #[DataProvider('getEagerEntrypointImportsTests')]
    public function testFindEagerEntrypointImports(MappedAsset $entryAsset, array $expected, array $mappedAssets = [])
    {
        $manager = $this->createImportMapGenerator();
        $this->mockAssetMapper([$entryAsset, ...$mappedAssets]);
        // put the entry asset in the importmap
        $this->mockImportMap([
            ImportMapEntry::createLocal('the_entrypoint_name', ImportMapType::JS, $entryAsset->logicalPath, true),
        ]);

        $this->assertEquals($expected, $manager->findEagerEntrypointImports('the_entrypoint_name'));
    }

    public static function getEagerEntrypointImportsTests(): iterable
    {
        yield 'an entry with no dependencies' => [
            new MappedAsset(
                'app.js',
                publicPath: '/assets/app.js',
            ),
            [],
        ];

        $simpleAsset = new MappedAsset(
            'simple.js',
            '/path/to/simple.js',
            publicPathWithoutDigest: '/assets/simple.js',
        );
        yield 'an entry with a non-lazy dependency is included' => [
            new MappedAsset(
                'app.js',
                publicPath: '/assets/app.js',
                javaScriptImports: [new JavaScriptImport('/assets/simple.js', assetLogicalPath: $simpleAsset->logicalPath, assetSourcePath: $simpleAsset->sourcePath, isLazy: false)]
            ),
            ['/assets/simple.js'], // path is the key in the importmap
            [$simpleAsset],
        ];

        yield 'an entry with a non-lazy dependency with module name is included' => [
            new MappedAsset(
                'app.js',
                publicPath: '/assets/app.js',
                javaScriptImports: [new JavaScriptImport('simple', assetLogicalPath: $simpleAsset->logicalPath, assetSourcePath: $simpleAsset->sourcePath, isLazy: false)]
            ),
            ['simple'], // path is the key in the importmap
            [$simpleAsset],
        ];

        yield 'an entry with a lazy dependency is not included' => [
            new MappedAsset(
                'app.js',
                publicPath: '/assets/app.js',
                javaScriptImports: [new JavaScriptImport('/assets/simple.js', assetLogicalPath: $simpleAsset->logicalPath, assetSourcePath: $simpleAsset->sourcePath, isLazy: true)]
            ),
            [],
            [$simpleAsset],
        ];

        $importsSimpleAsset = new MappedAsset(
            'imports_simple.js',
            '/path/to/imports_simple.js',
            publicPathWithoutDigest: '/assets/imports_simple.js',
            javaScriptImports: [new JavaScriptImport('/assets/simple.js', assetLogicalPath: $simpleAsset->logicalPath, assetSourcePath: $simpleAsset->sourcePath, isLazy: false)]
        );
        yield 'an entry follows through dependencies recursively' => [
            new MappedAsset(
                'app.js',
                publicPath: '/assets/app.js',
                javaScriptImports: [new JavaScriptImport('/assets/imports_simple.js', assetLogicalPath: $importsSimpleAsset->logicalPath, assetSourcePath: $importsSimpleAsset->sourcePath, isLazy: false)]
            ),
            ['/assets/imports_simple.js', '/assets/simple.js'],
            [$simpleAsset, $importsSimpleAsset],
        ];

        $importsSimpleAsset2 = new MappedAsset(
            'imports_simple2.js',
            '/path/to/imports_simple2.js',
            publicPathWithoutDigest: '/assets/imports_simple2.js',
            javaScriptImports: [new JavaScriptImport('/assets/simple.js', assetLogicalPath: $simpleAsset->logicalPath, assetSourcePath: $simpleAsset->sourcePath, isLazy: false)]
        );
        yield 'an entry recursive dependencies are deduplicated' => [
            new MappedAsset(
                'app.js',
                publicPath: '/assets/app.js',
                javaScriptImports: [
                    new JavaScriptImport('/assets/imports_simple.js', assetLogicalPath: $importsSimpleAsset->logicalPath, assetSourcePath: $importsSimpleAsset->sourcePath, isLazy: false),
                    new JavaScriptImport('/assets/imports_simple2.js', assetLogicalPath: $importsSimpleAsset2->logicalPath, assetSourcePath: $importsSimpleAsset2->sourcePath, isLazy: false),
                ]
            ),
            ['/assets/imports_simple.js', '/assets/imports_simple2.js', '/assets/simple.js'],
            [$simpleAsset, $importsSimpleAsset, $importsSimpleAsset2],
        ];
    }

    public function testFindEagerEntrypointImportsUsesCacheFile()
    {
        $this->compiledConfigReader = $this->createMock(CompiledAssetMapperConfigReader::class);
        $manager = $this->createImportMapGenerator();
        $entrypointData = [
            'app',
            '/assets/foo.js',
        ];
        $this->compiledConfigReader->expects($this->once())
            ->method('configExists')
            ->with('entrypoint.foo.json')
            ->willReturn(true);
        $this->compiledConfigReader->expects($this->once())
            ->method('loadConfig')
            ->willReturn($entrypointData);

        $this->assertEquals($entrypointData, $manager->findEagerEntrypointImports('foo'));
    }

    public function testFindReachableEntrypointImports()
    {
        $manager = $this->createImportMapGenerator();

        $deep = new MappedAsset('deep.js', '/path/to/deep.js', publicPathWithoutDigest: '/assets/deep.js');
        $lazy = new MappedAsset(
            'lazy.js',
            '/path/to/lazy.js',
            publicPathWithoutDigest: '/assets/lazy.js',
            javaScriptImports: [new JavaScriptImport('/assets/deep.js', assetLogicalPath: $deep->logicalPath, assetSourcePath: $deep->sourcePath, isLazy: false)],
        );
        $eager = new MappedAsset(
            'eager.js',
            '/path/to/eager.js',
            publicPathWithoutDigest: '/assets/eager.js',
            javaScriptImports: [new JavaScriptImport('/assets/deep.js', assetLogicalPath: $deep->logicalPath, assetSourcePath: $deep->sourcePath, isLazy: true)],
        );
        $entrypoint = new MappedAsset(
            'app.js',
            '/path/to/app.js',
            publicPath: '/assets/app.js',
            javaScriptImports: [
                new JavaScriptImport('/assets/eager.js', assetLogicalPath: $eager->logicalPath, assetSourcePath: $eager->sourcePath, isLazy: false),
                new JavaScriptImport('/assets/lazy.js', assetLogicalPath: $lazy->logicalPath, assetSourcePath: $lazy->sourcePath, isLazy: true),
            ],
        );

        $this->mockAssetMapper([$entrypoint, $eager, $lazy, $deep]);
        $this->mockImportMap([
            ImportMapEntry::createLocal('the_entrypoint_name', ImportMapType::JS, $entrypoint->logicalPath, true),
        ]);

        $this->assertSame(['/assets/eager.js'], $manager->findEagerEntrypointImports('the_entrypoint_name'));
        $this->assertSame(['/assets/eager.js', '/assets/lazy.js', '/assets/deep.js'], $manager->findReachableEntrypointImports('the_entrypoint_name'));
    }

    public function testGetRawImportMapDataResolvesEachEntryOnce()
    {
        $manager = $this->createImportMapGenerator();
        $this->mockImportMap([
            self::createLocalEntry('app', path: 'app.js'),
            self::createLocalEntry('admin', path: 'admin.js'),
        ]);

        $resolvedAssets = [];
        $this->mockAssetMapper([
            new MappedAsset('app.js', publicPath: '/assets/app-d1g3st.js'),
            new MappedAsset('admin.js', publicPath: '/assets/admin-d1g3st.js'),
        ], $resolvedAssets);

        $this->assertSame([
            'app' => ['path' => '/assets/app-d1g3st.js', 'type' => 'js'],
            'admin' => ['path' => '/assets/admin-d1g3st.js', 'type' => 'js'],
        ], $manager->getRawImportMapData());
        $this->assertSame(['app.js' => 1, 'admin.js' => 1], $resolvedAssets);
    }

    private function createImportMapGenerator(array $integrityHashAlgorithms = [], string $entries = ImportMapGenerator::ENTRIES_ALL): ImportMapGenerator
    {
        $this->compiledConfigReader ??= $this->createStub(CompiledAssetMapperConfigReader::class);
        $this->assetMapper = $this->createStub(AssetMapperInterface::class);
        $this->configReader = $this->createStub(ImportMapConfigReader::class);

        // mock this to behave like normal
        $this->configReader
            ->method('createRemoteEntry')
            ->willReturnCallback(static function (string $importName, ImportMapType $type, string $version, string $packageModuleSpecifier, bool $isEntrypoint) {
                $path = '/path/to/vendor/'.$packageModuleSpecifier.'.js';

                return ImportMapEntry::createRemote($importName, $type, $path, $version, $packageModuleSpecifier, $isEntrypoint);
            });

        return new ImportMapGenerator(
            $this->assetMapper,
            $this->compiledConfigReader,
            $this->configReader,
            $integrityHashAlgorithms,
            $entries,
        );
    }

    private function mockImportMap(array $importMapEntries): void
    {
        $importMapEntries = new ImportMapEntries($importMapEntries);

        $this->configReader
            ->method('getEntries')
            ->willReturn($importMapEntries)
        ;
        $this->configReader
            ->method('findRootImportMapEntry')
            ->willReturnCallback(static fn (string $moduleName): ?ImportMapEntry => $importMapEntries->has($moduleName) ? $importMapEntries->get($moduleName) : null)
        ;
    }

    private static function createLocalEntry(string $importName, string $path, ImportMapType $type = ImportMapType::JS, bool $isEntrypoint = false): ImportMapEntry
    {
        return ImportMapEntry::createLocal($importName, $type, $path, $isEntrypoint);
    }

    private static function createRemoteEntry(string $importName, string $version, ?string $path = null, ImportMapType $type = ImportMapType::JS, ?string $packageSpecifier = null): ImportMapEntry
    {
        $packageSpecifier ??= $importName;
        $path ??= '/vendor/any-path.js';

        return ImportMapEntry::createRemote($importName, $type, $path, $version, $packageSpecifier, false);
    }

    /**
     * @param MappedAsset[]      $mappedAssets
     * @param array<string, int> $resolvedAssets Filled with the number of times each logical path was resolved
     */
    private function mockAssetMapper(array $mappedAssets, array &$resolvedAssets = []): void
    {
        $this->assetMapper
            ->method('getAsset')
            ->willReturnCallback(static function (string $logicalPath) use ($mappedAssets, &$resolvedAssets) {
                $resolvedAssets[$logicalPath] = ($resolvedAssets[$logicalPath] ?? 0) + 1;

                foreach ($mappedAssets as $asset) {
                    if ($asset->logicalPath === $logicalPath) {
                        return $asset;
                    }
                }

                return null;
            })
        ;

        $this->assetMapper
            ->method('getAssetFromSourcePath')
            ->willReturnCallback(static function (string $sourcePath) use ($mappedAssets) {
                // collapse ../ in paths and ./ in paths to mimic the realpath AssetMapper uses
                $unCollapsePath = static function (string $path) {
                    $parts = explode('/', $path);
                    $newParts = [];
                    foreach ($parts as $part) {
                        if ('..' === $part) {
                            array_pop($newParts);

                            continue;
                        }

                        if ('.' !== $part) {
                            $newParts[] = $part;
                        }
                    }

                    return implode('/', $newParts);
                };

                $sourcePath = $unCollapsePath($sourcePath);

                foreach ($mappedAssets as $asset) {
                    if (isset($asset->sourcePath) && $unCollapsePath($asset->sourcePath) === $sourcePath) {
                        return $asset;
                    }
                }

                return null;
            })
        ;
    }
}
