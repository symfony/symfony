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
use Symfony\Component\Finder\Glob;

class AssetMapperRepositoryTest extends TestCase
{
    public function testFindWithAbsolutePaths()
    {
        $repository = new AssetMapperRepository([
            __DIR__.'/Fixtures/dir1' => '',
            __DIR__.'/Fixtures/dir2' => '',
        ], __DIR__);

        $this->assertSame(realpath(__DIR__.'/Fixtures/dir1/file1.css'), $repository->find('file1.css'));
        $this->assertSame(realpath(__DIR__.'/Fixtures/dir2/file4.js'), $repository->find('file4.js'));
        $this->assertSame(realpath(__DIR__.'/Fixtures/dir2/subdir/file5.js'), $repository->find('subdir/file5.js'));
        $this->assertNull($repository->find('file5.css'));
    }

    public function testFindWithRelativePaths()
    {
        $repository = new AssetMapperRepository([
            'dir1' => '',
            'dir2' => '',
        ], __DIR__.'/Fixtures');

        $this->assertSame(realpath(__DIR__.'/Fixtures/dir1/file1.css'), $repository->find('file1.css'));
        $this->assertSame(realpath(__DIR__.'/Fixtures/dir2/file4.js'), $repository->find('file4.js'));
        $this->assertSame(realpath(__DIR__.'/Fixtures/dir2/subdir/file5.js'), $repository->find('subdir/file5.js'));
        $this->assertNull($repository->find('file5.css'));
    }

    public function testFindWithMovingPaths()
    {
        $repository = new AssetMapperRepository([
            __DIR__.'/../Tests/Fixtures/dir2' => '',
        ], __DIR__);

        $this->assertSame(realpath(__DIR__.'/Fixtures/dir2/file4.js'), $repository->find('file4.js'));
        $this->assertSame(realpath(__DIR__.'/Fixtures/dir2/file4.js'), $repository->find('subdir/../file4.js'));
    }

    public function testFindWithNamespaces()
    {
        $repository = new AssetMapperRepository([
            'dir1' => 'dir1_namespace',
            'dir2' => 'dir2_namespace',
        ], __DIR__.'/Fixtures');

        $this->assertSame(realpath(__DIR__.'/Fixtures/dir1/file1.css'), $repository->find('dir1_namespace/file1.css'));
        $this->assertSame(realpath(__DIR__.'/Fixtures/dir2/file4.js'), $repository->find('dir2_namespace/file4.js'));
        $this->assertSame(realpath(__DIR__.'/Fixtures/dir2/subdir/file5.js'), $repository->find('dir2_namespace/subdir/file5.js'));
        // non-namespaced path does not work
        $this->assertNull($repository->find('file4.js'));
    }

    public function testFindLogicalPath()
    {
        $repository = new AssetMapperRepository([
            'dir' => '',
            'dir1' => 'some_namespace',
            'dir2' => '',
        ], __DIR__.'/Fixtures');
        $this->assertSame('subdir/file5.js', $repository->findLogicalPath(__DIR__.'/Fixtures/dir2/subdir/file5.js'));
        $this->assertSame('some_namespace/file2.js', $repository->findLogicalPath(__DIR__.'/Fixtures/dir1/file2.js'));
        $this->assertSame('some_namespace/file2.js', $repository->findLogicalPath(__DIR__.'/../Tests/Fixtures/dir1/file2.js'));
    }

    public function testAll()
    {
        $repository = new AssetMapperRepository([
            'dir1' => '',
            'dir2' => '',
            'dir3' => '',
        ], __DIR__.'/Fixtures');

        $actualAllAssets = $repository->all();
        $this->assertCount(8, $actualAllAssets);

        // use realpath to normalize slashes on Windows for comparison
        $expectedAllAssets = array_map('realpath', [
            'file1.css' => __DIR__.'/Fixtures/dir1/file1.css',
            'file2.js' => __DIR__.'/Fixtures/dir1/file2.js',
            'already-abcdefVWXYZ0123456789.digested.css' => __DIR__.'/Fixtures/dir2/already-abcdefVWXYZ0123456789.digested.css',
            'file3.css' => __DIR__.'/Fixtures/dir2/file3.css',
            'file4.js' => __DIR__.'/Fixtures/dir2/file4.js',
            'subdir/file5.js' => __DIR__.'/Fixtures/dir2/subdir/file5.js',
            'subdir/file6.js' => __DIR__.'/Fixtures/dir2/subdir/file6.js',
            'test.gif.foo' => __DIR__.'/Fixtures/dir3/test.gif.foo',
        ]);
        $this->assertEquals($expectedAllAssets, array_map('realpath', $actualAllAssets));
    }

    public function testAllWithNamespaces()
    {
        $repository = new AssetMapperRepository([
            'dir1' => 'dir1_namespace',
            'dir2' => 'dir2_namespace',
            'dir3' => 'dir3_namespace',
        ], __DIR__.'/Fixtures');

        $expectedAllAssets = [
            'dir1_namespace/file1.css' => __DIR__.'/Fixtures/dir1/file1.css',
            'dir1_namespace/file2.js' => __DIR__.'/Fixtures/dir1/file2.js',
            'dir2_namespace/already-abcdefVWXYZ0123456789.digested.css' => __DIR__.'/Fixtures/dir2/already-abcdefVWXYZ0123456789.digested.css',
            'dir2_namespace/file3.css' => __DIR__.'/Fixtures/dir2/file3.css',
            'dir2_namespace/file4.js' => __DIR__.'/Fixtures/dir2/file4.js',
            'dir2_namespace/subdir/file5.js' => __DIR__.'/Fixtures/dir2/subdir/file5.js',
            'dir2_namespace/subdir/file6.js' => __DIR__.'/Fixtures/dir2/subdir/file6.js',
            'dir3_namespace/test.gif.foo' => __DIR__.'/Fixtures/dir3/test.gif.foo',
        ];

        $normalizedExpectedAllAssets = array_map('realpath', $expectedAllAssets);

        $actualAssets = $repository->all();
        $normalizedActualAssets = array_map('realpath', $actualAssets);

        $this->assertEquals($normalizedExpectedAllAssets, $normalizedActualAssets);
    }

    public function testExcludedPaths()
    {
        $excludedPatterns = [
            '*/subdir/*',
            '*/*3.css',
            '*/*.digested.*',
        ];
        $excludedGlobs = array_map(function ($pattern) {
            // globbed equally in FrameworkExtension
            return Glob::toRegex($pattern, true, false);
        }, $excludedPatterns);
        $repository = new AssetMapperRepository([
            'dir1' => '',
            'dir2' => '',
            'dir3' => '',
        ], __DIR__.'/Fixtures', $excludedGlobs);

        $expectedAssets = [
            'file1.css',
            'file2.js',
            'file4.js',
            'test.gif.foo',
        ];

        $actualAssets = array_keys($repository->all());
        sort($actualAssets);
        $this->assertEquals($expectedAssets, $actualAssets);

        $this->assertNull($repository->find('file3.css'));
        $this->assertNull($repository->findLogicalPath(__DIR__.'/Fixtures/dir2/file3.css'));
    }

    public function testDotFilesExcluded()
    {
        $repository = new AssetMapperRepository([
            'dot_file' => '',
        ], __DIR__.'/Fixtures', [], true);

        $actualAssets = array_keys($repository->all());
        $this->assertEquals([], $actualAssets);
    }

    public function testDotFilesNotExcluded()
    {
        $repository = new AssetMapperRepository([
            'dot_file' => '',
        ], __DIR__.'/Fixtures', [], false);

        $actualAssets = array_keys($repository->all());
        $this->assertEquals(['.dotfile'], $actualAssets);
    }
}
