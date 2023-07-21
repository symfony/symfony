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
use Symfony\Component\AssetMapper\ImportMap\PackageRequireOptions;
use Symfony\Component\AssetMapper\ImportMap\Resolver\JsDelivrEsmResolver;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class JsDelivrEsmResolverTest extends TestCase
{
    /**
     * @dataProvider provideResolvePackagesTests
     */
    public function testResolvePackages(array $packages, array $expectedRequests, array $expectedResolvedPackages)
    {
        $responses = [];
        foreach ($expectedRequests as $expectedRequest) {
            $responses[] = function ($method, $url) use ($expectedRequest) {
                $this->assertSame('GET', $method);
                $this->assertStringEndsWith($expectedRequest['url'], $url);

                $body = 'any body';
                if (isset($expectedRequest['response']['body'])) {
                    $body = \is_array($expectedRequest['response']['body']) ? json_encode($expectedRequest['response']['body']) : $expectedRequest['response']['body'];
                }

                return new MockResponse($body, [
                    'url' => $expectedRequest['response']['url'] ?? '/anything',
                ]);
            };
        }

        $httpClient = new MockHttpClient($responses);

        $provider = new JsDelivrEsmResolver($httpClient);
        $actualResolvedPackages = $provider->resolvePackages($packages);
        $this->assertCount(\count($expectedResolvedPackages), $actualResolvedPackages);
        foreach ($actualResolvedPackages as $package) {
            $packageName = $package->requireOptions->packageName;
            $this->assertArrayHasKey($packageName, $expectedResolvedPackages);
            $this->assertSame($expectedResolvedPackages[$packageName]['url'], $package->url);
            if (isset($expectedResolvedPackages[$packageName]['content'])) {
                $this->assertSame($expectedResolvedPackages[$packageName]['content'], $package->content);
            }
        }
    }

    public static function provideResolvePackagesTests(): iterable
    {
        yield 'require single lodash package' => [
            'packages' => [new PackageRequireOptions('lodash')],
            'expectedRequests' => [
                [
                    'url' => '/v1/packages/npm/lodash/resolved?specifier=%2A',
                    'response' => ['body' => ['version' => '1.2.3']],
                ],
                [
                    'url' => '/lodash@1.2.3/+esm',
                    'response' => ['url' => 'https://cdn.jsdelivr.net/npm/lodash.js@1.2.3/+esm'],
                ],
            ],
            'expectedResolvedPackages' => [
                'lodash' => [
                    'url' => 'https://cdn.jsdelivr.net/npm/lodash.js@1.2.3/+esm',
                ],
            ],
        ];

        yield 'require non-scoped package with version' => [
            'packages' => [new PackageRequireOptions('lodash', '^2')],
            'expectedRequests' => [
                [
                    'url' => '/v1/packages/npm/lodash/resolved?specifier=%5E2',
                    'response' => ['body' => ['version' => '2.1.3']],
                ],
                [
                    'url' => '/lodash@2.1.3/+esm',
                    'response' => ['url' => 'https://cdn.jsdelivr.net/npm/lodash.js@2.1.3/+esm'],
                ],
            ],
            'expectedResolvedPackages' => [
                'lodash' => [
                    'url' => 'https://cdn.jsdelivr.net/npm/lodash.js@2.1.3/+esm',
                ],
            ],
        ];

        yield 'require scoped package with version' => [
            'packages' => [new PackageRequireOptions('@hotwired/stimulus', '^3')],
            'expectedRequests' => [
                [
                    'url' => '/v1/packages/npm/@hotwired/stimulus/resolved?specifier=%5E3',
                    'response' => ['body' => ['version' => '3.1.3']],
                ],
                [
                    'url' => '/@hotwired/stimulus@3.1.3/+esm',
                    'response' => ['url' => 'https://cdn.jsdelivr.net/npm/@hotwired/stimulus.js@3.1.3/+esm'],
                ],
            ],
            'expectedResolvedPackages' => [
                '@hotwired/stimulus' => [
                    'url' => 'https://cdn.jsdelivr.net/npm/@hotwired/stimulus.js@3.1.3/+esm',
                ],
            ],
        ];

        yield 'require non-scoped package with path' => [
            'packages' => [new PackageRequireOptions('chart.js/auto', '^3')],
            'expectedRequests' => [
                [
                    'url' => '/v1/packages/npm/chart.js/resolved?specifier=%5E3',
                    'response' => ['body' => ['version' => '3.0.1']],
                ],
                [
                    'url' => '/chart.js@3.0.1/auto/+esm',
                    'response' => ['url' => 'https://cdn.jsdelivr.net/npm/chart.js@3.0.1/auto/+esm'],
                ],
            ],
            'expectedResolvedPackages' => [
                'chart.js/auto' => [
                    'url' => 'https://cdn.jsdelivr.net/npm/chart.js@3.0.1/auto/+esm',
                ],
            ],
        ];

        yield 'require scoped package with path' => [
            'packages' => [new PackageRequireOptions('@chart/chart.js/auto', '^3')],
            'expectedRequests' => [
                [
                    'url' => '/v1/packages/npm/@chart/chart.js/resolved?specifier=%5E3',
                    'response' => ['body' => ['version' => '3.0.1']],
                ],
                [
                    'url' => '/@chart/chart.js@3.0.1/auto/+esm',
                    'response' => ['url' => 'https://cdn.jsdelivr.net/npm/@chart/chart.js@3.0.1/auto/+esm'],
                ],
            ],
            'expectedResolvedPackages' => [
                '@chart/chart.js/auto' => [
                    'url' => 'https://cdn.jsdelivr.net/npm/@chart/chart.js@3.0.1/auto/+esm',
                ],
            ],
        ];

        yield 'require package with simple download' => [
            'packages' => [new PackageRequireOptions('lodash', download: true)],
            'expectedRequests' => [
                [
                    'url' => '/v1/packages/npm/lodash/resolved?specifier=%2A',
                    'response' => ['body' => ['version' => '1.2.3']],
                ],
                [
                    'url' => '/lodash@1.2.3/+esm',
                    'response' => [
                        'url' => 'https://cdn.jsdelivr.net/npm/lodash.js@1.2.3/+esm',
                        'body' => 'contents of file',
                    ],
                ],
            ],
            'expectedResolvedPackages' => [
                'lodash' => [
                    'url' => 'https://cdn.jsdelivr.net/npm/lodash.js@1.2.3/+esm',
                    'content' => 'contents of file',
                ],
            ],
        ];

        yield 'require package download with import dependencies' => [
            'packages' => [new PackageRequireOptions('lodash', download: true)],
            'expectedRequests' => [
                // lodash
                [
                    'url' => '/v1/packages/npm/lodash/resolved?specifier=%2A',
                    'response' => ['body' => ['version' => '1.2.3']],
                ],
                [
                    'url' => '/lodash@1.2.3/+esm',
                    'response' => [
                        'url' => 'https://cdn.jsdelivr.net/npm/lodash.js@1.2.3/+esm',
                        'body' => 'import{Color as t}from"/npm/@kurkle/color@0.3.2/+esm";console.log("yo");',
                    ],
                ],
                // @kurkle/color
                [
                    'url' => '/v1/packages/npm/@kurkle/color/resolved?specifier=0.3.2',
                    'response' => ['body' => ['version' => '0.3.2']],
                ],
                [
                    'url' => '/@kurkle/color@0.3.2/+esm',
                    'response' => [
                        'url' => 'https://cdn.jsdelivr.net/npm/@kurkle/color@0.3.2/+esm',
                        'body' => 'import*as t from"/npm/@popperjs/core@2.11.7/+esm";// hello world',
                    ],
                ],
                // @popperjs/core
                [
                    'url' => '/v1/packages/npm/@popperjs/core/resolved?specifier=2.11.7',
                    'response' => ['body' => ['version' => '2.11.7']],
                ],
                [
                    'url' => '/@popperjs/core@2.11.7/+esm',
                    'response' => [
                        'url' => 'https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.7/+esm',
                        // point back to the original to try to confuse things or cause extra work
                        'body' => 'import*as t from"/npm/lodash@1.2.9/+esm";// hello from popper',
                    ],
                ],
            ],
            'expectedResolvedPackages' => [
                'lodash' => [
                    'url' => 'https://cdn.jsdelivr.net/npm/lodash.js@1.2.3/+esm',
                    // file was updated correctly
                    'content' => 'import{Color as t}from"@kurkle/color";console.log("yo");',
                ],
                '@kurkle/color' => [
                    'url' => 'https://cdn.jsdelivr.net/npm/@kurkle/color@0.3.2/+esm',
                    'content' => 'import*as t from"@popperjs/core";// hello world',
                ],
                '@popperjs/core' => [
                    'url' => 'https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.7/+esm',
                    'content' => 'import*as t from"lodash";// hello from popper',
                ],
            ],
        ];
    }

    public function testImportRegex()
    {
        $subject = 'import{Color as t}from"/npm/@kurkle/color@0.3.2/+esm";import t from"/npm/jquery@3.7.0/+esm";import e from"/npm/popper.js@1.16.1/+esm";console.log("yo");';
        preg_match_all(JsDelivrEsmResolver::IMPORT_REGEX, $subject, $matches);

        $this->assertCount(3, $matches[0]);
        $this->assertSame([
            '@kurkle/color',
            'jquery',
            'popper.js',
        ], $matches[1]);
        $this->assertSame([
            '0.3.2',
            '3.7.0',
            '1.16.1',
        ], $matches[2]);
    }
}
