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
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\AssetMapper\Compiler\JavaScriptImportPathCompiler;
use Symfony\Component\AssetMapper\MappedAsset;

class JavaScriptImportPathCompilerTest extends TestCase
{
    /**
     * @dataProvider provideCompileTests
     */
    public function testCompile(string $sourceLogicalName, string $input, array $expectedDependencies)
    {
        $asset = new MappedAsset($sourceLogicalName);

        $compiler = new JavaScriptImportPathCompiler(false);
        // compile - and check that content doesn't change
        $this->assertSame($input, $compiler->compile($input, $asset, $this->createAssetMapper()));
        $actualDependencies = [];
        foreach ($asset->getDependencies() as $dependency) {
            $actualDependencies[$dependency->asset->logicalPath] = $dependency->isLazy;
        }
        $this->assertEquals($expectedDependencies, $actualDependencies);
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
     * @dataProvider provideStrictModeTests
     */
    public function testStrictMode(string $sourceLogicalName, string $input, ?string $expectedExceptionMessage)
    {
        if (null !== $expectedExceptionMessage) {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage($expectedExceptionMessage);
        }

        $asset = new MappedAsset($sourceLogicalName);
        $asset->setSourcePath('/path/to/app.js');

        $compiler = new JavaScriptImportPathCompiler(true);
        $this->assertSame($input, $compiler->compile($input, $asset, $this->createAssetMapper()));
    }

    public static function provideStrictModeTests(): iterable
    {
        yield 'importing_non_existent_file_throws_exception' => [
            'sourceLogicalName' => 'app.js',
            'input' => "import './non-existent.js';",
            'expectedExceptionMessage' => 'Unable to find asset "non-existent.js" imported from "/path/to/app.js".',
        ];

        yield 'importing_file_just_missing_js_extension_adds_extra_info' => [
            'sourceLogicalName' => 'app.js',
            'input' => "import './other';",
            'expectedExceptionMessage' => 'Unable to find asset "other" imported from "/path/to/app.js". Try adding ".js" to the end of the import - i.e. "other.js".',
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

    private function createAssetMapper(): AssetMapperInterface
    {
        $assetMapper = $this->createMock(AssetMapperInterface::class);
        $assetMapper->expects($this->any())
            ->method('getAsset')
            ->willReturnCallback(function ($path) {
                switch ($path) {
                    case 'other.js':
                        $asset = new MappedAsset('other.js');
                        $asset->setMimeType('application/javascript');

                        return $asset;
                    case 'subdir/foo.js':
                        $asset = new MappedAsset('subdir/foo.js');
                        $asset->setMimeType('text/javascript');

                        return $asset;
                    case 'dir_with_index/index.js':
                        $asset = new MappedAsset('dir_with_index/index.js');
                        $asset->setMimeType('text/javascript');

                        return $asset;
                    case 'styles.css':
                        $asset = new MappedAsset('styles.css');
                        $asset->setMimeType('text/css');

                        return $asset;
                    default:
                        return null;
                }
            });

        return $assetMapper;
    }
}
