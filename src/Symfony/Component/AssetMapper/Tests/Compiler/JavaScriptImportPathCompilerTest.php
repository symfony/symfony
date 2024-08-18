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
use Symfony\Component\AssetMapper\ImportMap\ImportMapConfigReader;
use Symfony\Component\AssetMapper\ImportMap\ImportMapEntry;
use Symfony\Component\AssetMapper\ImportMap\ImportMapType;
use Symfony\Component\AssetMapper\MappedAsset;

class JavaScriptImportPathCompilerTest extends TestCase
{
    /**
     * @dataProvider provideCompileTests
     */
    public function testCompileFindsCorrectImports(string $input, array $expectedJavaScriptImports)
    {
        $asset = new MappedAsset('app.js', '/project/assets/app.js', publicPathWithoutDigest: '/assets/app.js');

        $importMapConfigReader = $this->createMock(ImportMapConfigReader::class);
        $importMapConfigReader->expects($this->any())
            ->method('findRootImportMapEntry')
            ->willReturnCallback(function ($importName) {
                return match ($importName) {
                    'module_in_importmap_local_asset' => ImportMapEntry::createLocal('module_in_importmap_local_asset', ImportMapType::JS, 'module_in_importmap_local_asset.js', false),
                    'module_in_importmap_remote' => ImportMapEntry::createRemote('module_in_importmap_remote', ImportMapType::JS, './vendor/module_in_importmap_remote.js', '1.2.3', 'could_be_anything', false),
                    '@popperjs/core' => ImportMapEntry::createRemote('@popperjs/core', ImportMapType::JS, '/project/assets/vendor/@popperjs/core.js', '1.2.3', 'could_be_anything', false),
                    default => null,
                };
            });
        $importMapConfigReader->expects($this->any())
            ->method('convertPathToFilesystemPath')
            ->willReturnCallback(function ($path) {
                return match ($path) {
                    './vendor/module_in_importmap_remote.js' => '/project/assets/vendor/module_in_importmap_remote.js',
                    '/project/assets/vendor/@popperjs/core.js' => '/project/assets/vendor/@popperjs/core.js',
                    default => throw new \RuntimeException(\sprintf('Unexpected path "%s"', $path)),
                };
            });

        $assetMapper = $this->createMock(AssetMapperInterface::class);
        $assetMapper->expects($this->any())
            ->method('getAsset')
            ->willReturnCallback(function ($path) {
                return match ($path) {
                    'module_in_importmap_local_asset.js' => new MappedAsset('module_in_importmap_local_asset.js', '/can/be/anything.js', publicPathWithoutDigest: '/assets/module_in_importmap_local_asset.js'),
                    default => null,
                };
            });

        $assetMapper->expects($this->any())
            ->method('getAssetFromSourcePath')
            ->willReturnCallback(function ($path) {
                return match ($path) {
                    '/project/assets/foo.js' => new MappedAsset('foo.js', '/can/be/anything.js', publicPathWithoutDigest: '/assets/foo.js'),
                    '/project/assets/bootstrap.js' => new MappedAsset('bootstrap.js', '/can/be/anything.js', publicPathWithoutDigest: '/assets/bootstrap.js'),
                    '/project/assets/other.js' => new MappedAsset('other.js', '/can/be/anything.js', publicPathWithoutDigest: '/assets/other.js'),
                    '/project/assets/subdir/foo.js' => new MappedAsset('subdir/foo.js', '/can/be/anything.js', publicPathWithoutDigest: '/assets/subdir/foo.js'),
                    '/project/assets/styles/app.css' => new MappedAsset('styles/app.css', '/can/be/anything.js', publicPathWithoutDigest: '/assets/styles/app.css'),
                    '/project/assets/styles/app.scss' => new MappedAsset('styles/app.scss', '/can/be/anything.js', publicPathWithoutDigest: '/assets/styles/app.scss'),
                    '/project/assets/styles.css' => new MappedAsset('styles.css', '/can/be/anything.js', publicPathWithoutDigest: '/assets/styles.css'),
                    '/project/assets/vendor/module_in_importmap_remote.js' => new MappedAsset('module_in_importmap_remote.js', '/can/be/anything.js', publicPathWithoutDigest: '/assets/module_in_importmap_remote.js'),
                    '/project/assets/vendor/@popperjs/core.js' => new MappedAsset('assets/vendor/@popperjs/core.js', '/can/be/anything.js', publicPathWithoutDigest: '/assets/@popperjs/core.js'),
                    default => null,
                };
            });

        $compiler = new JavaScriptImportPathCompiler($importMapConfigReader);
        // compile - and check that content doesn't change
        $this->assertSame($input, $compiler->compile($input, $asset, $assetMapper));
        $actualImports = [];
        foreach ($asset->getJavaScriptImports() as $import) {
            $actualImports[$import->importName] = ['lazy' => $import->isLazy, 'asset' => $import->assetLogicalPath, 'add' => $import->addImplicitlyToImportMap];
        }

        $this->assertEquals($expectedJavaScriptImports, $actualImports);
    }

    public static function provideCompileTests(): iterable
    {
        yield 'standard_symfony_app_js' => [
            'input' => <<<EOF
            import './bootstrap.js';

            /*
             * Welcome to your app's main JavaScript file!
             *
             * This file will be included onto the page via the importmap() Twig function,
             * which should already be in your base.html.twig.
             */
            import './styles/app.css';

            console.log('This log comes from assets/app.js - welcome to AssetMapper! 🎉');
            EOF,
            'expectedJavaScriptImports' => [
                '/assets/bootstrap.js' => ['lazy' => false, 'asset' => 'bootstrap.js', 'add' => true],
                '/assets/styles/app.css' => ['lazy' => false, 'asset' => 'styles/app.css', 'add' => true],
            ],
        ];

        yield 'dynamic_simple_double_quotes' => [
            'input' => 'import("./other.js");',
            'expectedJavaScriptImports' => ['/assets/other.js' => ['lazy' => true, 'asset' => 'other.js', 'add' => true]],
        ];

        yield 'dynamic_simple_multiline' => [
            'input' => <<<EOF
                const fun;
                import("./other.js");
                EOF
            ,
            'expectedJavaScriptImports' => ['/assets/other.js' => ['lazy' => true, 'asset' => 'other.js', 'add' => true]],
        ];

        yield 'dynamic_simple_single_quotes' => [
            'input' => 'import(\'./other.js\');',
            'expectedJavaScriptImports' => ['/assets/other.js' => ['lazy' => true, 'asset' => 'other.js', 'add' => true]],
        ];

        yield 'dynamic_simple_tick_quotes' => [
            'input' => 'import(`./other.js`);',
            'expectedJavaScriptImports' => ['/assets/other.js' => ['lazy' => true, 'asset' => 'other.js', 'add' => true]],
        ];

        yield 'dynamic_resolves_multiple' => [
            'input' => 'import("./other.js"); import("./subdir/foo.js");',
            'expectedJavaScriptImports' => [
                '/assets/other.js' => ['lazy' => true, 'asset' => 'other.js', 'add' => true],
                '/assets/subdir/foo.js' => ['lazy' => true, 'asset' => 'subdir/foo.js', 'add' => true],
            ],
        ];

        yield 'dynamic_resolves_dynamic_imports_later_in_file' => [
            'input' => "console.log('Hello test!');\n import('./subdir/foo.js').then(() => console.log('inside promise!'));",
            'expectedJavaScriptImports' => [
                '/assets/subdir/foo.js' => ['lazy' => true, 'asset' => 'subdir/foo.js', 'add' => true],
            ],
        ];

        yield 'static_named_import_double_quotes' => [
            'input' => 'import { myFunction } from "./other.js";',
            'expectedJavaScriptImports' => ['/assets/other.js' => ['lazy' => false, 'asset' => 'other.js', 'add' => true]],
        ];

        yield 'static_named_import_single_quotes' => [
            'input' => 'import { myFunction } from \'./other.js\';',
            'expectedJavaScriptImports' => ['/assets/other.js' => ['lazy' => false, 'asset' => 'other.js', 'add' => true]],
        ];

        yield 'static_default_import' => [
            'input' => 'import myFunction from "./other.js";',
            'expectedJavaScriptImports' => ['/assets/other.js' => ['lazy' => false, 'asset' => 'other.js', 'add' => true]],
        ];

        yield 'static_default_and_named_import' => [
            'input' => 'import myFunction, { helperFunction } from "./other.js";',
            'expectedJavaScriptImports' => ['/assets/other.js' => ['lazy' => false, 'asset' => 'other.js', 'add' => true]],
        ];

        yield 'static_import_everything' => [
            'input' => 'import * as myModule from "./other.js";',
            'expectedJavaScriptImports' => ['/assets/other.js' => ['lazy' => false, 'asset' => 'other.js', 'add' => true]],
        ];

        yield 'static_import_just_for_side_effects' => [
            'input' => 'import "./other.js";',
            'expectedJavaScriptImports' => ['/assets/other.js' => ['lazy' => false, 'asset' => 'other.js', 'add' => true]],
        ];

        yield 'mix_of_static_and_dynamic_imports' => [
            'input' => 'import "./other.js"; import("./subdir/foo.js");',
            'expectedJavaScriptImports' => [
                '/assets/other.js' => ['lazy' => false, 'asset' => 'other.js', 'add' => true],
                '/assets/subdir/foo.js' => ['lazy' => true, 'asset' => 'subdir/foo.js', 'add' => true],
            ],
        ];

        yield 'extra_import_word_does_not_cause_issues' => [
            'input' => "// about to do an import\nimport('./other.js');",
            'expectedJavaScriptImports' => ['/assets/other.js' => ['lazy' => true, 'asset' => 'other.js', 'add' => true]],
        ];

        yield 'import_on_one_line_then_module_name_on_next_is_ok' => [
            'input' => "import \n    './other.js';",
            'expectedJavaScriptImports' => ['/assets/other.js' => ['lazy' => false, 'asset' => 'other.js', 'add' => true]],
        ];

        yield 'commented_import_on_one_line_then_module_name_on_next_is_not_ok' => [
            'input' => "// import \n    './other.js';",
            'expectedJavaScriptImports' => [],
        ];

        yield 'commented_import_on_one_line_then_import_on_next_is_ok' => [
            'input' => "// import\nimport { Foo } from './other.js';",
            'expectedJavaScriptImports' => ['/assets/other.js' => ['lazy' => false, 'asset' => 'other.js', 'add' => true]],
        ];

        yield 'importing_a_css_file_is_included' => [
            'input' => "import './styles.css';",
            'expectedJavaScriptImports' => ['/assets/styles.css' => ['lazy' => false, 'asset' => 'styles.css', 'add' => true]],
        ];

        yield 'importing_non_existent_file_without_strict_mode_is_ignored_and_no_import_added' => [
            'input' => "import './non-existent.js';",
            'expectedJavaScriptImports' => [],
        ];

        yield 'single_line_comment_at_start_ignored' => [
            'input' => <<<EOF
                const fun;
                // import("./other.js");
                EOF
            ,
            'expectedJavaScriptImports' => [],
        ];

        yield 'single_line_comment_with_whitespace_before_is_ignored' => [
            'input' => <<<EOF
                const fun;
                 // import("./other.js");
                EOF
            ,
            'expectedJavaScriptImports' => [],
        ];

        yield 'single_line_comment_with_more_text_before_import_ignored' => [
            'input' => <<<EOF
                const fun;
                // this is not going to be parsed import("./other.js");
                EOF
            ,
            'expectedJavaScriptImports' => [],
        ];

        yield 'single_line_comment_not_at_start_is_parsed' => [
            'input' => <<<EOF
                const fun;
                console.log('// I am not really a comment'); import("./other.js");
                EOF
            ,
            'expectedJavaScriptImports' => ['/assets/other.js' => ['lazy' => true, 'asset' => 'other.js', 'add' => true]],
        ];

        yield 'multi_line_comment_with_start_and_end_before_import_is_found' => [
            'input' => <<<EOF
                const fun;
                /* comment */ import("./other.js");
                EOF
            ,
            'expectedJavaScriptImports' => ['/assets/other.js' => ['lazy' => true, 'asset' => 'other.js', 'add' => true]],
        ];

        yield 'multi_line_comment_with_import_between_start_and_end_ignored' => [
            'input' => <<<EOF
                const fun;
                    /* comment import("./other.js"); */
                EOF
            ,
            'expectedJavaScriptImports' => [],
        ];

        yield 'multi_line_comment_with_no_end_parsed_for_safety' => [
            'input' => <<<EOF
                const fun;
                    /* comment import("./other.js");
                EOF
            ,
            'expectedJavaScriptImports' => ['/assets/other.js' => ['lazy' => true, 'asset' => 'other.js', 'add' => true]],
        ];

        yield 'multi_line_comment_with_no_end_found_eventually_ignored' => [
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
            'input' => <<<EOF
                const fun;
                    console.log('/* not a comment'); import("./other.js");
                EOF
            ,
            'expectedJavaScriptImports' => ['/assets/other.js' => ['lazy' => true, 'asset' => 'other.js', 'add' => true]],
        ];

        yield 'import_in_double_quoted_string_is_ignored' => [
            'input' => <<<EOF
                const fun;
                console.log("import('./foo.js')");
                EOF
            ,
            'expectedJavaScriptImports' => [],
        ];

        yield 'import_in_double_quoted_string_with_escaped_quote_is_ignored' => [
            'input' => <<<EOF
                const fun;
                console.log(" foo \" import('./foo.js')");
                EOF
            ,
            'expectedJavaScriptImports' => [],
        ];

        yield 'import_in_single_quoted_string_is_ignored' => [
            'input' => <<<EOF
                const fun;
                console.log('import("./foo.js")');
                EOF
            ,
            'expectedJavaScriptImports' => [],
        ];

        yield 'import_after_a_string_is_parsed' => [
            'input' => <<<EOF
                const fun;
                console.log("import('./other.js')"); import("./foo.js");
                EOF
            ,
            'expectedJavaScriptImports' => ['/assets/foo.js' => ['lazy' => true, 'asset' => 'foo.js', 'add' => true]],
        ];

        yield 'import_before_a_string_is_parsed' => [
            'input' => <<<EOF
                const fun;
                import("./other.js"); console.log("import('./foo.js')");
                EOF
            ,
            'expectedJavaScriptImports' => ['/assets/other.js' => ['lazy' => true, 'asset' => 'other.js', 'add' => true]],
        ];

        yield 'import_before_and_after_a_string_is_parsed' => [
            'input' => <<<EOF
                const fun;
                import("./other.js"); console.log("import('./foo.js')"); import("./subdir/foo.js");
                EOF
            ,
            'expectedJavaScriptImports' => [
                '/assets/other.js' => ['lazy' => true, 'asset' => 'other.js', 'add' => true],
                '/assets/subdir/foo.js' => ['lazy' => true, 'asset' => 'subdir/foo.js', 'add' => true],
            ],
        ];

        yield 'bare_import_not_in_importmap' => [
            'input' => 'import "some_module";',
            'expectedJavaScriptImports' => [],
        ];

        yield 'bare_import_in_importmap_with_local_asset' => [
            'input' => 'import "module_in_importmap_local_asset";',
            'expectedJavaScriptImports' => ['module_in_importmap_local_asset' => ['lazy' => false, 'asset' => 'module_in_importmap_local_asset.js', 'add' => false]],
        ];

        yield 'bare_import_in_importmap_but_remote' => [
            'input' => 'import "module_in_importmap_remote";',
            'expectedJavaScriptImports' => ['module_in_importmap_remote' => ['lazy' => false, 'asset' => 'module_in_importmap_remote.js', 'add' => false]],
        ];

        yield 'absolute_import_ignored_and_no_dependency_added' => [
            'input' => 'import "https://example.com/module.js";',
            'expectedJavaScriptImports' => [],
        ];

        yield 'bare_import_with_minimal_spaces' => [
            'input' => 'import*as t from"@popperjs/core";',
            'expectedJavaScriptImports' => ['@popperjs/core' => ['lazy' => false, 'asset' => 'assets/vendor/@popperjs/core.js', 'add' => false]],
        ];
    }

    public function testCompileFindsRelativePathsViaSourcePath()
    {
        $inputAsset = new MappedAsset('app.js', '/project/assets/app.js', publicPathWithoutDigest: '/assets/app.js');

        $assetMapper = $this->createMock(AssetMapperInterface::class);
        $assetMapper->expects($this->any())
            ->method('getAssetFromSourcePath')
            ->willReturnCallback(function ($path) {
                return match ($path) {
                    '/project/assets/other.js' => new MappedAsset('other.js', '/can/be/anything.js', publicPathWithoutDigest: '/assets/other.js'),
                    '/project/assets/subdir/foo.js' => new MappedAsset('subdir/foo.js', '/can/be/anything.js', publicPathWithoutDigest: '/assets/subdir/foo.js'),
                    '/project/root_asset.js' => new MappedAsset('root_asset.js', '/can/be/anything.js', publicPathWithoutDigest: '/assets/root_asset.js'),
                    default => throw new \RuntimeException(\sprintf('Unexpected source path "%s"', $path)),
                };
            });

        $input = <<<EOF
            import './other.js';
            import './subdir/foo.js';
            import '../root_asset.js';
            EOF;

        $compiler = new JavaScriptImportPathCompiler($this->createMock(ImportMapConfigReader::class));
        $compiler->compile($input, $inputAsset, $assetMapper);
        $this->assertCount(3, $inputAsset->getJavaScriptImports());
        $this->assertSame('other.js', $inputAsset->getJavaScriptImports()[0]->assetLogicalPath);
        $this->assertSame('subdir/foo.js', $inputAsset->getJavaScriptImports()[1]->assetLogicalPath);
        $this->assertSame('root_asset.js', $inputAsset->getJavaScriptImports()[2]->assetLogicalPath);
    }

    public function testCompileFindsRelativePathsWithWindowsPathsViaSourcePath()
    {
        if ('\\' !== \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Must be on windows where dirname() understands backslashes');
        }
        $inputAsset = new MappedAsset('app.js', 'C:\\\\project\\assets\\app.js', publicPathWithoutDigest: '/assets/app.js');

        $assetMapper = $this->createMock(AssetMapperInterface::class);
        $assetMapper->expects($this->any())
            ->method('getAssetFromSourcePath')
            ->willReturnCallback(function ($path) {
                return match ($path) {
                    'C://project/assets/other.js' => new MappedAsset('other.js', '/can/be/anything.js', publicPathWithoutDigest: '/assets/other.js'),
                    'C://project/assets/subdir/foo.js' => new MappedAsset('subdir/foo.js', '/can/be/anything.js', publicPathWithoutDigest: '/assets/subdir/foo.js'),
                    'C://project/root_asset.js' => new MappedAsset('root_asset.js', '/can/be/anything.js', publicPathWithoutDigest: '/assets/root_asset.js'),
                    default => throw new \RuntimeException(\sprintf('Unexpected source path "%s"', $path)),
                };
            });

        $input = <<<EOF
            import './other.js';
            import './subdir/foo.js';
            import '../root_asset.js';
            EOF;

        $compiler = new JavaScriptImportPathCompiler($this->createMock(ImportMapConfigReader::class));
        $compiler->compile($input, $inputAsset, $assetMapper);
        $this->assertCount(3, $inputAsset->getJavaScriptImports());
        $this->assertSame('other.js', $inputAsset->getJavaScriptImports()[0]->assetLogicalPath);
        $this->assertSame('subdir/foo.js', $inputAsset->getJavaScriptImports()[1]->assetLogicalPath);
        $this->assertSame('root_asset.js', $inputAsset->getJavaScriptImports()[2]->assetLogicalPath);
    }

    /**
     * @dataProvider providePathsCanUpdateTests
     */
    public function testImportPathsCanUpdateForDifferentPublicPath(string $input, string $inputAssetPublicPath, string $importedPublicPath, string $expectedOutput)
    {
        $asset = new MappedAsset('app.js', '/path/to/assets/app.js', publicPathWithoutDigest: $inputAssetPublicPath);

        $assetMapper = $this->createMock(AssetMapperInterface::class);
        $importedAsset = new MappedAsset('anything', '/can/be/anything.js', publicPathWithoutDigest: $importedPublicPath);
        $assetMapper->expects($this->once())
            ->method('getAssetFromSourcePath')
            ->willReturn($importedAsset);

        $compiler = new JavaScriptImportPathCompiler($this->createMock(ImportMapConfigReader::class));
        $this->assertSame($expectedOutput, $compiler->compile($input, $asset, $assetMapper));
    }

    public static function providePathsCanUpdateTests(): iterable
    {
        yield 'simple - no change needed' => [
            'input' => "import './other.js';",
            'inputAssetPublicPath' => '/assets/app.js',
            'importedPublicPath' => '/assets/other.js',
            'expectedOutput' => "import './other.js';",
        ];

        yield 'same directory - no change needed' => [
            'input' => "import './other.js';",
            'inputAssetPublicPath' => '/assets/js/app.js',
            'importedPublicPath' => '/assets/js/other.js',
            'expectedOutput' => "import './other.js';",
        ];

        yield 'different directories but not adjustment needed' => [
            'input' => "import './subdir/other.js';",
            'inputAssetPublicPath' => '/assets/app.js',
            'importedPublicPath' => '/assets/subdir/other.js',
            'expectedOutput' => "import './subdir/other.js';",
        ];

        yield 'inputAssetPublicPath is deeper than expected so adjustment is made' => [
            'input' => "import './other.js';",
            'inputAssetPublicPath' => '/assets/js/app.js',
            'importedPublicPath' => '/assets/other.js',
            'expectedOutput' => "import '../other.js';",
        ];

        yield 'importedPublicPath is different so adjustment is made' => [
            'input' => "import './other.js';",
            'inputAssetPublicPath' => '/assets/app.js',
            'importedPublicPath' => '/assets/js/other.js',
            'expectedOutput' => "import './js/other.js';",
        ];

        yield 'both paths are in unexpected places so adjustment is made' => [
            'input' => "import './other.js';",
            'inputAssetPublicPath' => '/assets/js/app.js',
            'importedPublicPath' => '/assets/somewhere/other.js',
            'expectedOutput' => "import '../somewhere/other.js';",
        ];
    }

    public function testCompileHandlesCircularRelativeAssets()
    {
        $appAsset = new MappedAsset('app.js', '/project/assets/app.js', '/assets/app.js');
        $otherAsset = new MappedAsset('other.js', '/project/assets/other.js', '/assets/other.js');

        $importMapConfigReader = $this->createMock(ImportMapConfigReader::class);
        $assetMapper = $this->createMock(AssetMapperInterface::class);
        $assetMapper->expects($this->once())
            ->method('getAssetFromSourcePath')
            ->with('/project/assets/other.js')
            ->willThrowException(new CircularAssetsException($otherAsset));

        $compiler = new JavaScriptImportPathCompiler($importMapConfigReader);
        $input = 'import "./other.js";';
        $compiler->compile($input, $appAsset, $assetMapper);
        $this->assertCount(1, $appAsset->getJavaScriptImports());
        $this->assertSame($otherAsset->logicalPath, $appAsset->getJavaScriptImports()[0]->assetLogicalPath);
    }

    public function testCompileHandlesCircularBareImportAssets()
    {
        $bootstrapAsset = new MappedAsset('bootstrap', 'anythingbootstrap', '/assets/bootstrap.js');
        $popperAsset = new MappedAsset('@popperjs/core', 'anythingpopper', '/assets/popper.js');

        $importMapConfigReader = $this->createMock(ImportMapConfigReader::class);
        $importMapConfigReader->expects($this->once())
            ->method('findRootImportMapEntry')
            ->with('@popperjs/core')
            ->willReturn(ImportMapEntry::createRemote('@popperjs/core', ImportMapType::JS, './vendor/@popperjs/core.js', '1.2.3', 'could_be_anything', false));
        $importMapConfigReader->expects($this->any())
            ->method('convertPathToFilesystemPath')
            ->with('./vendor/@popperjs/core.js')
            ->willReturn('/path/to/vendor/@popperjs/core.js');

        $assetMapper = $this->createMock(AssetMapperInterface::class);
        $assetMapper->expects($this->once())
            ->method('getAssetFromSourcePath')
            ->with('/path/to/vendor/@popperjs/core.js')
            ->willThrowException(new CircularAssetsException($popperAsset));

        $compiler = new JavaScriptImportPathCompiler($importMapConfigReader);
        $input = 'import "@popperjs/core";';
        $compiler->compile($input, $bootstrapAsset, $assetMapper);
        $this->assertCount(1, $bootstrapAsset->getJavaScriptImports());
        $this->assertSame($popperAsset->logicalPath, $bootstrapAsset->getJavaScriptImports()[0]->assetLogicalPath);
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
            $this->createMock(ImportMapConfigReader::class),
            AssetCompilerInterface::MISSING_IMPORT_STRICT,
            $logger
        );
        $assetMapper = $this->createMock(AssetMapperInterface::class);
        $assetMapper->expects($this->any())
            ->method('getAssetFromSourcePath')
            ->willReturnCallback(function ($sourcePath) {
                return match ($sourcePath) {
                    '/path/to/other.js' => new MappedAsset('other.js', '/can/be/anything.js', publicPathWithoutDigest: '/assets/other.js'),
                    default => null,
                };
            }
            );

        $this->assertSame($input, $compiler->compile($input, $asset, $assetMapper));
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
                    throw new CircularAssetsException(new MappedAsset('htmx.js'));
                }
            });

        $asset = new MappedAsset('htmx.js', '/path/to/app.js');
        $compiler = new JavaScriptImportPathCompiler($this->createMock(ImportMapConfigReader::class));
        $content = '//** @type {import("./htmx").HtmxApi} */';
        $compiled = $compiler->compile($content, $asset, $assetMapper);
        // To form a good exception message, the compiler will check for the
        // htmx.js asset, which will throw a CircularAssetsException. This
        // should not be caught.
        $this->assertSame($content, $compiled);
    }

    public function testCompilerThrowsExceptionOnPcreError()
    {
        $compiler = new JavaScriptImportPathCompiler($this->createMock(ImportMapConfigReader::class));
        $content = str_repeat('foo "import *  ', 50);
        $javascriptAsset = new MappedAsset('app.js', '/project/assets/app.js', publicPathWithoutDigest: '/assets/app.js');
        $assetMapper = $this->createMock(AssetMapperInterface::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to compile JavaScript import paths in "/project/assets/app.js". Error: "Backtrack limit exhausted".');

        $limit = \ini_get('pcre.backtrack_limit');
        ini_set('pcre.backtrack_limit', 10);
        try {
            $compiler->compile($content, $javascriptAsset, $assetMapper);
        } finally {
            ini_set('pcre.backtrack_limit', $limit);
        }
    }
}
