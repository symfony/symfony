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
    private static string $writableRoot;
    private static int $writableRootIndex = 0;

    protected function setUp(): void
    {
        self::$writableRoot = sys_get_temp_dir().'/remote_package_storage'.++self::$writableRootIndex;
        $this->filesystem = new Filesystem();
        $this->filesystem->mkdir(self::$writableRoot);
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

    public function testSaveThrowsWhenFailing()
    {
        $vendorDir = self::$writableRoot.'/assets/acme/vendor';
        $this->filesystem->mkdir($vendorDir.'/module_specifier');
        $this->filesystem->touch($vendorDir.'/module_specifier/module_specifier.index.js');
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->filesystem->chmod($vendorDir.'/module_specifier/module_specifier.index.js', 0555);
        } else {
            $this->filesystem->chmod($vendorDir.'/module_specifier/', 0555);
        }

        $storage = new RemotePackageStorage($vendorDir);
        $entry = ImportMapEntry::createRemote('foo', ImportMapType::JS, '/does/not/matter', '1.0.0', 'module_specifier', false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to write file "'.$vendorDir.'/module_specifier/module_specifier.index.js".');

        try {
            $storage->save($entry, 'any content');
        } finally {
            if ('\\' === \DIRECTORY_SEPARATOR) {
                foreach (glob($vendorDir.'/module_specifier/*') as $file) {
                    $this->filesystem->chmod($file, 0777);
                }
            } else {
                $this->filesystem->chmod($vendorDir.'/module_specifier/', 0777);
            }
        }
    }

    public function testIsDownloaded()
    {
        $storage = new RemotePackageStorage(self::$writableRoot.'/assets/vendor');
        $entry = ImportMapEntry::createRemote('foo', ImportMapType::JS, '/does/not/matter', '1.0.0', 'module_specifier', false);
        $this->assertFalse($storage->isDownloaded($entry));

        $targetPath = self::$writableRoot.'/assets/vendor/module_specifier/module_specifier.index.js';
        $this->filesystem->mkdir(\dirname($targetPath));
        $this->filesystem->dumpFile($targetPath, 'any content');
        $this->assertTrue($storage->isDownloaded($entry));
    }

    public function testIsExtraFileDownloaded()
    {
        $storage = new RemotePackageStorage(self::$writableRoot.'/assets/vendor');
        $entry = ImportMapEntry::createRemote('foo', ImportMapType::JS, '/does/not/matter', '1.0.0', 'module_specifier', false);
        $this->assertFalse($storage->isExtraFileDownloaded($entry, '/path/to/extra.woff'));

        $targetPath = self::$writableRoot.'/assets/vendor/module_specifier/path/to/extra.woff';
        $this->filesystem->mkdir(\dirname($targetPath));
        $this->filesystem->dumpFile($targetPath, 'any content');
        $this->assertTrue($storage->isExtraFileDownloaded($entry, '/path/to/extra.woff'));
    }

    public function testSave()
    {
        $storage = new RemotePackageStorage(self::$writableRoot.'/assets/vendor');
        $entry = ImportMapEntry::createRemote('foo', ImportMapType::JS, '/does/not/matter', '1.0.0', 'module_specifier', false);
        $storage->save($entry, 'any content');
        $targetPath = self::$writableRoot.'/assets/vendor/module_specifier/module_specifier.index.js';
        $this->assertFileExists($targetPath);
        $this->assertEquals('any content', $this->filesystem->readFile($targetPath));
    }

    public function testSaveExtraFile()
    {
        $storage = new RemotePackageStorage(self::$writableRoot.'/assets/vendor');
        $entry = ImportMapEntry::createRemote('foo', ImportMapType::JS, '/does/not/matter', '1.0.0', 'module_specifier', false);
        $storage->saveExtraFile($entry, '/path/to/extra-file.woff2', 'any content');
        $targetPath = self::$writableRoot.'/assets/vendor/module_specifier/path/to/extra-file.woff2';
        $this->assertFileExists($targetPath);
        $this->assertEquals('any content', $this->filesystem->readFile($targetPath));
    }

    /**
     * @dataProvider getDownloadPathTests
     */
    public function testGetDownloadedPath(string $packageModuleSpecifier, ImportMapType $importMapType, string $expectedPath)
    {
        $storage = new RemotePackageStorage(self::$writableRoot.'/assets/vendor');
        $this->assertSame(self::$writableRoot.$expectedPath, $storage->getDownloadPath($packageModuleSpecifier, $importMapType));
    }

    public static function getDownloadPathTests(): iterable
    {
        yield 'javascript bare package' => [
            'packageModuleSpecifier' => 'foo',
            'importMapType' => ImportMapType::JS,
            'expectedPath' => '/assets/vendor/foo/foo.index.js',
        ];

        yield 'javascript package with path' => [
            'packageModuleSpecifier' => 'foo/bar',
            'importMapType' => ImportMapType::JS,
            'expectedPath' => '/assets/vendor/foo/bar.js',
        ];

        yield 'javascript package with path and extension' => [
            'packageModuleSpecifier' => 'foo/bar.js',
            'importMapType' => ImportMapType::JS,
            'expectedPath' => '/assets/vendor/foo/bar.js',
        ];

        yield 'CSS package with path' => [
            'packageModuleSpecifier' => 'foo/bar',
            'importMapType' => ImportMapType::CSS,
            'expectedPath' => '/assets/vendor/foo/bar.css',
        ];
    }
}
