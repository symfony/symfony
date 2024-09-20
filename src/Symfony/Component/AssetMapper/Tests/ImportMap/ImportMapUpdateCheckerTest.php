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
use Symfony\Component\HttpClient\Response\JsonMockResponse;
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
            '@hotwired/stimulus' => self::createRemoteEntry(
                importName: '@hotwired/stimulus',
                version: '3.2.1',
                packageSpecifier: '@hotwired/stimulus',
            ),
            'json5' => self::createRemoteEntry(
                importName: 'json5',
                version: '1.0.0',
                packageSpecifier: 'json5',
            ),
            'bootstrap' => self::createRemoteEntry(
                importName: 'bootstrap',
                version: '5.3.1',
                packageSpecifier: 'bootstrap',
            ),
            'bootstrap/dist/css/bootstrap.min.css' => self::createRemoteEntry(
                importName: 'bootstrap/dist/css/bootstrap.min.css',
                version: '5.3.1',
                type: ImportMapType::CSS,
                packageSpecifier: 'bootstrap',
            ),
            'lodash' => self::createRemoteEntry(
                importName: 'lodash',
                version: '4.17.21',
                packageSpecifier: 'lodash',
            ),
            // Local package won't appear in update list
            'app' => ImportMapEntry::createLocal(
                'app',
                ImportMapType::JS,
                'assets/app.js',
                false,
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
            $this->updateChecker->getAvailableUpdates(array_map(fn ($entry) => $entry->importName, $entries));
        } else {
            $update = $this->updateChecker->getAvailableUpdates(array_map(fn ($entry) => $entry->importName, $entries));
            $this->assertEquals($expectedUpdateInfo, $update);
        }
    }

    public static function provideImportMapEntry(): iterable
    {
        yield [
            [self::createRemoteEntry(
                importName: '@hotwired/stimulus',
                version: '3.2.1',
                packageSpecifier: '@hotwired/stimulus',
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
                self::createRemoteEntry(
                    importName: 'bootstrap/dist/css/bootstrap.min.css',
                    version: '5.3.1',
                    packageSpecifier: 'bootstrap',
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
                self::createRemoteEntry(
                    importName: 'bootstrap',
                    version: 'not_a_version',
                    packageSpecifier: 'bootstrap',
                ),
            ],
            [],
            new \RuntimeException('Unable to get latest available version for package "bootstrap".'),
        ];
        yield [
            [
                self::createRemoteEntry(
                    importName: 'invalid_package_name',
                    version: '1.0.0',
                    packageSpecifier: 'invalid_package_name',
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
            'https://registry.npmjs.org/@hotwired/stimulus' => new JsonMockResponse([
                'dist-tags' => ['latest' => '4.0.1'], // Major update
            ]),
            'https://registry.npmjs.org/json5' => new JsonMockResponse([
                'dist-tags' => ['latest' => '1.2.0'], // Minor update
            ]),
            'https://registry.npmjs.org/bootstrap' => new JsonMockResponse([
                'dist-tags' => ['latest' => '5.3.2'], // Patch update
            ]),
            'https://registry.npmjs.org/lodash' => new JsonMockResponse([
                'dist-tags' => ['latest' => '4.17.21'], // no update
            ]),
        ];

        return $map[$url] ?? new MockResponse('Not found', ['http_code' => 404]);
    }

    private static function createRemoteEntry(string $importName, string $version, ImportMapType $type = ImportMapType::JS, ?string $packageSpecifier = null): ImportMapEntry
    {
        $packageSpecifier = $packageSpecifier ?? $importName;

        return ImportMapEntry::createRemote($importName, $type, path: '/vendor/any-path.js', version: $version, packageModuleSpecifier: $packageSpecifier, isEntrypoint: false);
    }
}
