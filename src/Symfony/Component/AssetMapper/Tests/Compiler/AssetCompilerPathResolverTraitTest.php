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
use Symfony\Component\Asset\Exception\RuntimeException;
use Symfony\Component\AssetMapper\Compiler\AssetCompilerPathResolverTrait;

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
}

class StubTestAssetCompilerPathResolver
{
    use AssetCompilerPathResolverTrait;

    public function doResolvePath(string $directory, string $filename): string
    {
        return $this->resolvePath($directory, $filename);
    }
}
