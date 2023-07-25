<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Tests\Loader;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Exception\FileLoaderImportCircularReferenceException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\FileLocatorInterface;
use Symfony\Component\Config\Loader\FileLoader;
use Symfony\Component\Config\Loader\LoaderResolver;

class FileLoaderTest extends TestCase
{
    public function testImportWithFileLocatorDelegation()
    {
        $locatorMock = $this->createMock(FileLocatorInterface::class);

        $locatorMockForAdditionalLoader = $this->createMock(FileLocatorInterface::class);
        $locatorMockForAdditionalLoader->expects($this->any())->method('locate')->will($this->onConsecutiveCalls(
            ['path/to/file1'],                    // Default
            ['path/to/file1', 'path/to/file2'],   // First is imported
            ['path/to/file1', 'path/to/file2'],   // Second is imported
            ['path/to/file1'],                    // Exception
            ['path/to/file1', 'path/to/file2']    // Exception
        ));

        $fileLoader = new TestFileLoader($locatorMock);
        $fileLoader->setSupports(false);
        $fileLoader->setCurrentDir('.');

        $additionalLoader = new TestFileLoader($locatorMockForAdditionalLoader);
        $additionalLoader->setCurrentDir('.');

        $fileLoader->setResolver($loaderResolver = new LoaderResolver([$fileLoader, $additionalLoader]));

        // Default case
        $this->assertSame('path/to/file1', $fileLoader->import('my_resource'));

        // Check first file is imported if not already loading
        $this->assertSame('path/to/file1', $fileLoader->import('my_resource'));

        // Check second file is imported if first is already loading
        $fileLoader->addLoading('path/to/file1');
        $this->assertSame('path/to/file2', $fileLoader->import('my_resource'));

        // Check exception throws if first (and only available) file is already loading
        try {
            $fileLoader->import('my_resource');
            $this->fail('->import() throws a FileLoaderImportCircularReferenceException if the resource is already loading');
        } catch (\Exception $e) {
            $this->assertInstanceOf(FileLoaderImportCircularReferenceException::class, $e, '->import() throws a FileLoaderImportCircularReferenceException if the resource is already loading');
        }

        // Check exception throws if all files are already loading
        try {
            $fileLoader->addLoading('path/to/file2');
            $fileLoader->import('my_resource');
            $this->fail('->import() throws a FileLoaderImportCircularReferenceException if the resource is already loading');
        } catch (\Exception $e) {
            $this->assertInstanceOf(FileLoaderImportCircularReferenceException::class, $e, '->import() throws a FileLoaderImportCircularReferenceException if the resource is already loading');
        }
    }

    public function testImportWithGlobLikeResource()
    {
        $locatorMock = $this->createMock(FileLocatorInterface::class);
        $locatorMock->expects($this->once())->method('locate')->willReturn('');
        $loader = new TestFileLoader($locatorMock);

        $this->assertSame('[foo]', $loader->import('[foo]'));
    }

    public function testImportWithGlobLikeResourceWhichContainsSlashes()
    {
        $locatorMock = $this->createMock(FileLocatorInterface::class);
        $locatorMock->expects($this->once())->method('locate')->willReturn('');
        $loader = new TestFileLoader($locatorMock);

        $this->assertNull($loader->import('foo/bar[foo]'));
    }

    public function testImportWithGlobLikeResourceWhichContainsMultipleLines()
    {
        $locatorMock = $this->createMock(FileLocatorInterface::class);
        $loader = new TestFileLoader($locatorMock);

        $this->assertSame("foo\nfoobar[foo]", $loader->import("foo\nfoobar[foo]"));
    }

    public function testImportWithGlobLikeResourceWhichContainsSlashesAndMultipleLines()
    {
        $locatorMock = $this->createMock(FileLocatorInterface::class);
        $loader = new TestFileLoader($locatorMock);

        $this->assertSame("foo\nfoo/bar[foo]", $loader->import("foo\nfoo/bar[foo]"));
    }

    public function testImportWithNoGlobMatch()
    {
        $locatorMock = $this->createMock(FileLocatorInterface::class);
        $locatorMock->expects($this->once())->method('locate')->willReturn('');
        $loader = new TestFileLoader($locatorMock);

        $this->assertNull($loader->import('./*.abc'));
    }

    public function testImportWithSimpleGlob()
    {
        $loader = new TestFileLoader(new FileLocator(__DIR__));

        $this->assertSame(__FILE__, strtr($loader->import('FileLoaderTest.*'), '/', \DIRECTORY_SEPARATOR));
    }

    public function testImportWithExclude()
    {
        $loader = new TestFileLoader(new FileLocator(__DIR__.'/../Fixtures'));
        $loadedFiles = $loader->import('Include/*', null, false, null, __DIR__.'/../Fixtures/Include/{ExcludeFile.txt}');
        $this->assertCount(2, $loadedFiles);
        $this->assertNotContains('ExcludeFile.txt', $loadedFiles);
    }

    /**
     * @dataProvider excludeTrailingSlashConsistencyProvider
     */
    public function testExcludeTrailingSlashConsistency(string $exclude)
    {
        $loader = new TestFileLoader(new FileLocator(__DIR__.'/../Fixtures'));
        $loadedFiles = $loader->import('ExcludeTrailingSlash/*', null, false, null, $exclude);
        $this->assertCount(2, $loadedFiles);
        $this->assertNotContains('baz.txt', $loadedFiles);
    }

    public static function excludeTrailingSlashConsistencyProvider(): iterable
    {
        yield [__DIR__.'/../Fixtures/Exclude/ExcludeToo/'];
        yield [__DIR__.'/../Fixtures/Exclude/ExcludeToo'];
        yield [__DIR__.'/../Fixtures/Exclude/ExcludeToo/*'];
        yield [__DIR__.'/../Fixtures/*/ExcludeToo'];
        yield [__DIR__.'/../Fixtures/*/ExcludeToo/'];
        yield [__DIR__.'/../Fixtures/Exclude/ExcludeToo/*'];
        yield [__DIR__.'/../Fixtures/Exclude/ExcludeToo/AnotheExcludedFile.txt'];
    }
}

class TestFileLoader extends FileLoader
{
    private bool $supports = true;

    public function load(mixed $resource, string $type = null): mixed
    {
        return $resource;
    }

    public function supports(mixed $resource, string $type = null): bool
    {
        return $this->supports;
    }

    public function addLoading(string $resource): void
    {
        self::$loading[$resource] = true;
    }

    public function setSupports(bool $supports): void
    {
        $this->supports = $supports;
    }
}
