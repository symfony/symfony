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
        $locatorMock = self::createMock(FileLocatorInterface::class);

        $locatorMockForAdditionalLoader = self::createMock(FileLocatorInterface::class);
        $locatorMockForAdditionalLoader->expects(self::any())->method('locate')->will(self::onConsecutiveCalls(
            ['path/to/file1'],
            // Default
            ['path/to/file1', 'path/to/file2'],
            // First is imported
            ['path/to/file1', 'path/to/file2'],
            // Second is imported
            ['path/to/file1'],
            // Exception
            ['path/to/file1', 'path/to/file2']
        ));

        $fileLoader = new TestFileLoader($locatorMock);
        $fileLoader->setSupports(false);
        $fileLoader->setCurrentDir('.');

        $additionalLoader = new TestFileLoader($locatorMockForAdditionalLoader);
        $additionalLoader->setCurrentDir('.');

        $fileLoader->setResolver($loaderResolver = new LoaderResolver([$fileLoader, $additionalLoader]));

        // Default case
        self::assertSame('path/to/file1', $fileLoader->import('my_resource'));

        // Check first file is imported if not already loading
        self::assertSame('path/to/file1', $fileLoader->import('my_resource'));

        // Check second file is imported if first is already loading
        $fileLoader->addLoading('path/to/file1');
        self::assertSame('path/to/file2', $fileLoader->import('my_resource'));

        // Check exception throws if first (and only available) file is already loading
        try {
            $fileLoader->import('my_resource');
            self::fail('->import() throws a FileLoaderImportCircularReferenceException if the resource is already loading');
        } catch (\Exception $e) {
            self::assertInstanceOf(FileLoaderImportCircularReferenceException::class, $e, '->import() throws a FileLoaderImportCircularReferenceException if the resource is already loading');
        }

        // Check exception throws if all files are already loading
        try {
            $fileLoader->addLoading('path/to/file2');
            $fileLoader->import('my_resource');
            self::fail('->import() throws a FileLoaderImportCircularReferenceException if the resource is already loading');
        } catch (\Exception $e) {
            self::assertInstanceOf(FileLoaderImportCircularReferenceException::class, $e, '->import() throws a FileLoaderImportCircularReferenceException if the resource is already loading');
        }
    }

    public function testImportWithGlobLikeResource()
    {
        $locatorMock = self::createMock(FileLocatorInterface::class);
        $locatorMock->expects(self::once())->method('locate')->willReturn('');
        $loader = new TestFileLoader($locatorMock);

        self::assertSame('[foo]', $loader->import('[foo]'));
    }

    public function testImportWithGlobLikeResourceWhichContainsSlashes()
    {
        $locatorMock = self::createMock(FileLocatorInterface::class);
        $locatorMock->expects(self::once())->method('locate')->willReturn('');
        $loader = new TestFileLoader($locatorMock);

        self::assertNull($loader->import('foo/bar[foo]'));
    }

    public function testImportWithGlobLikeResourceWhichContainsMultipleLines()
    {
        $locatorMock = self::createMock(FileLocatorInterface::class);
        $loader = new TestFileLoader($locatorMock);

        self::assertSame("foo\nfoobar[foo]", $loader->import("foo\nfoobar[foo]"));
    }

    public function testImportWithGlobLikeResourceWhichContainsSlashesAndMultipleLines()
    {
        $locatorMock = self::createMock(FileLocatorInterface::class);
        $loader = new TestFileLoader($locatorMock);

        self::assertSame("foo\nfoo/bar[foo]", $loader->import("foo\nfoo/bar[foo]"));
    }

    public function testImportWithNoGlobMatch()
    {
        $locatorMock = self::createMock(FileLocatorInterface::class);
        $locatorMock->expects(self::once())->method('locate')->willReturn('');
        $loader = new TestFileLoader($locatorMock);

        self::assertNull($loader->import('./*.abc'));
    }

    public function testImportWithSimpleGlob()
    {
        $loader = new TestFileLoader(new FileLocator(__DIR__));

        self::assertSame(__FILE__, strtr($loader->import('FileLoaderTest.*'), '/', \DIRECTORY_SEPARATOR));
    }

    public function testImportWithExclude()
    {
        $loader = new TestFileLoader(new FileLocator(__DIR__.'/../Fixtures'));
        $loadedFiles = $loader->import('Include/*', null, false, null, __DIR__.'/../Fixtures/Include/{ExcludeFile.txt}');
        self::assertCount(2, $loadedFiles);
        self::assertNotContains('ExcludeFile.txt', $loadedFiles);
    }

    /**
     * @dataProvider excludeTrailingSlashConsistencyProvider
     */
    public function testExcludeTrailingSlashConsistency(string $exclude)
    {
        $loader = new TestFileLoader(new FileLocator(__DIR__.'/../Fixtures'));
        $loadedFiles = $loader->import('ExcludeTrailingSlash/*', null, false, null, $exclude);
        self::assertCount(2, $loadedFiles);
        self::assertNotContains('baz.txt', $loadedFiles);
    }

    public function excludeTrailingSlashConsistencyProvider(): iterable
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
    private $supports = true;

    public function load($resource, string $type = null)
    {
        return $resource;
    }

    public function supports($resource, string $type = null): bool
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
