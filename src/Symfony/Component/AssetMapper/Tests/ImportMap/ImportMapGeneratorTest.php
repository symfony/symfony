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
use Symfony\Component\AssetMapper\CompiledAssetMapperConfigReader;
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
    private AssetMapperInterface&MockObject $assetMapper;
    private CompiledAssetMapperConfigReader&MockObject $compiledConfigReader;
    private ImportMapConfigReader&MockObject $configReader;

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
            ImportMapEntry::createLocal('entry1', ImportMapType::JS, path: '/any', isEntrypoint: true),
            ImportMapEntry::createLocal('entry2', ImportMapType::JS, path: '/any', isEntrypoint: true),
            ImportMapEntry::createLocal('not_entrypoint', ImportMapType::JS, path: '/any', isEntrypoint: false),
        ]);

        $this->assertEquals(['entry1', 'entry2'], $manager->getEntrypointNames());
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

    /**
     * @dataProvider getRawImportMapDataTests
     */
    public function testGetRawImportMapData(array $importMapEntries, array $mappedAssets, array $expectedData)
    {
        $manager = $this->createImportMapGenerator();
        $this->mockImportMap($importMapEntries);
        $this->mockAssetMapper($mappedAssets);
        $this->configReader->expects($this->any())
            ->method('convertPathToFilesystemPath')
            ->willReturnCallback(function (string $path) {
                if (!str_starts_with($path, '.')) {
                    return $path;
                }

                return Path::join('/fake/root', $path);
            });

        $this->assertEquals($expectedData, $manager->getRawImportMapData());
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

    public function testGetRawImportDataUsesCacheFile()
    {
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

    /**
     * @dataProvider getEagerEntrypointImportsTests
     */
    public function testFindEagerEntrypointImports(MappedAsset $entryAsset, array $expected, array $mappedAssets = [])
    {
        $manager = $this->createImportMapGenerator();
        $this->mockAssetMapper([$entryAsset, ...$mappedAssets]);
        // put the entry asset in the importmap
        $this->mockImportMap([
            ImportMapEntry::createLocal('the_entrypoint_name', ImportMapType::JS, path: $entryAsset->logicalPath, isEntrypoint: true),
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

    private function createImportMapGenerator(): ImportMapGenerator
    {
        $this->compiledConfigReader = $this->createMock(CompiledAssetMapperConfigReader::class);
        $this->assetMapper = $this->createMock(AssetMapperInterface::class);
        $this->configReader = $this->createMock(ImportMapConfigReader::class);

        // mock this to behave like normal
        $this->configReader->expects($this->any())
            ->method('createRemoteEntry')
            ->willReturnCallback(function (string $importName, ImportMapType $type, string $version, string $packageModuleSpecifier, bool $isEntrypoint) {
                $path = '/path/to/vendor/'.$packageModuleSpecifier.'.js';

                return ImportMapEntry::createRemote($importName, $type, $path, $version, $packageModuleSpecifier, $isEntrypoint);
            });

        return new ImportMapGenerator(
            $this->assetMapper,
            $this->compiledConfigReader,
            $this->configReader,
        );
    }

    private function mockImportMap(array $importMapEntries): void
    {
        $this->configReader->expects($this->any())
            ->method('getEntries')
            ->willReturn(new ImportMapEntries($importMapEntries))
        ;
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

    /**
     * @param MappedAsset[] $mappedAssets
     */
    private function mockAssetMapper(array $mappedAssets): void
    {
        $this->assetMapper->expects($this->any())
            ->method('getAsset')
            ->willReturnCallback(function (string $logicalPath) use ($mappedAssets) {
                foreach ($mappedAssets as $asset) {
                    if ($asset->logicalPath === $logicalPath) {
                        return $asset;
                    }
                }

                return null;
            })
        ;

        $this->assetMapper->expects($this->any())
            ->method('getAssetFromSourcePath')
            ->willReturnCallback(function (string $sourcePath) use ($mappedAssets) {
                // collapse ../ in paths and ./ in paths to mimic the realpath AssetMapper uses
                $unCollapsePath = function (string $path) {
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
