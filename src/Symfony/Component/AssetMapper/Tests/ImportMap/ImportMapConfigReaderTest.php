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
use Symfony\Component\Filesystem\Filesystem;

class ImportMapConfigReaderTest extends TestCase
{
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        if (!file_exists(__DIR__.'/../fixtures/importmaps_for_writing')) {
            $this->filesystem->mkdir(__DIR__.'/../fixtures/importmaps_for_writing');
        }
        if (!file_exists(__DIR__.'/../fixtures/importmaps_for_writing/assets')) {
            $this->filesystem->mkdir(__DIR__.'/../fixtures/importmaps_for_writing/assets');
        }
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove(__DIR__.'/../fixtures/importmaps_for_writing');
    }

    public function testGetEntriesAndWriteEntries()
    {
        $importMap = <<<EOF
<?php
return [
    'remote_package' => [
        'url' => 'https://unpkg.com/@hotwired/stimulus@3.2.1/dist/stimulus.js',
    ],
    'remote_package_downloaded' => [
        'downloaded_to' => 'vendor/lodash.js',
        'url' => 'https://ga.jspm.io/npm:lodash@4.17.21/lodash.js',
    ],
    'local_package' => [
        'path' => 'app.js',
    ],
    'type_css' => [
        'path' => 'styles/app.css',
        'type' => 'css',
    ],
    'entry_point' => [
        'path' => 'entry.js',
        'entrypoint' => true,
    ],
];
EOF;
        file_put_contents(__DIR__.'/../fixtures/importmaps_for_writing/importmap.php', $importMap);

        $reader = new ImportMapConfigReader(__DIR__.'/../fixtures/importmaps_for_writing/importmap.php');
        $entries = $reader->getEntries();
        $this->assertInstanceOf(ImportMapEntries::class, $entries);
        /** @var ImportMapEntry[] $allEntries */
        $allEntries = iterator_to_array($entries);
        $this->assertCount(5, $allEntries);

        $remotePackageEntry = $allEntries[0];
        $this->assertSame('remote_package', $remotePackageEntry->importName);
        $this->assertNull($remotePackageEntry->path);
        $this->assertSame('https://unpkg.com/@hotwired/stimulus@3.2.1/dist/stimulus.js', $remotePackageEntry->url);
        $this->assertFalse($remotePackageEntry->isDownloaded);
        $this->assertSame('js', $remotePackageEntry->type->value);
        $this->assertFalse($remotePackageEntry->isEntrypoint);

        $remotePackageDownloadedEntry = $allEntries[1];
        $this->assertSame('https://ga.jspm.io/npm:lodash@4.17.21/lodash.js', $remotePackageDownloadedEntry->url);
        $this->assertSame('vendor/lodash.js', $remotePackageDownloadedEntry->path);

        $localPackageEntry = $allEntries[2];
        $this->assertNull($localPackageEntry->url);
        $this->assertSame('app.js', $localPackageEntry->path);

        $typeCssEntry = $allEntries[3];
        $this->assertSame('css', $typeCssEntry->type->value);

        $entryPointEntry = $allEntries[4];
        $this->assertTrue($entryPointEntry->isEntrypoint);

        // now save the original raw data from importmap.php and delete the file
        $originalImportMapData = (static fn () => include __DIR__.'/../fixtures/importmaps_for_writing/importmap.php')();
        unlink(__DIR__.'/../fixtures/importmaps_for_writing/importmap.php');
        // dump the entries back to the file
        $reader->writeEntries($entries);
        $newImportMapData = (static fn () => include __DIR__.'/../fixtures/importmaps_for_writing/importmap.php')();

        $this->assertSame($originalImportMapData, $newImportMapData);
    }

    public function testGetRootDirectory()
    {
        $configReader = new ImportMapConfigReader(__DIR__.'/../fixtures/importmap.php');
        $this->assertSame(__DIR__.'/../fixtures', $configReader->getRootDirectory());
    }
}
