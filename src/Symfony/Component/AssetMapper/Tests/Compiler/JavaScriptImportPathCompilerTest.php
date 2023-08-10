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
use Symfony\Component\AssetMapper\MappedAsset;

class JavaScriptImportPathCompilerTest extends TestCase
{
    /**
     * @dataProvider provideCompileTests
     */
    public function testCompile(string $sourceLogicalName, string $input, array $expectedDependencies)
    {
        $asset = new MappedAsset($sourceLogicalName, 'anything', '/assets/'.$sourceLogicalName);

        $compiler = new JavaScriptImportPathCompiler(AssetCompilerInterface::MISSING_IMPORT_IGNORE, $this->createMock(LoggerInterface::class));
        // compile - and check that content doesn't change
        $this->assertSame($input, $compiler->compile($input, $asset, $this->createAssetMapper()));
        $actualDependencies = [];
        foreach ($asset->getDependencies() as $dependency) {
            $actualDependencies[$dependency->asset->logicalPath] = $dependency->isLazy;
        }
        $this->assertEquals($expectedDependencies, $actualDependencies);
        if ($expectedDependencies) {
            $this->assertFalse($asset->getDependencies()[0]->isContentDependency);
        }
    }

    public static function provideCompileTests(): iterable
    {
        yield 'dynamic_simple_double_quotes' => [
            'sourceLogicalName' => 'app.js',
            'input' => 'import("./other.js");',
            'expectedDependencies' => ['other.js' => true],
        ];

        yield 'dynamic_simple_multiline' => [
            'sourceLogicalName' => 'app.js',
            'input' => <<<EOF
                const fun;
                import("./other.js");
                EOF
            ,
            'expectedDependencies' => ['other.js' => true],
        ];

        yield 'dynamic_simple_single_quotes' => [
            'sourceLogicalName' => 'app.js',
            'input' => 'import(\'./other.js\');',
            'expectedDependencies' => ['other.js' => true],
        ];

        yield 'dynamic_simple_tick_quotes' => [
            'sourceLogicalName' => 'app.js',
            'input' => 'import(`./other.js`);',
            'expectedDependencies' => ['other.js' => true],
        ];

        yield 'dynamic_resolves_multiple' => [
            'sourceLogicalName' => 'app.js',
            'input' => 'import("./other.js"); import("./subdir/foo.js");',
            'expectedDependencies' => ['other.js' => true, 'subdir/foo.js' => true],
        ];

        yield 'dynamic_avoid_resolving_non_relative_imports' => [
            'sourceLogicalName' => 'app.js',
            'input' => 'import("other.js");',
            'expectedDependencies' => [],
        ];

        yield 'dynamic_resolves_dynamic_imports_later_in_file' => [
            'sourceLogicalName' => 'app.js',
            'input' => "console.log('Hello test!');\n import('./subdir/foo.js').then(() => console.log('inside promise!'));",
            'expectedDependencies' => ['subdir/foo.js' => true],
        ];

        yield 'dynamic_correctly_moves_to_higher_directories' => [
            'sourceLogicalName' => 'subdir/app.js',
            'input' => 'import("../other.js");',
            'expectedDependencies' => ['other.js' => true],
        ];

        yield 'static_named_import_double_quotes' => [
            'sourceLogicalName' => 'app.js',
            'input' => 'import { myFunction } from "./other.js";',
            'expectedDependencies' => ['other.js' => false],
        ];

        yield 'static_named_import_single_quotes' => [
            'sourceLogicalName' => 'app.js',
            'input' => 'import { myFunction } from \'./other.js\';',
            'expectedDependencies' => ['other.js' => false],
        ];

        yield 'static_default_import' => [
            'sourceLogicalName' => 'app.js',
            'input' => 'import myFunction from "./other.js";',
            'expectedDependencies' => ['other.js' => false],
        ];

        yield 'static_default_and_named_import' => [
            'sourceLogicalName' => 'app.js',
            'input' => 'import myFunction, { helperFunction } from "./other.js";',
            'expectedDependencies' => ['other.js' => false],
        ];

        yield 'static_import_everything' => [
            'sourceLogicalName' => 'app.js',
            'input' => 'import * as myModule from "./other.js";',
            'expectedDependencies' => ['other.js' => false],
        ];

        yield 'static_import_just_for_side_effects' => [
            'sourceLogicalName' => 'app.js',
            'input' => 'import "./other.js";',
            'expectedDependencies' => ['other.js' => false],
        ];

        yield 'mix_of_static_and_dynamic_imports' => [
            'sourceLogicalName' => 'app.js',
            'input' => 'import "./other.js"; import("./subdir/foo.js");',
            'expectedDependencies' => ['other.js' => false, 'subdir/foo.js' => true],
        ];

        yield 'extra_import_word_does_not_cause_issues' => [
            'sourceLogicalName' => 'app.js',
            'input' => "// about to do an import\nimport('./other.js');",
            'expectedDependencies' => ['other.js' => true],
        ];

        yield 'import_on_one_line_then_module_name_on_next_is_ok' => [
            'sourceLogicalName' => 'app.js',
            'input' => "import \n    './other.js';",
            'expectedDependencies' => ['other.js' => false],
        ];

        yield 'importing_a_css_file_is_not_included' => [
            'sourceLogicalName' => 'app.js',
            'input' => "import './styles.css';",
            'expectedDependencies' => [],
        ];

        yield 'importing_non_existent_file_without_strict_mode_is_ignored' => [
            'sourceLogicalName' => 'app.js',
            'input' => "import './non-existent.js';",
            'expectedDependencies' => [],
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

        $compiler = new JavaScriptImportPathCompiler(AssetCompilerInterface::MISSING_IMPORT_IGNORE, $this->createMock(LoggerInterface::class));
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
        $compiler = new JavaScriptImportPathCompiler();
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
                    default => null,
                };
            });

        return $assetMapper;
    }
}
