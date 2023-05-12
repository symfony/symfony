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
use Symfony\Component\AssetMapper\Compiler\SourceMappingUrlsCompiler;
use Symfony\Component\AssetMapper\MappedAsset;

class SourceMappingUrlsCompilerTest extends TestCase
{
    /**
     * @dataProvider provideCompileTests
     */
    public function testCompile(string $sourceLogicalName, string $input, string $expectedOutput, $expectedDependencies)
    {
        $assetMapper = $this->createMock(AssetMapperInterface::class);
        $assetMapper->expects($this->any())
            ->method('getAsset')
            ->willReturnCallback(function ($path) {
                switch ($path) {
                    case 'foo.js.map':
                        $asset = new MappedAsset('foo.js.map');
                        $asset->setPublicPathWithoutDigest('/assets/foo.js.map');
                        $asset->setPublicPath('/assets/foo.123456.js.map');

                        return $asset;
                    case 'styles/bar.css.map':
                        $asset = new MappedAsset('styles/bar.css.map');
                        $asset->setPublicPathWithoutDigest('/assets/styles/bar.css.map');
                        $asset->setPublicPath('/assets/styles/bar.abcd123.css.map');

                        return $asset;
                    case 'sourcemaps/baz.css.map':
                        $asset = new MappedAsset('sourcemaps/baz.css.map');
                        $asset->setPublicPathWithoutDigest('/assets/sourcemaps/baz.css.map');
                        $asset->setPublicPath('/assets/sourcemaps/baz.987fedc.css.map');

                        return $asset;
                    default:
                        return null;
                }
            });

        $compiler = new SourceMappingUrlsCompiler();
        $asset = new MappedAsset($sourceLogicalName);
        $asset->setPublicPathWithoutDigest('/assets/'.$sourceLogicalName);
        $this->assertSame($expectedOutput, $compiler->compile($input, $asset, $assetMapper));
        $assetDependencyLogicalPaths = array_map(fn (AssetDependency $dependency) => $dependency->asset->getLogicalPath(), $asset->getDependencies());
        $this->assertSame($expectedDependencies, $assetDependencyLogicalPaths);
    }

    public static function provideCompileTests(): iterable
    {
        yield 'js_simple_sourcemap' => [
            'sourceLogicalName' => 'foo.js',
            'input' => <<<EOF
                var fun;
                //# sourceMappingURL=foo.js.map
                EOF
            ,
            'expectedOutput' => <<<EOF
                var fun;
                //# sourceMappingURL=foo.123456.js.map
                EOF
            ,
            'expectedDependencies' => ['foo.js.map'],
        ];

        yield 'css_simple_sourcemap' => [
            'sourceLogicalName' => 'styles/bar.css',
            'input' => <<<EOF
                .class { color: green; }
                /*# sourceMappingURL=bar.css.map */
                EOF
            ,
            'expectedOutput' => <<<EOF
                .class { color: green; }
                /*# sourceMappingURL=bar.abcd123.css.map */
                EOF
            ,
            'expectedDependencies' => ['styles/bar.css.map'],
        ];

        yield 'sourcemap_in_different_directory_resolves' => [
            'sourceLogicalName' => 'styles/bar.css',
            'input' => <<<EOF
                .class { color: green; }
                /*# sourceMappingURL=../sourcemaps/baz.css.map */
                EOF
            ,
            'expectedOutput' => <<<EOF
                .class { color: green; }
                /*# sourceMappingURL=../sourcemaps/baz.987fedc.css.map */
                EOF
            ,
            'expectedDependencies' => ['sourcemaps/baz.css.map'],
        ];

        yield 'no_sourcemap_found' => [
            'sourceLogicalName' => 'styles/bar.css',
            'input' => <<<EOF
                .class { color: green; }
                EOF
            ,
            'expectedOutput' => <<<EOF
                .class { color: green; }
                EOF
            ,
            'expectedDependencies' => [],
        ];

        yield 'path_not_in_asset_mapper_is_left_alone' => [
            'sourceLogicalName' => 'styles/bar.css',
            'input' => <<<EOF
                .class { color: green; }
                /*# sourceMappingURL=unknown.css.map */
                EOF
            ,
            'expectedOutput' => <<<EOF
                .class { color: green; }
                /*# sourceMappingURL=unknown.css.map */
                EOF
            ,
            'expectedDependencies' => [],
        ];

        yield 'sourcemap_outside_of_comment_left_alone' => [
            'sourceLogicalName' => 'styles/bar.css',
            'input' => <<<EOF
                .class::before {
                  content: "# sourceMappingURL=sourceMappingURL-outside-comment.css.map";
                }
                EOF
            ,
            'expectedOutput' => <<<EOF
                .class::before {
                  content: "# sourceMappingURL=sourceMappingURL-outside-comment.css.map";
                }
                EOF
            ,
            'expectedDependencies' => [],
        ];

        yield 'sourcemap_not_at_start_of_line_left_alone' => [
            'sourceLogicalName' => 'styles/bar.css',
            'input' => <<<EOF
                .class {
                  color: green; /*# sourceMappingURL=sourceMappingURL-not-at-start.css.map */
                }
                EOF
            ,
            'expectedOutput' => <<<EOF
                .class {
                  color: green; /*# sourceMappingURL=sourceMappingURL-not-at-start.css.map */
                }
                EOF
            ,
            'expectedDependencies' => [],
        ];
    }
}
