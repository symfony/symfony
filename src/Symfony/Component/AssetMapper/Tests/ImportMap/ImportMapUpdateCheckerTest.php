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
        yield 'pre-release to pre-release' => [
            [self::createRemoteEntry('next-beta', '2.0.0-beta.3')],
            ['next-beta' => new PackageUpdateInfo('next-beta', '2.0.0-beta.3', '2.0.0-beta.4', 'patch')],
            null,
        ];
        yield 'pre-release without a dot to pre-release' => [
            [self::createRemoteEntry('next-beta-nodot', '5.0.0-beta1')],
            ['next-beta-nodot' => new PackageUpdateInfo('next-beta-nodot', '5.0.0-beta1', '5.0.0-beta2', 'patch')],
            null,
        ];
        yield 'pre-release to release' => [
            [self::createRemoteEntry('next-release', '2.0.0-beta.3')],
            ['next-release' => new PackageUpdateInfo('next-release', '2.0.0-beta.3', '2.0.0', 'patch')],
            null,
        ];
        yield 'pre-release to a later patch' => [
            [self::createRemoteEntry('next-patch', '1.2.3-beta.1')],
            ['next-patch' => new PackageUpdateInfo('next-patch', '1.2.3-beta.1', '1.2.10', 'patch')],
            null,
        ];
        yield 'pre-release named "preview" to release' => [
            [self::createRemoteEntry('next-preview', '11.0.0-preview.7.26381.103')],
            ['next-preview' => new PackageUpdateInfo('next-preview', '11.0.0-preview.7.26381.103', '11.0.0', 'patch')],
            null,
        ];
        yield 'numeric pre-release to release' => [
            [self::createRemoteEntry('next-numeric', '1.2.3-1')],
            ['next-numeric' => new PackageUpdateInfo('next-numeric', '1.2.3-1', '1.2.3', 'patch')],
            null,
        ];
        yield '0.x minor update' => [
            [self::createRemoteEntry('zero-minor', '0.186.0')],
            ['zero-minor' => new PackageUpdateInfo('zero-minor', '0.186.0', '0.187.0', 'major')],
            null,
        ];
        yield '0.x patch update' => [
            [self::createRemoteEntry('zero-patch', '0.186.0')],
            ['zero-patch' => new PackageUpdateInfo('zero-patch', '0.186.0', '0.186.1', 'patch')],
            null,
        ];
        yield '0.0.x patch update' => [
            [self::createRemoteEntry('zero-zero-patch', '0.0.3')],
            ['zero-zero-patch' => new PackageUpdateInfo('zero-zero-patch', '0.0.3', '0.0.4', 'major')],
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
            'https://registry.npmjs.org/next-beta' => new JsonMockResponse([
                'dist-tags' => ['latest' => '2.0.0-beta.4'],
            ]),
            'https://registry.npmjs.org/next-beta-nodot' => new JsonMockResponse([
                'dist-tags' => ['latest' => '5.0.0-beta2'],
            ]),
            'https://registry.npmjs.org/next-release' => new JsonMockResponse([
                'dist-tags' => ['latest' => '2.0.0'],
            ]),
            'https://registry.npmjs.org/next-patch' => new JsonMockResponse([
                'dist-tags' => ['latest' => '1.2.10'],
            ]),
            'https://registry.npmjs.org/next-preview' => new JsonMockResponse([
                'dist-tags' => ['latest' => '11.0.0'],
            ]),
            'https://registry.npmjs.org/next-numeric' => new JsonMockResponse([
                'dist-tags' => ['latest' => '1.2.3'],
            ]),
            'https://registry.npmjs.org/zero-minor' => new JsonMockResponse([
                'dist-tags' => ['latest' => '0.187.0'],
            ]),
            'https://registry.npmjs.org/zero-patch' => new JsonMockResponse([
                'dist-tags' => ['latest' => '0.186.1'],
            ]),
            'https://registry.npmjs.org/zero-zero-patch' => new JsonMockResponse([
                'dist-tags' => ['latest' => '0.0.4'],
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
