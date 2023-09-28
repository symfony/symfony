<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper\Tests\Compiler;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\AssetMapper\Compiler\AssetCompilerInterface;
use Symfony\Component\AssetMapper\Compiler\JavaScriptImportPathCompiler;
use Symfony\Component\AssetMapper\Exception\CircularAssetsException;
use Symfony\Component\AssetMapper\Exception\RuntimeException;
use Symfony\Component\AssetMapper\ImportMap\ImportMapEntry;
use Symfony\Component\AssetMapper\ImportMap\ImportMapManager;
use Symfony\Component\AssetMapper\MappedAsset;

class JavaScriptImportPathCompilerTest extends TestCase
{
    /**
     * @dataProvider provideCompileTests
     */
    public function testCompile(string $sourceLogicalName, string $input, array $expectedJavaScriptImports)
    {
        $asset = new MappedAsset($sourceLogicalName, 'anything', '/assets/'.$sourceLogicalName);

        $importMapManager = $this->createMock(ImportMapManager::class);
        $importMapManager->expects($this->any())
            ->method('findRootImportMapEntry')
            ->willReturnCallback(function ($importName) {
                if ('module_in_importmap_local_asset' === $importName) {
                    return new ImportMapEntry('module_in_importmap_local_asset', 'module_in_importmap_local_asset.js');
                }

                if ('module_in_importmap_remote' === $importName) {
                    return new ImportMapEntry('module_in_importmap_local_asset', version: '1.2.3');
                }

                return null;
            });
        $compiler = new JavaScriptImportPathCompiler($importMapManager);
        // compile - and check that content doesn't change
        $this->assertSame($input, $compiler->compile($input, $asset, $this->createAssetMapper()));
        $actualImports = [];
        foreach ($asset->getJavaScriptImports() as $import) {
            $actualImports[$import->importName] = ['lazy' => $import->isLazy, 'asset' => $import->asset?->logicalPath, 'add' => $import->addImplicitlyToImportMap];
        }
        $this->assertEquals($expectedJavaScriptImports, $actualImports);
    }

    public static function provideCompileTests(): iterable
    {
        yield 'dynamic_simple_double_quotes' => [
            'sourceLogicalName' => 'app.js',
            'input' => 'import("./other.js");',
            'expectedJavaScriptImports' => ['/assets/other.js' => ['lazy' => true, 'asset' => 'other.js', 'add' => true]],
        ];

        yield 'dynamic_simple_multiline' => [
            'sourceLogicalName' => 'app.js',
            'input' => <<<EOF
                const fun;
                import("./other.js");
                EOF
            ,
            'expectedJavaScriptImports' => ['/assets/other.js' => ['lazy' => true, 'asset' => 'other.js', 'add' => true]],
        ];

        yield 'dynamic_simple_single_quotes' => [
            'sourceLogicalName' => 'app.js',
            'input' => 'import(\'./other.js\');',
            'expectedJavaScriptImports' => ['/assets/other.js' => ['lazy' => true, 'asset' => 'other.js', 'add' => true]],
        ];

        yield 'dynamic_simple_tick_quotes' => [
            'sourceLogicalName' => 'app.js',
            'input' => 'import(`./other.js`);',
            'expectedJavaScriptImports' => ['/assets/other.js' => ['lazy' => true, 'asset' => 'other.js', 'add' => true]],
        ];

        yield 'dynamic_resolves_multiple' => [
            'sourceLogicalName' => 'app.js',
            'input' => 'import("./other.js"); import("./subdir/foo.js");',
            'expectedJavaScriptImports' => [
                '/assets/other.js' => ['lazy' => true, 'asset' => 'other.js', 'add' => true],
                '/assets/subdir/foo.js' => ['lazy' => true, 'asset' => 'subdir/foo.js', 'add' => true],
            ],
        ];

        yield 'dynamic_resolves_dynamic_imports_later_in_file' => [
            'sourceLogicalName' => 'app.js',
            'input' => "console.log('Hello test!');\n import('./subdir/foo.js').then(() => console.log('inside promise!'));",
            'expectedJavaScriptImports' => [
                '/assets/subdir/foo.js' => ['lazy' => true, 'asset' => 'subdir/foo.js', 'add' => true],
            ],
        ];

        yield 'dynamic_correctly_moves_to_higher_directories' => [
            'sourceLogicalName' => 'subdir/app.js',
            'input' => 'import("../other.js");',
            'expectedJavaScriptImports' => ['/assets/other.js' => ['lazy' => true, 'asset' => 'other.js', 'add' => true]],
        ];

        yield 'static_named_import_double_quotes' => [
            'sourceLogicalName' => 'app.js',
            'input' => 'import { myFunction } from "./other.js";',
            'expectedJavaScriptImports' => ['/assets/other.js' => ['lazy' => false, 'asset' => 'other.js', 'add' => true]],
        ];

        yield 'static_named_import_single_quotes' => [
            'sourceLogicalName' => 'app.js',
            'input' => 'import { myFunction } from \'./other.js\';',
            'expectedJavaScriptImports' => ['/assets/other.js' => ['lazy' => false, 'asset' => 'other.js', 'add' => true]],
        ];

        yield 'static_default_import' => [
            'sourceLogicalName' => 'app.js',
            'input' => 'import myFunction from "./other.js";',
            'expectedJavaScriptImports' => ['/assets/other.js' => ['lazy' => false, 'asset' => 'other.js', 'add' => true]],
        ];

        yield 'static_default_and_named_import' => [
            'sourceLogicalName' => 'app.js',
            'input' => 'import myFunction, { helperFunction } from "./other.js";',
            'expectedJavaScriptImports' => ['/assets/other.js' => ['lazy' => false, 'asset' => 'other.js', 'add' => true]],
        ];

        yield 'static_import_everything' => [
            'sourceLogicalName' => 'app.js',
            'input' => 'import * as myModule from "./other.js";',
            'expectedJavaScriptImports' => ['/assets/other.js' => ['lazy' => false, 'asset' => 'other.js', 'add' => true]],
        ];

        yield 'static_import_just_for_side_effects' => [
            'sourceLogicalName' => 'app.js',
            'input' => 'import "./other.js";',
            'expectedJavaScriptImports' => ['/assets/other.js' => ['lazy' => false, 'asset' => 'other.js', 'add' => true]],
        ];

        yield 'mix_of_static_and_dynamic_imports' => [
            'sourceLogicalName' => 'app.js',
            'input' => 'import "./other.js"; import("./subdir/foo.js");',
            'expectedJavaScriptImports' => [
                '/assets/other.js' => ['lazy' => false, 'asset' => 'other.js', 'add' => true],
                '/assets/subdir/foo.js' => ['lazy' => true, 'asset' => 'subdir/foo.js', 'add' => true],
            ],
        ];

        yield 'extra_import_word_does_not_cause_issues' => [
            'sourceLogicalName' => 'app.js',
            'input' => "// about to do an import\nimport('./other.js');",
            'expectedJavaScriptImports' => ['/assets/other.js' => ['lazy' => true, 'asset' => 'other.js', 'add' => true]],
        ];

        yield 'import_on_one_line_then_module_name_on_next_is_ok' => [
            'sourceLogicalName' => 'app.js',
            'input' => "import \n    './other.js';",
            'expectedJavaScriptImports' => ['/assets/other.js' => ['lazy' => false, 'asset' => 'other.js', 'add' => true]],
        ];

        yield 'importing_a_css_file_is_included' => [
            'sourceLogicalName' => 'app.js',
            'input' => "import './styles.css';",
            'expectedJavaScriptImports' => ['/assets/styles.css' => ['lazy' => false, 'asset' => 'styles.css', 'add' => true]],
        ];

        yield 'importing_non_existent_file_without_strict_mode_is_ignored_still_listed_as_an_import' => [
            'sourceLogicalName' => 'app.js',
            'input' => "import './non-existent.js';",
            'expectedJavaScriptImports' => ['./non-existent.js' => ['lazy' => false, 'asset' => null, 'add' => false]],
        ];

        yield 'single_line_comment_at_start_ignored' => [
            'sourceLogicalName' => 'app.js',
            'input' => <<<EOF
                const fun;
                // import("./other.js");
                EOF
            ,
            'expectedJavaScriptImports' => [],
        ];

        yield 'single_line_comment_with_whitespace_before_is_ignored' => [
            'sourceLogicalName' => 'app.js',
            'input' => <<<EOF
                const fun;
                 // import("./other.js");
                EOF
            ,
            'expectedJavaScriptImports' => [],
        ];

        yield 'single_line_comment_with_more_text_before_import_ignored' => [
            'sourceLogicalName' => 'app.js',
            'input' => <<<EOF
                const fun;
                // this is not going to be parsed import("./other.js");
                EOF
            ,
            'expectedJavaScriptImports' => [],
        ];

        yield 'single_line_comment_not_at_start_is_parsed' => [
            'sourceLogicalName' => 'app.js',
            'input' => <<<EOF
                const fun;
                console.log('// I am not really a comment'); import("./other.js");
                EOF
            ,
            'expectedJavaScriptImports' => ['/assets/other.js' => ['lazy' => true, 'asset' => 'other.js', 'add' => true]],
        ];

        yield 'multi_line_comment_with_start_and_end_before_import_is_found' => [
            'sourceLogicalName' => 'app.js',
            'input' => <<<EOF
                const fun;
                /* comment */ import("./other.js");
                EOF
            ,
            'expectedJavaScriptImports' => ['/assets/other.js' => ['lazy' => true, 'asset' => 'other.js', 'add' => true]],
        ];

        yield 'multi_line_comment_with_import_between_start_and_end_ignored' => [
            'sourceLogicalName' => 'app.js',
            'input' => <<<EOF
                const fun;
                    /* comment import("./other.js"); */
                EOF
            ,
            'expectedJavaScriptImports' => [],
        ];

        yield 'multi_line_comment_with_no_end_parsed_for_safety' => [
            'sourceLogicalName' => 'app.js',
            'input' => <<<EOF
                const fun;
                    /* comment import("./other.js");
                EOF
            ,
            'expectedJavaScriptImports' => ['/assets/other.js' => ['lazy' => true, 'asset' => 'other.js', 'add' => true]],
        ];

        yield 'multi_line_comment_with_no_end_found_eventually_ignored' => [
            'sourceLogicalName' => 'app.js',
            'input' => <<<EOF
                const fun;
                    /* comment import("./other.js");
                    and more
                    */
                EOF
            ,
            'expectedJavaScriptImports' => [],
        ];

        yield 'multi_line_comment_with_text_before_is_parsed' => [
            'sourceLogicalName' => 'app.js',
            'input' => <<<EOF
                const fun;
                    console.log('/* not a comment'); import("./other.js");
                EOF
            ,
            'expectedJavaScriptImports' => ['/assets/other.js' => ['lazy' => true, 'asset' => 'other.js', 'add' => true]],
        ];

        yield 'bare_import_not_in_importmap' => [
            'sourceLogicalName' => 'app.js',
            'input' => 'import "some_module";',
            'expectedJavaScriptImports' => ['some_module' => ['lazy' => false, 'asset' => null, 'add' => false]],
        ];

        yield 'bare_import_in_importmap_with_local_asset' => [
            'sourceLogicalName' => 'app.js',
            'input' => 'import "module_in_importmap_local_asset";',
            'expectedJavaScriptImports' => ['module_in_importmap_local_asset' => ['lazy' => false, 'asset' => 'module_in_importmap_local_asset.js', 'add' => false]],
        ];

        yield 'bare_import_in_importmap_but_remote' => [
            'sourceLogicalName' => 'app.js',
            'input' => 'import "module_in_importmap_remote";',
            'expectedJavaScriptImports' => ['module_in_importmap_remote' => ['lazy' => false, 'asset' => null, 'add' => false]],
        ];

        yield 'absolute_import_added_as_dependency_only' => [
            'sourceLogicalName' => 'app.js',
            'input' => 'import "https://example.com/module.js";',
            'expectedJavaScriptImports' => ['https://example.com/module.js' => ['lazy' => false, 'asset' => null, 'add' => false]],
        ];
    }

    /**
     * @dataProvider providePathsCanUpdateTests
     */
    public function testImportPathsCanUpdate(string $sourceLogicalName, string $input, string $sourcePublicPath, string $importedPublicPath, string $expectedOutput)
    {
        $asset = new MappedAsset($sourceLogicalName, publicPathWithoutDigest: $sourcePublicPath);

        $assetMapper = $this->createMock(AssetMapperInterface::class);
        $importedAsset = new MappedAsset('anything', publicPathWithoutDigest: $importedPublicPath);
        $assetMapper->expects($this->once())
            ->method('getAsset')
            ->willReturn($importedAsset);

        $compiler = new JavaScriptImportPathCompiler($this->createMock(ImportMapManager::class));
        $this->assertSame($expectedOutput, $compiler->compile($input, $asset, $assetMapper));
    }

    public static function providePathsCanUpdateTests(): iterable
    {
        yield 'simple - no change needed' => [
            'sourceLogicalName' => 'app.js',
            'input' => "import './other.js';",
            'sourcePublicPath' => '/assets/app.js',
            'importedPublicPath' => '/assets/other.js',
            'expectedOutput' => "import './other.js';",
        ];

        yield 'same directory - no change needed' => [
            'sourceLogicalName' => 'app.js',
            'input' => "import './other.js';",
            'sourcePublicPath' => '/assets/js/app.js',
            'importedPublicPath' => '/assets/js/other.js',
            'expectedOutput' => "import './other.js';",
        ];

        yield 'different directories but not adjustment needed' => [
            'sourceLogicalName' => 'app.js',
            'input' => "import './subdir/other.js';",
            'sourcePublicPath' => '/assets/app.js',
            'importedPublicPath' => '/assets/subdir/other.js',
            'expectedOutput' => "import './subdir/other.js';",
        ];

        yield 'sourcePublicPath is deeper than expected so adjustment is made' => [
            'sourceLogicalName' => 'app.js',
            'input' => "import './other.js';",
            'sourcePublicPath' => '/assets/js/app.js',
            'importedPublicPath' => '/assets/other.js',
            'expectedOutput' => "import '../other.js';",
        ];

        yield 'importedPublicPath is different so adjustment is made' => [
            'sourceLogicalName' => 'app.js',
            'input' => "import './other.js';",
            'sourcePublicPath' => '/assets/app.js',
            'importedPublicPath' => '/assets/js/other.js',
            'expectedOutput' => "import './js/other.js';",
        ];

        yield 'both paths are in unexpected places so adjustment is made' => [
            'sourceLogicalName' => 'app.js',
            'input' => "import './other.js';",
            'sourcePublicPath' => '/assets/js/app.js',
            'importedPublicPath' => '/assets/somewhere/other.js',
            'expectedOutput' => "import '../somewhere/other.js';",
        ];
    }

    /**
     * @dataProvider provideMissingImportModeTests
     */
    public function testMissingImportMode(string $sourceLogicalName, string $input, ?string $expectedExceptionMessage)
    {
        if (null !== $expectedExceptionMessage) {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage($expectedExceptionMessage);
        }

        $asset = new MappedAsset($sourceLogicalName, '/path/to/app.js');

        $logger = $this->createMock(LoggerInterface::class);
        $compiler = new JavaScriptImportPathCompiler(
            $this->createMock(ImportMapManager::class),
            AssetCompilerInterface::MISSING_IMPORT_STRICT,
            $logger
        );
        $this->assertSame($input, $compiler->compile($input, $asset, $this->createAssetMapper()));
    }

    public static function provideMissingImportModeTests(): iterable
    {
        yield 'importing_non_existent_file_throws_exception' => [
            'sourceLogicalName' => 'app.js',
            'input' => "import './non-existent.js';",
            'expectedExceptionMessage' => 'Unable to find asset "./non-existent.js" imported from "/path/to/app.js".',
        ];

        yield 'importing_file_just_missing_js_extension_adds_extra_info' => [
            'sourceLogicalName' => 'app.js',
            'input' => "import './other';",
            'expectedExceptionMessage' => 'Unable to find asset "./other" imported from "/path/to/app.js". Try adding ".js" to the end of the import - i.e. "./other.js".',
        ];

        yield 'importing_absolute_file_path_is_ignored' => [
            'sourceLogicalName' => 'app.js',
            'input' => "import '/path/to/other.js';",
            'expectedExceptionMessage' => null,
        ];

        yield 'importing_a_url_is_ignored' => [
            'sourceLogicalName' => 'app.js',
            'input' => "import 'https://example.com/other.js';",
            'expectedExceptionMessage' => null,
        ];
    }

    public function testErrorMessageAvoidsCircularException()
    {
        $assetMapper = $this->createMock(AssetMapperInterface::class);
        $assetMapper->expects($this->any())
            ->method('getAsset')
            ->willReturnCallback(function ($logicalPath) {
                if ('htmx' === $logicalPath) {
                    return null;
                }

                if ('htmx.js' === $logicalPath) {
                    throw new CircularAssetsException();
                }
            });

        $asset = new MappedAsset('htmx.js', '/path/to/app.js');
        $compiler = new JavaScriptImportPathCompiler($this->createMock(ImportMapManager::class));
        $content = '//** @type {import("./htmx").HtmxApi} */';
        $compiled = $compiler->compile($content, $asset, $assetMapper);
        // To form a good exception message, the compiler will check for the
        // htmx.js asset, which will throw a CircularAssetsException. This
        // should not be caught.
        $this->assertSame($content, $compiled);
    }

    private function createAssetMapper(): AssetMapperInterface
    {
        $assetMapper = $this->createMock(AssetMapperInterface::class);
        $assetMapper->expects($this->any())
            ->method('getAsset')
            ->willReturnCallback(function ($path) {
                return match ($path) {
                    'other.js' => new MappedAsset('other.js', publicPathWithoutDigest: '/assets/other.js'),
                    'subdir/foo.js' => new MappedAsset('subdir/foo.js', publicPathWithoutDigest: '/assets/subdir/foo.js'),
                    'styles.css' => new MappedAsset('styles.css', publicPathWithoutDigest: '/assets/styles.css'),
                    'module_in_importmap_local_asset.js' => new MappedAsset('module_in_importmap_local_asset.js', publicPathWithoutDigest: '/assets/module_in_importmap_local_asset.js'),
                    default => null,
                };
            });

        return $assetMapper;
    }
}
