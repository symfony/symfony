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
use Symfony\Component\AssetMapper\ImportMap\ImportMapUpdateChecker;
use Symfony\Component\AssetMapper\ImportMap\PackageUpdateInfo;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class ImportMapUpdateCheckerTest extends TestCase
{
    private ImportMapConfigReader $importMapConfigReader;
    private ImportMapUpdateChecker $updateChecker;

    protected function setUp(): void
    {
        $this->importMapConfigReader = $this->createMock(ImportMapConfigReader::class);
        $httpClient = new MockHttpClient();
        $httpClient->setResponseFactory(self::responseFactory(...));
        $this->updateChecker = new ImportMapUpdateChecker($this->importMapConfigReader, $httpClient);
    }

    public function testGetAvailableUpdates()
    {
        $this->importMapConfigReader->method('getEntries')->willReturn(new ImportMapEntries([
            '@hotwired/stimulus' => new ImportMapEntry(
                importName: '@hotwired/stimulus',
                version: '3.2.1',
                packageName: '@hotwired/stimulus',
            ),
            'json5' => new ImportMapEntry(
                importName: 'json5',
                version: '1.0.0',
                packageName: 'json5',
            ),
            'bootstrap' => new ImportMapEntry(
                importName: 'bootstrap',
                version: '5.3.1',
                packageName: 'bootstrap',
            ),
            'bootstrap/dist/css/bootstrap.min.css' => new ImportMapEntry(
                importName: 'bootstrap/dist/css/bootstrap.min.css',
                version: '5.3.1',
                type: ImportMapType::CSS,
                packageName: 'bootstrap',
            ),
            'lodash' => new ImportMapEntry(
                importName: 'lodash',
                version: '4.17.21',
                packageName: 'lodash',
            ),
            // Local package won't appear in update list
            'app' => new ImportMapEntry(
                importName: 'app',
                path: 'assets/app.js',
            ),
        ]));

        $updates = $this->updateChecker->getAvailableUpdates();

        $this->assertEquals([
            '@hotwired/stimulus' => new PackageUpdateInfo(
                packageName: '@hotwired/stimulus',
                currentVersion: '3.2.1',
                latestVersion: '4.0.1',
                updateType: 'major'
            ),
            'json5' => new PackageUpdateInfo(
                packageName: 'json5',
                currentVersion: '1.0.0',
                latestVersion: '1.2.0',
                updateType: 'minor'
            ),
            'bootstrap' => new PackageUpdateInfo(
                packageName: 'bootstrap',
                currentVersion: '5.3.1',
                latestVersion: '5.3.2',
                updateType: 'patch'
            ),
            'bootstrap/dist/css/bootstrap.min.css' => new PackageUpdateInfo(
                packageName: 'bootstrap',
                currentVersion: '5.3.1',
                latestVersion: '5.3.2',
                updateType: 'patch'
            ),
            'lodash' => new PackageUpdateInfo(
                packageName: 'lodash',
                currentVersion: '4.17.21',
                latestVersion: '4.17.21',
                updateType: 'up-to-date'
            ),
        ], $updates);
    }

    /**
     * @dataProvider provideImportMapEntry
     *
     * @param ImportMapEntry[]    $entries
     * @param PackageUpdateInfo[] $expectedUpdateInfo
     */
    public function testGetAvailableUpdatesForSinglePackage(array $entries, array $expectedUpdateInfo, ?\Exception $expectedException)
    {
        $this->importMapConfigReader->method('getEntries')->willReturn(new ImportMapEntries($entries));
        if (null !== $expectedException) {
            $this->expectException($expectedException::class);
            $this->updateChecker->getAvailableUpdates(array_map(fn ($entry) => $entry->packageName, $entries));
        } else {
            $update = $this->updateChecker->getAvailableUpdates(array_map(fn ($entry) => $entry->packageName, $entries));
            $this->assertEquals($expectedUpdateInfo, $update);
        }
    }

    private function provideImportMapEntry()
    {
        yield [
            ['@hotwired/stimulus' => new ImportMapEntry(
                importName: '@hotwired/stimulus',
                version: '3.2.1',
                packageName: '@hotwired/stimulus',
            ),
            ],
            ['@hotwired/stimulus' => new PackageUpdateInfo(
                packageName: '@hotwired/stimulus',
                currentVersion: '3.2.1',
                latestVersion: '4.0.1',
                updateType: 'major'
            ), ],
            null,
        ];
        yield [
            [
                'bootstrap/dist/css/bootstrap.min.css' => new ImportMapEntry(
                    importName: 'bootstrap/dist/css/bootstrap.min.css',
                    version: '5.3.1',
                    packageName: 'bootstrap',
                ),
            ],
            ['bootstrap/dist/css/bootstrap.min.css' => new PackageUpdateInfo(
                packageName: 'bootstrap',
                currentVersion: '5.3.1',
                latestVersion: '5.3.2',
                updateType: 'patch'
            ), ],
            null,
        ];
        yield [
            [
                'bootstrap' => new ImportMapEntry(
                    importName: 'bootstrap',
                    version: 'not_a_version',
                    packageName: 'bootstrap',
                ),
            ],
            [],
            new \RuntimeException('Unable to get latest available version for package "bootstrap".'),
        ];
        yield [
            [
                new ImportMapEntry(
                    importName: 'invalid_package_name',
                    version: '1.0.0',
                    packageName: 'invalid_package_name',
                ),
            ],
            [],
            new \RuntimeException('Unable to get latest available version for package "invalid_package_name".'),
        ];
    }

    private function responseFactory($method, $url): MockResponse
    {
        $this->assertSame('GET', $method);
        $map = [
            'https://registry.npmjs.org/@hotwired/stimulus' => new MockResponse(json_encode([
                'dist-tags' => ['latest' => '4.0.1'], // Major update
            ])),
            'https://registry.npmjs.org/json5' => new MockResponse(json_encode([
                'dist-tags' => ['latest' => '1.2.0'], // Minor update
            ])),
            'https://registry.npmjs.org/bootstrap' => new MockResponse(json_encode([
                'dist-tags' => ['latest' => '5.3.2'], // Patch update
            ])),
            'https://registry.npmjs.org/lodash' => new MockResponse(json_encode([
                'dist-tags' => ['latest' => '4.17.21'], // no update
            ])),
        ];

        return $map[$url] ?? new MockResponse('Not found', ['http_code' => 404]);
    }
}
