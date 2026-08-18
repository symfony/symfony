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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\AssetMapper\ImportMap\ImportMapConfigReader;
use Symfony\Component\AssetMapper\ImportMap\ImportMapEntries;
use Symfony\Component\AssetMapper\ImportMap\ImportMapEntry;
use Symfony\Component\AssetMapper\ImportMap\ImportMapType;
use Symfony\Component\AssetMapper\ImportMap\ImportMapUpdateChecker;
use Symfony\Component\AssetMapper\ImportMap\PackageUpdateInfo;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

class ImportMapUpdateCheckerTest extends TestCase
{
    private const SEVEN_DAYS_IN_SECONDS = 604800;
    private const NOW = '2026-01-20T00:00:00Z';

    private ImportMapConfigReader $importMapConfigReader;
    private ImportMapUpdateChecker $updateChecker;

    protected function setUp(): void
    {
        $this->importMapConfigReader = $this->createStub(ImportMapConfigReader::class);
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
                '@hotwired/stimulus',
                '3.2.1',
                '4.0.1',
                'major'
            ),
            'json5' => new PackageUpdateInfo(
                'json5',
                '1.0.0',
                '1.2.0',
                'minor'
            ),
            'bootstrap' => new PackageUpdateInfo(
                'bootstrap',
                '5.3.1',
                '5.3.2',
                'patch'
            ),
            'bootstrap/dist/css/bootstrap.min.css' => new PackageUpdateInfo(
                'bootstrap',
                '5.3.1',
                '5.3.2',
                'patch'
            ),
            'lodash' => new PackageUpdateInfo(
                'lodash',
                '4.17.21',
                '4.17.21',
                'up-to-date'
            ),
        ], $updates);
    }

    public function testGetAvailableUpdatesHonorsMinimumReleaseAge()
    {
        $this->importMapConfigReader->method('getEntries')->willReturn(new ImportMapEntries([
            'bootstrap' => self::createRemoteEntry(
                importName: 'bootstrap',
                version: '5.3.1',
                packageSpecifier: 'bootstrap',
            ),
        ]));

        // Boundary is NOW - 7 days = 2026-01-13: 5.3.3 (1 day old) is too recent, 5.3.2 (10 days old) wins
        // and 5.3.3 is reported as withheld.
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'dist-tags' => ['latest' => '5.3.3'],
                'versions' => ['5.3.1' => [], '5.3.2' => [], '5.3.3' => []],
                'time' => [
                    'created' => '2024-01-01T00:00:00.000Z',
                    'modified' => self::NOW,
                    '5.3.1' => '2025-12-21T00:00:00Z',
                    '5.3.2' => '2026-01-10T00:00:00Z',
                    '5.3.3' => '2026-01-19T00:00:00Z',
                ],
            ]),
        ]);
        $updateChecker = new ImportMapUpdateChecker($this->importMapConfigReader, $httpClient, new MockClock(self::NOW), self::SEVEN_DAYS_IN_SECONDS);

        $this->assertEquals([
            'bootstrap' => new PackageUpdateInfo(
                'bootstrap',
                '5.3.1',
                '5.3.2',
                'patch',
                '5.3.3'
            ),
        ], $updateChecker->getAvailableUpdates());
    }

    public function testGetAvailableUpdatesSkipsPrereleasesAndUnpublishedVersions()
    {
        $this->importMapConfigReader->method('getEntries')->willReturn(new ImportMapEntries([
            'bootstrap' => self::createRemoteEntry(
                importName: 'bootstrap',
                version: '5.3.1',
                packageSpecifier: 'bootstrap',
            ),
        ]));

        // Newest eligible-by-age entries must all be rejected: 5.4.0 is inside the cooldown,
        // 5.4.0-rc.1 is a prerelease, 5.3.9-canary.1 is a prerelease that sorts BELOW latest
        // (which `parseStability()` alone would accept), 6.0.0-canary.1 sorts above the
        // declared latest, and 9.9.9 is unpublished. Only the stable 5.3.2 survives.
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'dist-tags' => ['latest' => '5.4.0'],
                'versions' => ['5.3.1' => [], '5.3.2' => [], '5.3.9-canary.1' => [], '5.4.0' => [], '5.4.0-rc.1' => [], '6.0.0-canary.1' => []],
                'time' => [
                    'created' => '2024-01-01T00:00:00.000Z',
                    'modified' => self::NOW,
                    '5.3.1' => '2025-12-20T00:00:00Z',
                    '5.3.2' => '2025-12-25T00:00:00Z',
                    '5.3.9-canary.1' => '2026-01-01T00:00:00Z',
                    '5.4.0-rc.1' => '2026-01-02T00:00:00Z',
                    '6.0.0-canary.1' => '2026-01-03T00:00:00Z',
                    '9.9.9' => '2026-01-04T00:00:00Z', // unpublished: present in time, absent from versions
                    '5.4.0' => '2026-01-19T00:00:00Z', // stable but inside the cooldown window
                ],
            ]),
        ]);
        $updateChecker = new ImportMapUpdateChecker($this->importMapConfigReader, $httpClient, new MockClock(self::NOW), self::SEVEN_DAYS_IN_SECONDS);

        $this->assertEquals([
            'bootstrap' => new PackageUpdateInfo(
                'bootstrap',
                '5.3.1',
                '5.3.2',
                'patch',
                '5.4.0'
            ),
        ], $updateChecker->getAvailableUpdates());
    }

    public function testGetAvailableUpdatesReportsPackageWithNoEligibleVersionAsUpToDateAndWarns()
    {
        $this->importMapConfigReader->method('getEntries')->willReturn(new ImportMapEntries([
            'bootstrap' => self::createRemoteEntry(
                importName: 'bootstrap',
                version: '5.3.1',
                packageSpecifier: 'bootstrap',
            ),
        ]));

        // Every published stable version is inside the cooldown window: nothing is eligible.
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'dist-tags' => ['latest' => '5.3.2'],
                'versions' => ['5.3.1' => [], '5.3.2' => []],
                'time' => [
                    'created' => '2024-01-01T00:00:00.000Z',
                    'modified' => self::NOW,
                    '5.3.1' => '2026-01-18T00:00:00Z',
                    '5.3.2' => '2026-01-19T00:00:00Z',
                ],
            ]),
        ]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('minimum release age'), $this->arrayHasKey('package'));

        $updateChecker = new ImportMapUpdateChecker($this->importMapConfigReader, $httpClient, new MockClock(self::NOW), self::SEVEN_DAYS_IN_SECONDS, $logger);

        $this->assertEquals([
            'bootstrap' => new PackageUpdateInfo(
                'bootstrap',
                '5.3.1',
                '5.3.1',
                'up-to-date',
                '5.3.2'
            ),
        ], $updateChecker->getAvailableUpdates());
    }

    public function testGetAvailableUpdatesSkipsPackageWhenLatestTagIsMissing()
    {
        $this->importMapConfigReader->method('getEntries')->willReturn(new ImportMapEntries([
            'bootstrap' => self::createRemoteEntry(
                importName: 'bootstrap',
                version: '5.3.1',
                packageSpecifier: 'bootstrap',
            ),
        ]));

        // Without a declared `dist-tags.latest` there is no safe upper bound: skip the package.
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'versions' => ['5.3.1' => [], '5.3.2' => []],
                'time' => [
                    'created' => '2024-01-01T00:00:00.000Z',
                    'modified' => self::NOW,
                    '5.3.1' => '2025-12-20T00:00:00Z',
                    '5.3.2' => '2025-12-25T00:00:00Z',
                ],
            ]),
        ]);
        $updateChecker = new ImportMapUpdateChecker($this->importMapConfigReader, $httpClient, new MockClock(self::NOW), self::SEVEN_DAYS_IN_SECONDS);

        $this->assertSame([], $updateChecker->getAvailableUpdates());
    }

    /**
     * @param ImportMapEntry[]    $entries
     * @param PackageUpdateInfo[] $expectedUpdateInfo
     */
    #[DataProvider('provideImportMapEntry')]
    public function testGetAvailableUpdatesForSinglePackage(array $entries, array $expectedUpdateInfo, ?\Exception $expectedException)
    {
        $this->importMapConfigReader->method('getEntries')->willReturn(new ImportMapEntries($entries));
        if (null !== $expectedException) {
            $this->expectException($expectedException::class);
            $this->updateChecker->getAvailableUpdates(array_map(static fn ($entry) => $entry->importName, $entries));
        } else {
            $update = $this->updateChecker->getAvailableUpdates(array_map(static fn ($entry) => $entry->importName, $entries));
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
                '@hotwired/stimulus',
                '3.2.1',
                '4.0.1',
                'major'
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
                'bootstrap',
                '5.3.1',
                '5.3.2',
                'patch'
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
        $packageSpecifier ??= $importName;

        return ImportMapEntry::createRemote($importName, $type, '/vendor/any-path.js', $version, $packageSpecifier, false);
    }
}
