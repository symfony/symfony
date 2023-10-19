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
use Symfony\Component\AssetMapper\Compiler\AssetCompilerPathResolverTrait;
use Symfony\Component\AssetMapper\Exception\RuntimeException;

class AssetCompilerPathResolverTraitTest extends TestCase
{
    /**
     * @dataProvider provideCompileTests
     */
    public function testResolvePath(string $directory, string $filename, string $expectedPath)
    {
        $resolver = new StubTestAssetCompilerPathResolver();
        $this->assertSame($expectedPath, $resolver->doResolvePath($directory, $filename));
    }

    public static function provideCompileTests(): iterable
    {
        yield 'simple_empty_directory' => [
            'directory' => '',
            'input' => 'other.js',
            'expectedOutput' => 'other.js',
        ];

        yield 'single_dot' => [
            'directory' => 'subdir',
            'input' => './other.js',
            'expectedOutput' => 'subdir/other.js',
        ];

        yield 'double_dot' => [
            'directory' => 'subdir',
            'input' => '../other.js',
            'expectedOutput' => 'other.js',
        ];

        yield 'mixture_of_dots' => [
            'directory' => 'subdir/another-dir/third-dir',
            'input' => './.././../other.js',
            'expectedOutput' => 'subdir/other.js',
        ];
    }

    public function testExceptionIfPathGoesAboveDirectory()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot import the file "../../other.js": it is outside the current "subdir" directory.');

        $resolver = new StubTestAssetCompilerPathResolver();
        $resolver->doResolvePath('subdir', '../../other.js');
    }

    /**
     * @dataProvider getCreateRelativePathTests
     */
    public function testCreateRelativePath(string $fromPath, string $toPath, string $expectedPath)
    {
        $resolver = new StubTestAssetCompilerPathResolver();
        $this->assertSame($expectedPath, $resolver->doCreateRelativePath($fromPath, $toPath));
    }

    public static function getCreateRelativePathTests(): iterable
    {
        yield 'same directory' => [
            'fromPath' => 'subdir/foo.js',
            'toPath' => 'subdir/other.js',
            'expectedPath' => 'other.js',
        ];

        yield 'both in root directory' => [
            'fromPath' => 'foo.js',
            'toPath' => 'other.js',
            'expectedPath' => 'other.js',
        ];

        yield 'toPath lives in subdirectory' => [
            'fromPath' => 'foo.js',
            'toPath' => 'subdir/other.js',
            'expectedPath' => 'subdir/other.js',
        ];

        yield 'fromPath lives in subdirectory' => [
            'fromPath' => 'subdir/foo.js',
            'toPath' => 'other.js',
            'expectedPath' => '../other.js',
        ];

        yield 'both paths live in different subdirectories' => [
            'fromPath' => 'subdir/foo.js',
            'toPath' => 'other-dir/other.js',
            'expectedPath' => '../other-dir/other.js',
        ];

        yield 'paths live in different subdirectories, but share a common parent' => [
            'fromPath' => 'subdir/foo.js',
            'toPath' => 'subdir/other-dir/other.js',
            'expectedPath' => 'other-dir/other.js',
        ];

        yield 'paths live in deep subdirectories that are identical' => [
            'fromPath' => 'subdir/another-dir/third-dir/foo.js',
            'toPath' => 'subdir/another-dir/third-dir/other.js',
            'expectedPath' => 'other.js',
        ];
    }
}

class StubTestAssetCompilerPathResolver
{
    use AssetCompilerPathResolverTrait;

    public function doResolvePath(string $directory, string $filename): string
    {
        return $this->resolvePath($directory, $filename);
    }

    public function doCreateRelativePath(string $fromPath, string $toPath): string
    {
        return $this->createRelativePath($fromPath, $toPath);
    }
}
