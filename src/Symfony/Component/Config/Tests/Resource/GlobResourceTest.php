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

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Resource\GlobResource;
use Symfony\Component\Filesystem\Filesystem;

class GlobResourceTest extends TestCase
{
    private ?string $tmpDir = null;

    protected function tearDown(): void
    {
        $dir = \dirname(__DIR__).'/Fixtures';
        @rmdir($dir.'/TmpGlob');
        @unlink($dir.'/TmpGlob');
        @unlink($dir.'/Resource/TmpGlob');
        touch($dir.'/Resource/.hiddenFile');

        if (null !== $this->tmpDir) {
            (new Filesystem())->remove([$this->tmpDir, $this->tmpDir.'-target']);
            $this->tmpDir = null;
        }
    }

    #[TestWith(['/Resource'])]
    #[TestWith(['/**/Resource'])]
    #[TestWith(['/**/Resource/'])]
    public function testIterator(string $pattern)
    {
        $dir = \dirname(__DIR__).\DIRECTORY_SEPARATOR.'Fixtures';
        $resource = new GlobResource($dir, $pattern, true);

        $paths = iterator_to_array($resource);

        $file = $dir.'/Resource'.\DIRECTORY_SEPARATOR.'ConditionalClass.php';
        $this->assertEquals([$file => new \SplFileInfo($file)], $paths);
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
            $dir.'/Include/SubDir/ExcludeFile.txt',
            $dir.'/Include/SubDir/IncludeFile.txt',
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

    public function testIsFreshAfterUnserializeIgnoresChangesThatKeepTheSameFiles()
    {
        $dir = $this->createTree(time() - 10);
        $resource = $this->unserializedResource($dir, '', true, [$dir.'/Excluded' => true]);

        $this->assertTrue($resource->isFresh(0));

        file_put_contents($dir.'/Foo/B.php', 'edited');
        file_put_contents($dir.'/Foo/B.php.tmp', 'saved with a rename');
        rename($dir.'/Foo/B.php.tmp', $dir.'/Foo/B.php');
        touch($dir.'/Foo/Bar/.C.php.swp');
        mkdir($dir.'/.git');
        touch($dir.'/.git/HEAD');
        mkdir($dir.'/Foo/New');
        touch($dir.'/Excluded/E.php');

        $this->assertTrue($resource->isFresh(0));
    }

    #[TestWith(['/E.php'])]
    #[TestWith(['/Foo/E.php'])]
    #[TestWith(['/Foo/Bar/E.php'])]
    #[TestWith(['/Empty/E.php'])]
    public function testIsFreshAfterUnserializeDetectsNewFile(string $file)
    {
        $dir = $this->createTree(time() - 10);
        $resource = $this->unserializedResource($dir);

        touch($dir.$file);

        $this->assertFalse($resource->isFresh(0));
    }

    public function testIsFreshAfterUnserializeDetectsNewFileNextToExcludedPrefix()
    {
        $dir = $this->createTree(time() - 10);
        $resource = $this->unserializedResource($dir, '', true, [$dir.'/Foo/Bar' => true]);

        touch($dir.'/Foo/Bar/E.php');
        $this->assertTrue($resource->isFresh(0));

        touch($dir.'/Foo/E.php');
        $this->assertFalse($resource->isFresh(0));
    }

    #[TestWith(['/A.php'])]
    #[TestWith(['/Foo/B.php'])]
    #[TestWith(['/Foo/Bar/C.php'])]
    public function testIsFreshAfterUnserializeDetectsRemovedFile(string $file)
    {
        $dir = $this->createTree(time() - 10);
        $resource = $this->unserializedResource($dir);

        unlink($dir.$file);

        $this->assertFalse($resource->isFresh(0));
    }

    #[TestWith(['/A.php'])]
    #[TestWith(['/Foo/B.php'])]
    #[TestWith(['/Foo/Bar/C.php'])]
    #[TestWith(['/Foo'])]
    #[TestWith(['/Foo/Bar'])]
    public function testIsFreshAfterUnserializeDetectsRenamedPath(string $path)
    {
        $dir = $this->createTree(time() - 10);
        $resource = $this->unserializedResource($dir);

        rename($dir.$path, \dirname($dir.$path).'/Renamed');

        $this->assertFalse($resource->isFresh(0));
    }

    public function testIsFreshAfterUnserializeDetectsFileInNewDirectory()
    {
        $dir = $this->createTree(time() - 10);
        $resource = $this->unserializedResource($dir);

        mkdir($dir.'/Foo/New');
        $this->assertTrue($resource->isFresh(0));

        touch($dir.'/Foo/New/E.php');
        $this->assertFalse($resource->isFresh(0));
    }

    public function testIsFreshAfterUnserializeDetectsFileReplacedByDirectory()
    {
        $dir = $this->createTree(time() - 10);
        $resource = $this->unserializedResource($dir);

        unlink($dir.'/Foo/B.php');
        mkdir($dir.'/Foo/B.php');

        $this->assertFalse($resource->isFresh(0));
    }

    public function testIsFreshAfterUnserializeDetectsDirectoryReplacedByAnotherOneWithTheSameMtime()
    {
        $dir = $this->createTree($mtime = time() - 10);
        mkdir($dir.'-target');
        touch($dir.'-target/E.php');
        $resource = $this->unserializedResource($dir);

        rmdir($dir.'/Empty');
        rename($dir.'-target', $dir.'/Empty');
        touch($dir.'/Empty', $mtime);

        $this->assertFalse($resource->isFresh(0));
    }

    public function testIsFreshAfterUnserializeDetectsRemovedPrefix()
    {
        $dir = $this->createTree(time() - 10);
        $resource = $this->unserializedResource($dir);

        (new Filesystem())->remove($dir);

        $this->assertFalse($resource->isFresh(0));
    }

    #[TestWith(['/*/Bar/*.php', false])]
    #[TestWith(['/*/B*/*.php', false])]
    #[TestWith(['/{Foo,Empty}/Bar/*.php', false])]
    #[TestWith(['/{Foo,Empty}/Bar/*.php', true])]
    #[TestWith(['/**/Bar/*.php', true])]
    public function testIsFreshAfterUnserializeDetectsNewMatchOfWildcardInTheMiddle(string $pattern, bool $recursive)
    {
        $dir = $this->createTree(time() - 10);
        $resource = $this->unserializedResource($dir, $pattern, $recursive);

        touch($dir.'/Foo/E.php');
        mkdir($dir.'/Empty/Bar');
        $this->assertTrue($resource->isFresh(0));

        touch($dir.'/Empty/Bar/E.php');
        $this->assertFalse($resource->isFresh(0));
    }

    #[Group('time-sensitive')]
    #[TestWith([0])]
    #[TestWith([1])]
    public function testIsFreshAfterUnserializeDetectsChangeMadeInTheSameSecondAsTheScan(int $clockLag)
    {
        $dir = $this->createTree(time() - 10);
        touch($dir.'/Foo', $mtime = time() - $clockLag);
        $resource = $this->unserializedResource($dir);

        touch($dir.'/Foo/E.php');
        touch($dir.'/Foo', $mtime);

        $this->assertFalse($resource->isFresh(0));
    }

    public function testIsFreshAfterUnserializeTrustsDirectoryMtimesOlderThanTheScan()
    {
        $dir = $this->createTree($mtime = time() - 10);
        $resource = $this->unserializedResource($dir);

        touch($dir.'/Foo/E.php');
        touch($dir.'/Foo', $mtime);

        $this->assertTrue($resource->isFresh(0));
    }

    public function testIsFreshAfterUnserializeDetectsRemovedSymlinkTarget()
    {
        $dir = $this->createTree(time() - 10);
        mkdir($dir.'-target');
        touch($dir.'-target/T.php');
        if (!@symlink($dir.'-target/T.php', $dir.'/Foo/T.php')) {
            $this->markTestSkipped('Symlinks are not supported.');
        }
        touch($dir.'/Foo', time() - 10);
        $resource = $this->unserializedResource($dir);

        unlink($dir.'-target/T.php');

        $this->assertFalse($resource->isFresh(0));
    }

    public function testIsFreshAfterUnserializeDetectsNewFileInSymlinkedDirectory()
    {
        $dir = $this->createTree(time() - 10);
        mkdir($dir.'-target');
        if (!@symlink($dir.'-target', $dir.'/Foo/Target')) {
            $this->markTestSkipped('Symlinks are not supported.');
        }
        touch($dir.'/Foo', time() - 10);
        touch($dir.'-target', time() - 10);
        $resource = $this->unserializedResource($dir);

        touch($dir.'-target/T.php');
        touch($dir.'-target', time() - 10);

        $this->assertFalse($resource->isFresh(0));
    }

    public function testIsFreshWithTheSerializedFormOfPreviousVersions()
    {
        $dir = $this->createTree($mtime = time() - 10);
        $data = (new GlobResource($dir, '', true))->__serialize();
        unset($data['directories']);
        $resource = unserialize('O:'.\strlen(GlobResource::class).':"'.GlobResource::class.'"'.substr(serialize($data), 1));

        $this->assertTrue($resource->isFresh(0));

        touch($dir.'/Foo/E.php');
        touch($dir.'/Foo', $mtime);

        $this->assertFalse($resource->isFresh(0));
    }

    private function createTree(int $mtime): string
    {
        $dir = $this->tmpDir = sys_get_temp_dir().'/sf_glob_resource_'.bin2hex(random_bytes(4));

        mkdir($dir.'/Foo/Bar', 0o777, true);
        mkdir($dir.'/Excluded');
        mkdir($dir.'/Empty');
        foreach (['/A.php', '/Foo/B.php', '/Foo/Bar/C.php', '/Excluded/D.php'] as $file) {
            touch($dir.$file);
        }

        foreach (['', '/Foo', '/Foo/Bar', '/Excluded', '/Empty'] as $path) {
            touch($dir.$path, $mtime);
        }

        return str_replace('\\', '/', realpath($dir));
    }

    private function unserializedResource(string $dir, string $pattern = '', bool $recursive = true, array $excludedPrefixes = []): GlobResource
    {
        return unserialize(serialize(new GlobResource($dir, $pattern, $recursive, false, $excludedPrefixes)));
    }
}
