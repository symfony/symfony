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
use Symfony\Component\AssetMapper\ImportMap\ImportMapConfigReader;
use Symfony\Component\AssetMapper\ImportMap\ImportMapEntries;
use Symfony\Component\AssetMapper\ImportMap\ImportMapEntry;
use Symfony\Component\AssetMapper\ImportMap\ImportMapType;
use Symfony\Component\AssetMapper\ImportMap\RemotePackageDownloader;
use Symfony\Component\AssetMapper\ImportMap\RemotePackageStorage;
use Symfony\Component\AssetMapper\ImportMap\Resolver\PackageResolverInterface;
use Symfony\Component\Filesystem\Filesystem;

class RemotePackageDownloaderTest extends TestCase
{
    private Filesystem $filesystem;
    private static string $writableRoot = __DIR__.'/../Fixtures/remote_package_downloader';

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->filesystem->mkdir(self::$writableRoot);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove(self::$writableRoot);
    }

    public function testDownloadPackagesDownloadsEverythingWithNoInstalled()
    {
        $configReader = $this->createMock(ImportMapConfigReader::class);
        $packageResolver = $this->createMock(PackageResolverInterface::class);
        $remotePackageStorage = new RemotePackageStorage(self::$writableRoot.'/assets/vendor');

        $entry1 = ImportMapEntry::createRemote('foo', ImportMapType::JS, path: '/any', version: '1.0.0', packageModuleSpecifier: 'foo', isEntrypoint: false);
        $entry2 = ImportMapEntry::createRemote('bar.js/file', ImportMapType::JS, path: '/any', version: '1.0.0', packageModuleSpecifier: 'bar.js/file', isEntrypoint: false);
        $entry3 = ImportMapEntry::createRemote('baz', ImportMapType::CSS, path: '/any', version: '1.0.0', packageModuleSpecifier: 'baz', isEntrypoint: false);
        $entry4 = ImportMapEntry::createRemote('different_specifier', ImportMapType::JS, path: '/any', version: '1.0.0', packageModuleSpecifier: 'custom_specifier', isEntrypoint: false);
        $importMapEntries = new ImportMapEntries([$entry1, $entry2, $entry3, $entry4]);

        $configReader->expects($this->once())
            ->method('getEntries')
            ->willReturn($importMapEntries);

        $progressCallback = fn () => null;
        $packageResolver->expects($this->once())
            ->method('downloadPackages')
            ->with(
                ['foo' => $entry1, 'bar.js/file' => $entry2, 'baz' => $entry3, 'different_specifier' => $entry4],
                $progressCallback
            )
            ->willReturn([
                'foo' => ['content' => 'foo content', 'dependencies' => [], 'extraFiles' => ['/path/to/extra-file.woff' => 'extra file contents']],
                'bar.js/file' => ['content' => 'bar content', 'dependencies' => [], 'extraFiles' => []],
                'baz' => ['content' => 'baz content', 'dependencies' => ['foo'], 'extraFiles' => []],
                'different_specifier' => ['content' => 'different content', 'dependencies' => [], 'extraFiles' => []],
            ]);

        $downloader = new RemotePackageDownloader(
            $remotePackageStorage,
            $configReader,
            $packageResolver,
        );
        $downloader->downloadPackages($progressCallback);

        $this->assertFileExists(self::$writableRoot.'/assets/vendor/foo/foo.index.js');
        $this->assertFileExists(self::$writableRoot.'/assets/vendor/bar.js/file.js');
        $this->assertFileExists(self::$writableRoot.'/assets/vendor/baz/baz.index.css');
        $this->assertEquals('foo content', $this->filesystem->readFile(self::$writableRoot.'/assets/vendor/foo/foo.index.js'));
        $this->assertFileExists(self::$writableRoot.'/assets/vendor/foo/path/to/extra-file.woff');
        $this->assertEquals('extra file contents', $this->filesystem->readFile(self::$writableRoot.'/assets/vendor/foo/path/to/extra-file.woff'));
        $this->assertEquals('bar content', $this->filesystem->readFile(self::$writableRoot.'/assets/vendor/bar.js/file.js'));
        $this->assertEquals('baz content', $this->filesystem->readFile(self::$writableRoot.'/assets/vendor/baz/baz.index.css'));
        $this->assertEquals('different content', $this->filesystem->readFile(self::$writableRoot.'/assets/vendor/custom_specifier/custom_specifier.index.js'));

        $installed = require self::$writableRoot.'/assets/vendor/installed.php';
        $this->assertEquals(
            [
                'foo' => ['version' => '1.0.0', 'dependencies' => [], 'extraFiles' => ['/path/to/extra-file.woff']],
                'bar.js/file' => ['version' => '1.0.0', 'dependencies' => [], 'extraFiles' => []],
                'baz' => ['version' => '1.0.0', 'dependencies' => ['foo'], 'extraFiles' => []],
                'different_specifier' => ['version' => '1.0.0', 'dependencies' => [], 'extraFiles' => []],
            ],
            $installed
        );
    }

    public function testPackagesWithCorrectInstalledVersionSkipped()
    {
        $this->filesystem->mkdir(self::$writableRoot.'/assets/vendor');
        $installed = [
            'foo' => ['version' => '1.0.0', 'dependencies' => [], 'extraFiles' => []],
            'bar.js/file' => ['version' => '1.0.0', 'dependencies' => [], 'extraFiles' => []],
            'baz' => ['version' => '1.0.0', 'dependencies' => [], 'extraFiles' => []],
        ];
        $this->filesystem->dumpFile(
            self::$writableRoot.'/assets/vendor/installed.php',
            '<?php return '.var_export($installed, true).';',
        );

        $configReader = $this->createMock(ImportMapConfigReader::class);
        $packageResolver = $this->createMock(PackageResolverInterface::class);

        // matches installed version and file exists
        $entry1 = ImportMapEntry::createRemote('foo', ImportMapType::JS, path: '/any', version: '1.0.0', packageModuleSpecifier: 'foo', isEntrypoint: false);
        $this->filesystem->dumpFile(self::$writableRoot.'/assets/vendor/foo/foo.index.js', 'original foo content');
        // matches installed version but file does not exist
        $entry2 = ImportMapEntry::createRemote('bar.js/file', ImportMapType::JS, path: '/any', version: '1.0.0', packageModuleSpecifier: 'bar.js/file', isEntrypoint: false);
        // does not match installed version
        $entry3 = ImportMapEntry::createRemote('baz', ImportMapType::CSS, path: '/any', version: '1.1.0', packageModuleSpecifier: 'baz', isEntrypoint: false);
        $this->filesystem->dumpFile(self::$writableRoot.'/assets/vendor/baz/baz.index.css', 'original baz content');
        // matches installed & file exists, but has missing extra file
        $entry4 = ImportMapEntry::createRemote('has-missing-extra', ImportMapType::JS, path: '/any', version: '1.0.0', packageModuleSpecifier: 'has-missing-extra', isEntrypoint: false);
        $importMapEntries = new ImportMapEntries([$entry1, $entry2, $entry3, $entry4]);

        $configReader->expects($this->once())
            ->method('getEntries')
            ->willReturn($importMapEntries);

        $packageResolver->expects($this->once())
            ->method('downloadPackages')
            ->willReturn([
                'bar.js/file' => ['content' => 'new bar content', 'dependencies' => [], 'extraFiles' => []],
                'baz' => ['content' => 'new baz content', 'dependencies' => [], 'extraFiles' => []],
                'has-missing-extra' => ['content' => 'new content', 'dependencies' => [], 'extraFiles' => ['/path/to/extra-file.woff' => 'extra file contents']],
            ]);

        $downloader = new RemotePackageDownloader(
            new RemotePackageStorage(self::$writableRoot.'/assets/vendor'),
            $configReader,
            $packageResolver,
        );
        $downloader->downloadPackages();

        $this->assertFileExists(self::$writableRoot.'/assets/vendor/foo/foo.index.js');
        $this->assertFileExists(self::$writableRoot.'/assets/vendor/bar.js/file.js');
        $this->assertFileExists(self::$writableRoot.'/assets/vendor/baz/baz.index.css');
        $this->assertEquals('original foo content', $this->filesystem->readFile(self::$writableRoot.'/assets/vendor/foo/foo.index.js'));
        $this->assertEquals('new bar content', $this->filesystem->readFile(self::$writableRoot.'/assets/vendor/bar.js/file.js'));
        $this->assertEquals('new baz content', $this->filesystem->readFile(self::$writableRoot.'/assets/vendor/baz/baz.index.css'));
        $this->assertFileExists(self::$writableRoot.'/assets/vendor/has-missing-extra/has-missing-extra.index.js');

        $installed = require self::$writableRoot.'/assets/vendor/installed.php';
        $this->assertEquals(
            [
                'foo' => ['version' => '1.0.0', 'dependencies' => [], 'extraFiles' => []],
                'bar.js/file' => ['version' => '1.0.0', 'dependencies' => [], 'extraFiles' => []],
                'baz' => ['version' => '1.1.0', 'dependencies' => [], 'extraFiles' => []],
                'has-missing-extra' => ['version' => '1.0.0', 'dependencies' => [], 'extraFiles' => ['/path/to/extra-file.woff']],
            ],
            $installed
        );
    }

    public function testGetVendorDir()
    {
        $remotePackageStorage = new RemotePackageStorage('/foo/assets/vendor');
        $downloader = new RemotePackageDownloader(
            $remotePackageStorage,
            $this->createMock(ImportMapConfigReader::class),
            $this->createMock(PackageResolverInterface::class),
        );
        $this->assertSame('/foo/assets/vendor', $downloader->getVendorDir());
    }
}
