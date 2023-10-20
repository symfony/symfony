<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper\Tests\ImportMap;

use PHPUnit\Framework\TestCase;
use Symfony\Component\AssetMapper\ImportMap\ImportMapEntry;
use Symfony\Component\AssetMapper\ImportMap\ImportMapType;
use Symfony\Component\AssetMapper\ImportMap\RemotePackageStorage;
use Symfony\Component\Filesystem\Filesystem;

class RemotePackageStorageTest extends TestCase
{
    private Filesystem $filesystem;
    private static string $writableRoot = __DIR__.'/../Fixtures/importmaps_for_writing';

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        if (!file_exists(self::$writableRoot)) {
            $this->filesystem->mkdir(self::$writableRoot);
        }
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove(self::$writableRoot);
    }

    public function testGetStorageDir()
    {
        $storage = new RemotePackageStorage(self::$writableRoot.'/assets/vendor');
        $this->assertSame(realpath(self::$writableRoot.'/assets/vendor'), realpath($storage->getStorageDir()));
    }

    public function testIsDownloaded()
    {
        $storage = new RemotePackageStorage(self::$writableRoot.'/assets/vendor');
        $entry = ImportMapEntry::createRemote('foo', ImportMapType::JS, '/does/not/matter', '1.0.0', 'module_specifier', false);
        $this->assertFalse($storage->isDownloaded($entry));
        $targetPath = self::$writableRoot.'/assets/vendor/module_specifier/module_specifier.index.js';
        @mkdir(\dirname($targetPath), 0777, true);
        file_put_contents($targetPath, 'any content');
        $this->assertTrue($storage->isDownloaded($entry));
    }

    public function testSave()
    {
        $storage = new RemotePackageStorage(self::$writableRoot.'/assets/vendor');
        $entry = ImportMapEntry::createRemote('foo', ImportMapType::JS, '/does/not/matter', '1.0.0', 'module_specifier', false);
        $storage->save($entry, 'any content');
        $targetPath = self::$writableRoot.'/assets/vendor/module_specifier/module_specifier.index.js';
        $this->assertFileExists($targetPath);
        $this->assertEquals('any content', file_get_contents($targetPath));
    }

    /**
     * @dataProvider getDownloadPathTests
     */
    public function testGetDownloadedPath(string $packageModuleSpecifier, ImportMapType $importMapType, string $expectedPath)
    {
        $storage = new RemotePackageStorage(self::$writableRoot.'/assets/vendor');
        $this->assertSame($expectedPath, $storage->getDownloadPath($packageModuleSpecifier, $importMapType));
    }

    public static function getDownloadPathTests()
    {
        yield 'javascript bare package' => [
            'packageModuleSpecifier' => 'foo',
            'importMapType' => ImportMapType::JS,
            'expectedPath' => self::$writableRoot.'/assets/vendor/foo/foo.index.js',
        ];

        yield 'javascript package with path' => [
            'packageModuleSpecifier' => 'foo/bar',
            'importMapType' => ImportMapType::JS,
            'expectedPath' => self::$writableRoot.'/assets/vendor/foo/bar.js',
        ];

        yield 'javascript package with path and extension' => [
            'packageModuleSpecifier' => 'foo/bar.js',
            'importMapType' => ImportMapType::JS,
            'expectedPath' => self::$writableRoot.'/assets/vendor/foo/bar.js',
        ];

        yield 'CSS package with path' => [
            'packageModuleSpecifier' => 'foo/bar',
            'importMapType' => ImportMapType::CSS,
            'expectedPath' => self::$writableRoot.'/assets/vendor/foo/bar.css',
        ];
    }
}
