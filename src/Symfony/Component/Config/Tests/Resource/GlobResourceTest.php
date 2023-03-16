<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Tests\Resource;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Resource\GlobResource;

class GlobResourceTest extends TestCase
{
    protected function tearDown(): void
    {
        $dir = \dirname(__DIR__).'/Fixtures';
        @rmdir($dir.'/TmpGlob');
        @unlink($dir.'/TmpGlob');
        @unlink($dir.'/Resource/TmpGlob');
        touch($dir.'/Resource/.hiddenFile');
    }

    public function testIterator()
    {
        $dir = \dirname(__DIR__).\DIRECTORY_SEPARATOR.'Fixtures';
        $resource = new GlobResource($dir, '/Resource', true);

        $paths = iterator_to_array($resource);

        $file = $dir.'/Resource'.\DIRECTORY_SEPARATOR.'ConditionalClass.php';
        $this->assertEquals([$file => new \SplFileInfo($file)], $paths);
        $this->assertInstanceOf(\SplFileInfo::class, current($paths));
        $this->assertSame($dir, $resource->getPrefix());

        $resource = new GlobResource($dir, '/**/Resource', true);

        $paths = iterator_to_array($resource);

        $file = $dir.'/Resource'.\DIRECTORY_SEPARATOR.'ConditionalClass.php';
        $this->assertEquals([$file => $file], $paths);
        $this->assertInstanceOf(\SplFileInfo::class, current($paths));
        $this->assertSame($dir, $resource->getPrefix());
    }

    public function testIteratorForExclusionDoesntIterateThroughSubfolders()
    {
        $dir = \dirname(__DIR__).\DIRECTORY_SEPARATOR.'Fixtures';
        $resource = new GlobResource($dir, \DIRECTORY_SEPARATOR.'Exclude', true, true);

        $paths = iterator_to_array($resource);

        $file = $dir.\DIRECTORY_SEPARATOR.'Exclude';
        $this->assertArrayHasKey($file, $paths);
        $this->assertCount(1, $paths);
    }

    public function testIteratorSkipsFoldersForGivenExcludedPrefixes()
    {
        $dir = \dirname(__DIR__).\DIRECTORY_SEPARATOR.'Fixtures';
        $resource = new GlobResource($dir, '/*Exclude*', true, false, [$dir.\DIRECTORY_SEPARATOR.'Exclude' => true]);

        $paths = iterator_to_array($resource);

        $file = $dir.\DIRECTORY_SEPARATOR.'Exclude'.\DIRECTORY_SEPARATOR.'AnExcludedFile.txt';
        $this->assertArrayNotHasKey($file, $paths);

        $file = $dir.\DIRECTORY_SEPARATOR.'Exclude'.\DIRECTORY_SEPARATOR.'ExcludeToo'.\DIRECTORY_SEPARATOR.'AnotheExcludedFile.txt';
        $this->assertArrayNotHasKey($file, $paths);
    }

    public function testIteratorSkipsSubfoldersForGivenExcludedPrefixes()
    {
        $dir = \dirname(__DIR__).\DIRECTORY_SEPARATOR.'Fixtures';
        $resource = new GlobResource($dir, '/*Exclude/*', true, false, [$dir.\DIRECTORY_SEPARATOR.'Exclude' => true]);

        $paths = iterator_to_array($resource);

        $file = $dir.\DIRECTORY_SEPARATOR.'Exclude'.\DIRECTORY_SEPARATOR.'AnExcludedFile.txt';
        $this->assertArrayNotHasKey($file, $paths);

        $file = $dir.\DIRECTORY_SEPARATOR.'Exclude'.\DIRECTORY_SEPARATOR.'ExcludeToo'.\DIRECTORY_SEPARATOR.'AnotheExcludedFile.txt';
        $this->assertArrayNotHasKey($file, $paths);
    }

    public function testIteratorSkipsFoldersWithForwardSlashForGivenExcludedPrefixes()
    {
        $dir = \dirname(__DIR__).\DIRECTORY_SEPARATOR.'Fixtures';
        $resource = new GlobResource($dir, '/*Exclude*', true, false, [$dir.'/Exclude' => true]);

        $paths = iterator_to_array($resource);

        $file = $dir.\DIRECTORY_SEPARATOR.'Exclude/AnExcludedFile.txt';
        $this->assertArrayNotHasKey($file, $paths);

        $file = $dir.\DIRECTORY_SEPARATOR.'Exclude'.\DIRECTORY_SEPARATOR.'ExcludeToo'.\DIRECTORY_SEPARATOR.'AnotheExcludedFile.txt';
        $this->assertArrayNotHasKey($file, $paths);
    }

    public function testIsFreshNonRecursiveDetectsNewFile()
    {
        $dir = \dirname(__DIR__).'/Fixtures';
        $resource = new GlobResource($dir, '/*', false);

        $this->assertTrue($resource->isFresh(0));

        mkdir($dir.'/TmpGlob');
        $this->assertTrue($resource->isFresh(0));

        rmdir($dir.'/TmpGlob');
        $this->assertTrue($resource->isFresh(0));

        touch($dir.'/TmpGlob');
        $this->assertFalse($resource->isFresh(0));

        unlink($dir.'/TmpGlob');
        $this->assertTrue($resource->isFresh(0));
    }

    public function testIsFreshNonRecursiveDetectsRemovedFile()
    {
        $dir = \dirname(__DIR__).'/Fixtures';
        $resource = new GlobResource($dir, '/*', false);

        touch($dir.'/TmpGlob');
        touch($dir.'/.TmpGlob');
        $this->assertTrue($resource->isFresh(0));

        unlink($dir.'/.TmpGlob');
        $this->assertTrue($resource->isFresh(0));

        unlink($dir.'/TmpGlob');
        $this->assertFalse($resource->isFresh(0));
    }

    public function testIsFreshRecursiveDetectsRemovedFile()
    {
        $dir = \dirname(__DIR__).'/Fixtures';
        $resource = new GlobResource($dir, '/*', true);

        touch($dir.'/Resource/TmpGlob');
        $this->assertTrue($resource->isFresh(0));

        unlink($dir.'/Resource/TmpGlob');
        $this->assertFalse($resource->isFresh(0));

        touch($dir.'/Resource/TmpGlob');
        $this->assertTrue($resource->isFresh(0));

        unlink($dir.'/Resource/.hiddenFile');
        $this->assertTrue($resource->isFresh(0));
    }

    public function testIsFreshRecursiveDetectsNewFile()
    {
        $dir = \dirname(__DIR__).'/Fixtures';
        $resource = new GlobResource($dir, '/*', true);

        $this->assertTrue($resource->isFresh(0));

        touch($dir.'/Resource/TmpGlob');
        $this->assertFalse($resource->isFresh(0));
    }

    public function testBraceFallback()
    {
        $dir = \dirname(__DIR__).\DIRECTORY_SEPARATOR.'Fixtures';
        $resource = new GlobResource($dir, '/*{/*/*.txt,.x{m,n}l}', true);

        $p = new \ReflectionProperty($resource, 'globBrace');
        $p->setValue($resource, 0);

        $expected = [
            $dir.'/Exclude/ExcludeToo/AnotheExcludedFile.txt',
            $dir.'/ExcludeTrailingSlash/exclude/baz.txt',
            $dir.'/foo.xml',
        ];

        $this->assertSame($expected, array_keys(iterator_to_array($resource)));
    }

    public function testUnbalancedBraceFallback()
    {
        $dir = \dirname(__DIR__).\DIRECTORY_SEPARATOR.'Fixtures';
        $resource = new GlobResource($dir, '/*{/*/*.txt,.x{m,nl}', true);

        $p = new \ReflectionProperty($resource, 'globBrace');
        $p->setValue($resource, 0);

        $this->assertSame([], array_keys(iterator_to_array($resource)));
    }

    public function testSerializeUnserialize()
    {
        $dir = \dirname(__DIR__).\DIRECTORY_SEPARATOR.'Fixtures';
        $resource = new GlobResource($dir, '/Resource', true);

        $newResource = unserialize(serialize($resource));

        $p = new \ReflectionProperty($resource, 'globBrace');

        $this->assertEquals($p->getValue($resource), $p->getValue($newResource));
    }

    public function testPhar()
    {
        $s = \DIRECTORY_SEPARATOR;
        $cwd = getcwd();
        chdir(\dirname(__DIR__).'/Fixtures');
        try {
            $resource = new GlobResource('phar://some.phar', '*', true);
            $files = array_keys(iterator_to_array($resource));
            $this->assertSame(["phar://some.phar{$s}ProjectWithXsdExtensionInPhar.php", "phar://some.phar{$s}schema{$s}project-1.0.xsd"], $files);

            $resource = new GlobResource("phar://some.phar{$s}ProjectWithXsdExtensionInPhar.php", '', true);
            $files = array_keys(iterator_to_array($resource));
            $this->assertSame(["phar://some.phar{$s}ProjectWithXsdExtensionInPhar.php"], $files);
        } finally {
            chdir($cwd);
        }
    }

    public function testFilePrefix()
    {
        $resource = new GlobResource(__FILE__, '/**/', true);
        $files = array_keys(iterator_to_array($resource));
        $this->assertSame([], $files);
    }
}
