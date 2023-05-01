<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\AssetMapper\AssetMapperRepository;

class AssetMapperRepositoryTest extends TestCase
{
    public function testFindWithAbsolutePaths()
    {
        $repository = new AssetMapperRepository([
            __DIR__.'/fixtures/dir1' => '',
            __DIR__.'/fixtures/dir2' => '',
        ], __DIR__);

        $this->assertSame(__DIR__.'/fixtures/dir1/file1.css', $repository->find('file1.css'));
        $this->assertSame(__DIR__.'/fixtures/dir2/file4.js', $repository->find('file4.js'));
        $this->assertSame(__DIR__.'/fixtures/dir2/subdir/file5.js', $repository->find('subdir/file5.js'));
        $this->assertNull($repository->find('file5.css'));
    }

    public function testFindWithRelativePaths()
    {
        $repository = new AssetMapperRepository([
            'dir1' => '',
            'dir2' => '',
        ], __DIR__.'/fixtures');

        $this->assertSame(__DIR__.'/fixtures/dir1/file1.css', $repository->find('file1.css'));
        $this->assertSame(__DIR__.'/fixtures/dir2/file4.js', $repository->find('file4.js'));
        $this->assertSame(__DIR__.'/fixtures/dir2/subdir/file5.js', $repository->find('subdir/file5.js'));
        $this->assertNull($repository->find('file5.css'));
    }

    public function testFindWithNamespaces()
    {
        $repository = new AssetMapperRepository([
            'dir1' => 'dir1_namespace',
            'dir2' => 'dir2_namespace',
        ], __DIR__.'/fixtures');

        $this->assertSame(__DIR__.'/fixtures/dir1/file1.css', $repository->find('dir1_namespace/file1.css'));
        $this->assertSame(__DIR__.'/fixtures/dir2/file4.js', $repository->find('dir2_namespace/file4.js'));
        $this->assertSame(__DIR__.'/fixtures/dir2/subdir/file5.js', $repository->find('dir2_namespace/subdir/file5.js'));
        // non-namespaced path does not work
        $this->assertNull($repository->find('file4.js'));
    }

    public function testFindLogicalPath()
    {
        $repository = new AssetMapperRepository([
            'dir1' => '',
            'dir2' => '',
        ], __DIR__.'/fixtures');
        $this->assertSame('subdir/file5.js', $repository->findLogicalPath(__DIR__.'/fixtures/dir2/subdir/file5.js'));
    }

    public function testAll()
    {
        $repository = new AssetMapperRepository([
            'dir1' => '',
            'dir2' => '',
            'dir3' => '',
        ], __DIR__.'/fixtures');

        $actualAllAssets = $repository->all();
        $this->assertCount(8, $actualAllAssets);

        // use realpath to normalize slashes on Windows for comparison
        $expectedAllAssets = array_map('realpath', [
            'file1.css' => __DIR__.'/fixtures/dir1/file1.css',
            'file2.js' => __DIR__.'/fixtures/dir1/file2.js',
            'already-abcdefVWXYZ0123456789.digested.css' => __DIR__.'/fixtures/dir2/already-abcdefVWXYZ0123456789.digested.css',
            'file3.css' => __DIR__.'/fixtures/dir2/file3.css',
            'file4.js' => __DIR__.'/fixtures/dir2/file4.js',
            'subdir'.DIRECTORY_SEPARATOR.'file5.js' => __DIR__.'/fixtures/dir2/subdir/file5.js',
            'subdir'.DIRECTORY_SEPARATOR.'file6.js' => __DIR__.'/fixtures/dir2/subdir/file6.js',
            'test.gif.foo' => __DIR__.'/fixtures/dir3/test.gif.foo',
        ]);
        $this->assertEquals($expectedAllAssets, array_map('realpath', $actualAllAssets));
    }

    public function testAllWithNamespaces()
    {
        $repository = new AssetMapperRepository([
            'dir1' => 'dir1_namespace',
            'dir2' => 'dir2_namespace',
            'dir3' => 'dir3_namespace',
        ], __DIR__.'/fixtures');

        $expectedAllAssets = [
            'dir1_namespace/file1.css' => __DIR__.'/fixtures/dir1/file1.css',
            'dir1_namespace/file2.js' => __DIR__.'/fixtures/dir1/file2.js',
            'dir2_namespace/already-abcdefVWXYZ0123456789.digested.css' => __DIR__.'/fixtures/dir2/already-abcdefVWXYZ0123456789.digested.css',
            'dir2_namespace/file3.css' => __DIR__.'/fixtures/dir2/file3.css',
            'dir2_namespace/file4.js' => __DIR__.'/fixtures/dir2/file4.js',
            'dir2_namespace/subdir/file5.js' => __DIR__.'/fixtures/dir2/subdir/file5.js',
            'dir2_namespace/subdir/file6.js' => __DIR__.'/fixtures/dir2/subdir/file6.js',
            'dir3_namespace/test.gif.foo' => __DIR__.'/fixtures/dir3/test.gif.foo',
        ];

        $normalizedExpectedAllAssets = [];
        foreach ($expectedAllAssets as $key => $val) {
            $normalizedExpectedAllAssets[str_replace('/', DIRECTORY_SEPARATOR, $key)] = realpath($val);
        }

        $actualAssets = $repository->all();
        $normalizedActualAssets = [];
        foreach ($actualAssets as $key => $val) {
            $normalizedActualAssets[str_replace('/', DIRECTORY_SEPARATOR, $key)] = realpath($val);
        }

        $this->assertEquals($normalizedExpectedAllAssets, $normalizedActualAssets);
    }
}
