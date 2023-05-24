<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ImportMap\Providers;

use PHPUnit\Framework\TestCase;
use Symfony\Component\AssetMapper\ImportMap\ImportMapManager;
use Symfony\Component\AssetMapper\ImportMap\PackageRequireOptions;
use Symfony\Component\AssetMapper\ImportMap\Resolver\JspmResolver;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class JspmResolverTest extends TestCase
{
    /**
     * @dataProvider provideResolvePackagesTests
     */
    public function testResolvePackages(array $packages, array $expectedInstallRequest, array $responseMap, array $expectedResolvedPackages, array $expectedDownloadedFiles)
    {
        $expectedRequestBody = [
            'install' => $expectedInstallRequest,
            'flattenScope' => true,
            'env' => ['browser', 'module', 'production'],
        ];
        $responseData = [
            'map' => [
                'imports' => $responseMap,
            ],
        ];

        $responses = [];
        $responses[] = function ($method, $url, $options) use ($responseData, $expectedRequestBody) {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.jspm.io/generate', $url);
            $this->assertSame($expectedRequestBody, json_decode($options['body'], true));

            return new MockResponse(json_encode($responseData));
        };
        // mock the "file download" requests
        foreach ($expectedDownloadedFiles as $file) {
            $responses[] = new MockResponse(sprintf('contents of %s', $file));
        }

        $httpClient = new MockHttpClient($responses);

        $provider = new JspmResolver($httpClient, ImportMapManager::PROVIDER_JSPM);
        $actualResolvedPackages = $provider->resolvePackages($packages);
        $this->assertCount(\count($expectedResolvedPackages), $actualResolvedPackages);
        foreach ($actualResolvedPackages as $package) {
            $packageName = $package->requireOptions->packageName;
            $this->assertArrayHasKey($packageName, $expectedResolvedPackages);
            $this->assertSame($expectedResolvedPackages[$packageName]['url'], $package->url);
        }
    }

    public static function provideResolvePackagesTests(): iterable
    {
        yield 'require single lodash package' => [
            'packages' => [new PackageRequireOptions('lodash')],
            'expectedInstallRequest' => ['lodash'],
            'responseMap' => [
                'lodash' => 'https://ga.jspm.io/npm:lodash@1.2.3/lodash.js',
            ],
            'expectedResolvedPackages' => [
                'lodash' => [
                    'url' => 'https://ga.jspm.io/npm:lodash@1.2.3/lodash.js',
                ],
            ],
            'expectedDownloadedFiles' => [],
        ];

        yield 'require two packages' => [
            'packages' => [new PackageRequireOptions('lodash'), new PackageRequireOptions('cowsay')],
            'expectedInstallRequest' => ['lodash', 'cowsay'],
            'responseMap' => [
                'lodash' => 'https://ga.jspm.io/npm:lodash@1.2.3/lodash.js',
                'cowsay' => 'https://ga.jspm.io/npm:cowsay@4.5.6/cowsay.js',
            ],
            'expectedResolvedPackages' => [
                'lodash' => [
                    'url' => 'https://ga.jspm.io/npm:lodash@1.2.3/lodash.js',
                ],
                'cowsay' => [
                    'url' => 'https://ga.jspm.io/npm:cowsay@4.5.6/cowsay.js',
                ],
            ],
            'expectedDownloadedFiles' => [],
        ];

        yield 'single_package_that_returns_as_two' => [
            'packages' => [new PackageRequireOptions('lodash')],
            'expectedInstallRequest' => ['lodash'],
            'responseMap' => [
                'lodash' => 'https://ga.jspm.io/npm:lodash@1.2.3/lodash.js',
                'lodash-dependency' => 'https://ga.jspm.io/npm:lodash-dependency@9.8.7/lodash-dependency.js',
            ],
            'expectedResolvedPackages' => [
                'lodash' => [
                    'url' => 'https://ga.jspm.io/npm:lodash@1.2.3/lodash.js',
                ],
                'lodash-dependency' => [
                    'url' => 'https://ga.jspm.io/npm:lodash-dependency@9.8.7/lodash-dependency.js',
                ],
            ],
            'expectedDownloadedFiles' => [],
        ];

        yield 'single_package_with_version_constraint' => [
            'packages' => [new PackageRequireOptions('lodash', '^1.2.3')],
            'expectedInstallRequest' => ['lodash@^1.2.3'],
            'responseMap' => [
                'lodash' => 'https://ga.jspm.io/npm:lodash@1.2.7/lodash.js',
            ],
            'expectedResolvedPackages' => [
                'lodash' => [
                    'url' => 'https://ga.jspm.io/npm:lodash@1.2.7/lodash.js',
                ],
            ],
            'expectedDownloadedFiles' => [],
        ];

        yield 'single_package_that_downloads' => [
            'packages' => [new PackageRequireOptions('lodash', download: true)],
            'expectedInstallRequest' => ['lodash'],
            'responseMap' => [
                'lodash' => 'https://ga.jspm.io/npm:lodash@1.2.3/lodash.js',
            ],
            'expectedResolvedPackages' => [
                'lodash' => [
                    'url' => 'https://ga.jspm.io/npm:lodash@1.2.3/lodash.js',
                    'downloaded_to' => 'vendor/lodash.js',
                ],
            ],
            'expectedDownloadedFiles' => [
                'assets/vendor/lodash.js',
            ],
        ];

        yield 'single_package_that_preloads' => [
            'packages' => [new PackageRequireOptions('lodash', preload: true)],
            'expectedInstallRequest' => ['lodash'],
            'responseMap' => [
                'lodash' => 'https://ga.jspm.io/npm:lodash@1.2.3/lodash.js',
                'lodash_dep' => 'https://ga.jspm.io/npm:dep@1.0.0/lodash_dep.js',
            ],
            'expectedResolvedPackages' => [
                'lodash' => [
                    'url' => 'https://ga.jspm.io/npm:lodash@1.2.3/lodash.js',
                    'preload' => true,
                ],
                'lodash_dep' => [
                    'url' => 'https://ga.jspm.io/npm:dep@1.0.0/lodash_dep.js',
                    // shares the preload - even though it wasn't strictly required
                    'preload' => true,
                ],
            ],
            'expectedDownloadedFiles' => [],
        ];

        yield 'single_package_with_jspm_custom_registry' => [
            'packages' => [new PackageRequireOptions('lodash', registryName: 'jspm')],
            'expectedInstallRequest' => ['jspm:lodash'],
            'responseMap' => [
                'lodash' => 'https://ga.jspm.io/npm:lodash@1.2.3/lodash.js',
            ],
            'expectedResolvedPackages' => [
                'lodash' => [
                    'url' => 'https://ga.jspm.io/npm:lodash@1.2.3/lodash.js',
                ],
            ],
            'expectedDownloadedFiles' => [],
        ];
    }
}
