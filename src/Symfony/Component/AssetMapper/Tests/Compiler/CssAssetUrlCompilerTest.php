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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\AssetMapper\Compiler\AssetCompilerInterface;
use Symfony\Component\AssetMapper\Compiler\CssAssetUrlCompiler;
use Symfony\Component\AssetMapper\MappedAsset;

class CssAssetUrlCompilerTest extends TestCase
{
    #[DataProvider('provideCompileTests')]
    public function testCompile(string $input, string $expectedOutput, array $expectedDependencies)
    {
        $assetMapper = $this->createStub(AssetMapperInterface::class);
        $assetMapper
            ->method('getAssetFromSourcePath')
            ->willReturnCallback(static function ($path) {
                return match ($path) {
                    '/project/assets/images/foo.png' => new MappedAsset('images/foo.png',
                        publicPathWithoutDigest: '/assets/images/foo.png',
                        publicPath: '/assets/images/foo.123456.png',
                    ),
                    '/project/assets/more-styles.css' => new MappedAsset('more-styles.css',
                        publicPathWithoutDigest: '/assets/more-styles.css',
                        publicPath: '/assets/more-styles.abcd123.css',
                    ),
                    default => null,
                };
            });

        $compiler = new CssAssetUrlCompiler();
        $asset = new MappedAsset('styles.css', '/project/assets/styles.css', '/assets/styles.css');
        $this->assertSame($expectedOutput, $compiler->compile($input, $asset, $assetMapper));
        $assetDependencyLogicalPaths = array_map(static fn (MappedAsset $dependency) => $dependency->logicalPath, $asset->getDependencies());
        $this->assertSame($expectedDependencies, $assetDependencyLogicalPaths);
    }

    public static function provideCompileTests(): iterable
    {
        yield 'simple_double_quotes' => [
            'input' => 'body { background: url("images/foo.png"); }',
            'expectedOutput' => 'body { background: url("images/foo.123456.png"); }',
            'expectedDependencies' => ['images/foo.png'],
        ];

        yield 'simple_multiline' => [
            'input' => <<<EOF
                body {
                    background: url("images/foo.png");
                }
                EOF,
            'expectedOutput' => <<<EOF
                body {
                    background: url("images/foo.123456.png");
                }
                EOF,
            'expectedDependencies' => ['images/foo.png'],
        ];

        yield 'simple_single_quotes' => [
            'input' => 'body { background: url(\'images/foo.png\'); }',
            'expectedOutput' => 'body { background: url("images/foo.123456.png"); }',
            'expectedDependencies' => ['images/foo.png'],
        ];

        yield 'simple_no_quotes' => [
            'input' => 'body { background: url(images/foo.png); }',
            'expectedOutput' => 'body { background: url("images/foo.123456.png"); }',
            'expectedDependencies' => ['images/foo.png'],
        ];

        yield 'import_other_css_file' => [
            'input' => '@import url(more-styles.css)',
            'expectedOutput' => '@import url("more-styles.abcd123.css")',
            'expectedDependencies' => ['more-styles.css'],
        ];

        yield 'import_other_css_file_with_dot_slash' => [
            'input' => '@import url(./more-styles.css)',
            'expectedOutput' => '@import url("more-styles.abcd123.css")',
            'expectedDependencies' => ['more-styles.css'],
        ];

        yield 'import_other_css_file_with_dot_dot_slash' => [
            'input' => '@import url(../assets/more-styles.css)',
            'expectedOutput' => '@import url("more-styles.abcd123.css")',
            'expectedDependencies' => ['more-styles.css'],
        ];

        yield 'path_not_found_left_alone' => [
            'input' => 'body { background: url("../images/bar.png"); }',
            'expectedOutput' => 'body { background: url("../images/bar.png"); }',
            'expectedDependencies' => [],
        ];

        yield 'absolute_paths_left_alone' => [
            'input' => 'body { background: url("https://cdn.io/images/bar.png"); }',
            'expectedOutput' => 'body { background: url("https://cdn.io/images/bar.png"); }',
            'expectedDependencies' => [],
        ];

        yield 'ignore_comments' => [
            'input' => 'body { background: url("images/foo.png"); /* background: url("images/bar.png"); */ }',
            'expectedOutput' => 'body { background: url("images/foo.123456.png"); /* background: url("images/bar.png"); */ }',
            'expectedDependencies' => ['images/foo.png'],
        ];

        yield 'ignore_comment_after_rule' => [
            'input' => 'body { background: url("images/foo.png"); } /* url("images/need-ignore.png") */',
            'expectedOutput' => 'body { background: url("images/foo.123456.png"); } /* url("images/need-ignore.png") */',
            'expectedDependencies' => ['images/foo.png'],
        ];

        yield 'ignore_comment_within_rule' => [
            'input' => 'body { background: url("images/foo.png") /* url("images/need-ignore.png") */; }',
            'expectedOutput' => 'body { background: url("images/foo.123456.png") /* url("images/need-ignore.png") */; }',
            'expectedDependencies' => ['images/foo.png'],
        ];

        yield 'ignore_multiline_comment_after_rule' => [
            'input' => 'body {
                background: url("images/foo.png"); /*
                url("images/need-ignore.png") */
            }',
            'expectedOutput' => 'body {
                background: url("images/foo.123456.png"); /*
                url("images/need-ignore.png") */
            }',
            'expectedDependencies' => ['images/foo.png'],
        ];

        yield 'import_without_url_double_quotes' => [
            'input' => '@import "more-styles.css"',
            'expectedOutput' => '@import "more-styles.abcd123.css"',
            'expectedDependencies' => ['more-styles.css'],
        ];

        yield 'import_without_url_single_quotes' => [
            'input' => "@import 'more-styles.css'",
            'expectedOutput' => '@import "more-styles.abcd123.css"',
            'expectedDependencies' => ['more-styles.css'],
        ];

        yield 'import_without_url_with_media_query' => [
            'input' => '@import "more-styles.css" screen',
            'expectedOutput' => '@import "more-styles.abcd123.css" screen',
            'expectedDependencies' => ['more-styles.css'],
        ];

        yield 'import_without_url_with_media_query_single_quotes' => [
            'input' => "@import 'more-styles.css' screen and (max-width: 600px)",
            'expectedOutput' => '@import "more-styles.abcd123.css" screen and (max-width: 600px)',
            'expectedDependencies' => ['more-styles.css'],
        ];

        yield 'import_without_url_with_dot_slash' => [
            'input' => '@import "./more-styles.css"',
            'expectedOutput' => '@import "more-styles.abcd123.css"',
            'expectedDependencies' => ['more-styles.css'],
        ];

        yield 'import_without_url_with_dot_dot_slash' => [
            'input' => '@import "../assets/more-styles.css"',
            'expectedOutput' => '@import "more-styles.abcd123.css"',
            'expectedDependencies' => ['more-styles.css'],
        ];

        yield 'import_without_url_absolute_path_ignored' => [
            'input' => '@import "/path/to/more-styles.css"',
            'expectedOutput' => '@import "/path/to/more-styles.css"',
            'expectedDependencies' => [],
        ];

        yield 'import_without_url_http_url_ignored' => [
            'input' => '@import "https://cdn.io/more-styles.css"',
            'expectedOutput' => '@import "https://cdn.io/more-styles.css"',
            'expectedDependencies' => [],
        ];

        yield 'import_without_url_data_uri_ignored' => [
            'input' => '@import "data:text/css;base64,LmJvZHkgeyBiYWNrZ3JvdW5kOiByZWQ7IH0="',
            'expectedOutput' => '@import "data:text/css;base64,LmJvZHkgeyBiYWNrZ3JvdW5kOiByZWQ7IH0="',
            'expectedDependencies' => [],
        ];

        yield 'import_without_url_in_comments_ignored' => [
            'input' => 'body { background: url("images/foo.png"); } /* @import "more-styles.css" */',
            'expectedOutput' => 'body { background: url("images/foo.123456.png"); } /* @import "more-styles.css" */',
            'expectedDependencies' => ['images/foo.png'],
        ];

        yield 'mixed_url_and_import_without_url' => [
            'input' => <<<EOF
                @import "more-styles.css";
                body { background: url("images/foo.png"); }
                @import url(./more-styles.css);
                EOF,
            'expectedOutput' => <<<EOF
                @import "more-styles.abcd123.css";
                body { background: url("images/foo.123456.png"); }
                @import url("more-styles.abcd123.css");
                EOF,
            'expectedDependencies' => ['more-styles.css', 'images/foo.png', 'more-styles.css'],
        ];
    }

    public function testCompileFindsRelativeFilesViaSourcePath()
    {
        $assetMapper = $this->createStub(AssetMapperInterface::class);
        $assetMapper
            ->method('getAssetFromSourcePath')
            ->willReturnCallback(static function ($path) {
                return match ($path) {
                    '/project/assets/images/foo.png' => new MappedAsset('images/foo.png',
                        publicPathWithoutDigest: '/assets/images/foo.png',
                        publicPath: '/assets/images/foo.123456.png',
                    ),
                    '/project/more-styles.css' => new MappedAsset('more-styles.css',
                        publicPathWithoutDigest: '/assets/more-styles.css',
                        publicPath: '/assets/more-styles.abcd123.css',
                    ),
                    default => null,
                };
            });

        $compiler = new CssAssetUrlCompiler();
        $asset = new MappedAsset('styles.css', '/project/assets/styles.css', '/assets/styles.css');
        $input = <<<EOF
            body {
                background: url("images/foo.png");
            }
            @import url("../more-styles.css");
            EOF;
        $expectedOutput = <<<EOF
            body {
                background: url("images/foo.123456.png");
            }
            @import url("more-styles.abcd123.css");
            EOF;
        $this->assertSame($expectedOutput, $compiler->compile($input, $asset, $assetMapper));
    }

    #[DataProvider('provideStrictModeTests')]
    public function testStrictMode(string $sourceLogicalName, string $input, ?string $expectedExceptionMessage)
    {
        if (null !== $expectedExceptionMessage) {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage($expectedExceptionMessage);
        }

        $asset = new MappedAsset($sourceLogicalName, '/path/to/styles.css');

        $compiler = new CssAssetUrlCompiler(AssetCompilerInterface::MISSING_IMPORT_STRICT, new NullLogger());
        $this->assertSame($input, $compiler->compile($input, $asset, $this->createStub(AssetMapperInterface::class)));
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

        yield 'importing_non_existent_file_without_url_throws_exception' => [
            'sourceLogicalName' => 'styles.css',
            'input' => '@import "non-existent.css"',
            'expectedExceptionMessage' => 'Unable to find asset "non-existent.css" referenced in "/path/to/styles.css".',
        ];

        yield 'importing_absolute_file_path_without_url_is_ignored' => [
            'sourceLogicalName' => 'styles.css',
            'input' => '@import "/path/to/non-existent.css"',
            'expectedExceptionMessage' => null,
        ];

        yield 'importing_a_url_without_url_is_ignored' => [
            'sourceLogicalName' => 'styles.css',
            'input' => '@import "https://cdn.io/non-existent.css"',
            'expectedExceptionMessage' => null,
        ];
    }
}
