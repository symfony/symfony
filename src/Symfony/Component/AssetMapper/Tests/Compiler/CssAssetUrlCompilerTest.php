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
use Symfony\Component\AssetMapper\AssetDependency;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\AssetMapper\Compiler\CssAssetUrlCompiler;
use Symfony\Component\AssetMapper\MappedAsset;

class CssAssetUrlCompilerTest extends TestCase
{
    /**
     * @dataProvider provideCompileTests
     */
    public function testCompile(string $sourceLogicalName, string $input, string $expectedOutput, array $expectedDependencies)
    {
        $compiler = new CssAssetUrlCompiler(false);
        $asset = new MappedAsset($sourceLogicalName);
        $this->assertSame($expectedOutput, $compiler->compile($input, $asset, $this->createAssetMapper()));
        $assetDependencyLogicalPaths = array_map(fn (AssetDependency $dependency) => $dependency->asset->logicalPath, $asset->getDependencies());
        $this->assertSame($expectedDependencies, $assetDependencyLogicalPaths);
    }

    public static function provideCompileTests(): iterable
    {
        yield 'simple_double_quotes' => [
            'sourceLogicalName' => 'styles.css',
            'input' => 'body { background: url("images/foo.png"); }',
            'expectedOutput' => 'body { background: url("/assets/images/foo.123456.png"); }',
            'expectedDependencies' => ['images/foo.png'],
        ];

        yield 'simple_multiline' => [
            'sourceLogicalName' => 'styles.css',
            'input' => <<<EOF
                body {
                    background: url("images/foo.png");
                }
                EOF
            ,
            'expectedOutput' => <<<EOF
                body {
                    background: url("/assets/images/foo.123456.png");
                }
                EOF
            ,
            'expectedDependencies' => ['images/foo.png'],
        ];

        yield 'simple_single_quotes' => [
            'sourceLogicalName' => 'styles.css',
            'input' => 'body { background: url(\'images/foo.png\'); }',
            'expectedOutput' => 'body { background: url("/assets/images/foo.123456.png"); }',
            'expectedDependencies' => ['images/foo.png'],
        ];

        yield 'simple_no_quotes' => [
            'sourceLogicalName' => 'styles.css',
            'input' => 'body { background: url(images/foo.png); }',
            'expectedOutput' => 'body { background: url("/assets/images/foo.123456.png"); }',
            'expectedDependencies' => ['images/foo.png'],
        ];

        yield 'import_other_css_file' => [
            'sourceLogicalName' => 'styles.css',
            'input' => '@import url(more-styles.css)',
            'expectedOutput' => '@import url("/assets/more-styles.abcd123.css")',
            'expectedDependencies' => ['more-styles.css'],
        ];

        yield 'move_up_a_directory' => [
            'sourceLogicalName' => 'styles/app.css',
            'input' => 'body { background: url("../images/foo.png"); }',
            'expectedOutput' => 'body { background: url("/assets/images/foo.123456.png"); }',
            'expectedDependencies' => ['images/foo.png'],
        ];

        yield 'path_not_found_left_alone' => [
            'sourceLogicalName' => 'styles/app.css',
            'input' => 'body { background: url("../images/bar.png"); }',
            'expectedOutput' => 'body { background: url("../images/bar.png"); }',
            'expectedDependencies' => [],
        ];

        yield 'absolute_paths_left_alone' => [
            'sourceLogicalName' => 'styles/app.css',
            'input' => 'body { background: url("https://cdn.io/images/bar.png"); }',
            'expectedOutput' => 'body { background: url("https://cdn.io/images/bar.png"); }',
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
        $asset->setSourcePath('/path/to/styles.css');

        $compiler = new CssAssetUrlCompiler(true);
        $this->assertSame($input, $compiler->compile($input, $asset, $this->createAssetMapper()));
    }

    public static function provideStrictModeTests(): iterable
    {
        yield 'importing_non_existent_file_throws_exception' => [
            'sourceLogicalName' => 'styles.css',
            'input' => '@import url(non-existent.css)',
            'expectedExceptionMessage' => 'Unable to find asset "non-existent.css" referenced in "/path/to/styles.css".',
        ];

        yield 'importing_absolute_file_path_is_ignored' => [
            'sourceLogicalName' => 'styles.css',
            'input' => '@import url(/path/to/non-existent.css)',
            'expectedExceptionMessage' => null,
        ];

        yield 'importing_a_url_is_ignored' => [
            'sourceLogicalName' => 'styles.css',
            'input' => '@import url(https://cdn.io/non-existent.css)',
            'expectedExceptionMessage' => null,
        ];

        yield 'importing_a_data_uri_is_ignored' => [
            'sourceLogicalName' => 'styles.css',
            'input' => "background-image: url(\'data:image/png;base64,iVBORw0KG\')",
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
                    case 'images/foo.png':
                        $asset = new MappedAsset('images/foo.png');
                        $asset->setPublicPath('/assets/images/foo.123456.png');

                        return $asset;
                    case 'more-styles.css':
                        $asset = new MappedAsset('more-styles.css');
                        $asset->setPublicPath('/assets/more-styles.abcd123.css');

                        return $asset;
                    default:
                        return null;
                }
            });

        return $assetMapper;
    }
}
