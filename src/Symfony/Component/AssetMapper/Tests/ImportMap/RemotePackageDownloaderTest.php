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
use Symfony\Component\AssetMapper\ImportMap\Resolver\PackageResolverInterface;
use Symfony\Component\Filesystem\Filesystem;

class RemotePackageDownloaderTest extends TestCase
{
    private Filesystem $filesystem;
    private static string $writableRoot = __DIR__.'/../fixtures/importmaps_for_writing';

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

    public function testDownloadPackagesDownloadsEverythingWithNoInstalled()
    {
        $configReader = $this->createMock(ImportMapConfigReader::class);
        $packageResolver = $this->createMock(PackageResolverInterface::class);

        $entry1 = new ImportMapEntry('foo', version: '1.0.0');
        $entry2 = new ImportMapEntry('bar.js/file', version: '1.0.0');
        $entry3 = new ImportMapEntry('baz', version: '1.0.0', type: ImportMapType::CSS);
        $importMapEntries = new ImportMapEntries([$entry1, $entry2, $entry3]);

        $configReader->expects($this->once())
            ->method('getEntries')
            ->willReturn($importMapEntries);

        $progressCallback = fn () => null;
        $packageResolver->expects($this->once())
            ->method('downloadPackages')
            ->with(
                ['foo' => $entry1, 'bar.js/file' => $entry2, 'baz' => $entry3],
                $progressCallback
            )
            ->willReturn(['foo' => 'foo content', 'bar.js/file' => 'bar content', 'baz' => 'baz content']);

        $downloader = new RemotePackageDownloader(
            $configReader,
            $packageResolver,
            self::$writableRoot.'/assets/vendor',
        );
        $downloader->downloadPackages($progressCallback);

        $this->assertFileExists(self::$writableRoot.'/assets/vendor/foo.js');
        $this->assertFileExists(self::$writableRoot.'/assets/vendor/bar.js/file.js');
        $this->assertFileExists(self::$writableRoot.'/assets/vendor/baz.css');
        $this->assertEquals('foo content', file_get_contents(self::$writableRoot.'/assets/vendor/foo.js'));
        $this->assertEquals('bar content', file_get_contents(self::$writableRoot.'/assets/vendor/bar.js/file.js'));
        $this->assertEquals('baz content', file_get_contents(self::$writableRoot.'/assets/vendor/baz.css'));

        $installed = require self::$writableRoot.'/assets/vendor/installed.php';
        $this->assertEquals(
            [
                'foo' => ['path' => 'foo.js', 'version' => '1.0.0'],
                'bar.js/file' => ['path' => 'bar.js/file.js', 'version' => '1.0.0'],
                'baz' => ['path' => 'baz.css', 'version' => '1.0.0'],
            ],
            $installed
        );
    }

    public function testPackagesWithCorrectInstalledVersionSkipped()
    {
        $this->filesystem->mkdir(self::$writableRoot.'/assets/vendor');
        $installed = [
            'foo' => ['path' => 'foo.js', 'version' => '1.0.0'],
            'bar.js/file' => ['path' => 'bar.js/file.js', 'version' => '1.0.0'],
            'baz' => ['path' => 'baz.css', 'version' => '1.0.0'],
        ];
        file_put_contents(
            self::$writableRoot.'/assets/vendor/installed.php',
            '<?php return '.var_export($installed, true).';'
        );

        $configReader = $this->createMock(ImportMapConfigReader::class);
        $packageResolver = $this->createMock(PackageResolverInterface::class);

        // matches installed version and file exists
        $entry1 = new ImportMapEntry('foo', version: '1.0.0');
        file_put_contents(self::$writableRoot.'/assets/vendor/foo.js', 'original foo content');
        // matches installed version but file does not exist
        $entry2 = new ImportMapEntry('bar.js/file', version: '1.0.0');
        // does not match installed version
        $entry3 = new ImportMapEntry('baz', version: '1.1.0', type: ImportMapType::CSS);
        file_put_contents(self::$writableRoot.'/assets/vendor/baz.css', 'original baz content');
        $importMapEntries = new ImportMapEntries([$entry1, $entry2, $entry3]);

        $configReader->expects($this->once())
            ->method('getEntries')
            ->willReturn($importMapEntries);

        $packageResolver->expects($this->once())
            ->method('downloadPackages')
            ->willReturn(['bar.js/file' => 'new bar content', 'baz' => 'new baz content']);

        $downloader = new RemotePackageDownloader(
            $configReader,
            $packageResolver,
            self::$writableRoot.'/assets/vendor',
        );
        $downloader->downloadPackages();

        $this->assertFileExists(self::$writableRoot.'/assets/vendor/foo.js');
        $this->assertFileExists(self::$writableRoot.'/assets/vendor/bar.js/file.js');
        $this->assertFileExists(self::$writableRoot.'/assets/vendor/baz.css');
        $this->assertEquals('original foo content', file_get_contents(self::$writableRoot.'/assets/vendor/foo.js'));
        $this->assertEquals('new bar content', file_get_contents(self::$writableRoot.'/assets/vendor/bar.js/file.js'));
        $this->assertEquals('new baz content', file_get_contents(self::$writableRoot.'/assets/vendor/baz.css'));

        $installed = require self::$writableRoot.'/assets/vendor/installed.php';
        $this->assertEquals(
            [
                'foo' => ['path' => 'foo.js', 'version' => '1.0.0'],
                'bar.js/file' => ['path' => 'bar.js/file.js', 'version' => '1.0.0'],
                'baz' => ['path' => 'baz.css', 'version' => '1.1.0'],
            ],
            $installed
        );
    }

    public function testGetDownloadedPath()
    {
        $this->filesystem->mkdir(self::$writableRoot.'/assets/vendor');
        $installed = [
            'foo' => ['path' => 'foo-path.js', 'version' => '1.0.0'],
        ];
        file_put_contents(
            self::$writableRoot.'/assets/vendor/installed.php',
            '<?php return '.var_export($installed, true).';'
        );
        file_put_contents(self::$writableRoot.'/assets/vendor/foo-path.js', 'foo content');

        $downloader = new RemotePackageDownloader(
            $this->createMock(ImportMapConfigReader::class),
            $this->createMock(PackageResolverInterface::class),
            self::$writableRoot.'/assets/vendor',
        );
        $this->assertSame(realpath(self::$writableRoot.'/assets/vendor/foo-path.js'), realpath($downloader->getDownloadedPath('foo')));
    }

    public function testGetVendorDir()
    {
        $downloader = new RemotePackageDownloader(
            $this->createMock(ImportMapConfigReader::class),
            $this->createMock(PackageResolverInterface::class),
            self::$writableRoot.'/assets/vendor',
        );
        $this->assertSame(realpath(self::$writableRoot.'/assets/vendor'), realpath($downloader->getVendorDir()));
    }
}
