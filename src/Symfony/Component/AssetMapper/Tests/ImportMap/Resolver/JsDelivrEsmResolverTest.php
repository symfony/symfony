<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper\Tests\ImportMap\Resolver;

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
                [
                    'url' => '/v1/packages/npm/lodash@1.2.3/entrypoints',
                    'response' => ['body' => ['entrypoints' => []]],
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
                [
                    'url' => '/v1/packages/npm/lodash@2.1.3/entrypoints',
                    'response' => ['body' => ['entrypoints' => []]],
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
                [
                    'url' => '/v1/packages/npm/@hotwired/stimulus@3.1.3/entrypoints',
                    'response' => ['body' => ['entrypoints' => []]],
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
                [
                    'url' => '/v1/packages/npm/chart.js@3.0.1/entrypoints',
                    'response' => ['body' => ['entrypoints' => []]],
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
                [
                    'url' => '/v1/packages/npm/@chart/chart.js@3.0.1/entrypoints',
                    'response' => ['body' => ['entrypoints' => []]],
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
                [
                    'url' => '/v1/packages/npm/lodash@1.2.3/entrypoints',
                    'response' => ['body' => ['entrypoints' => []]],
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
                [
                    'url' => '/v1/packages/npm/lodash@1.2.3/entrypoints',
                    'response' => ['body' => ['entrypoints' => []]],
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
                [
                    'url' => '/v1/packages/npm/@kurkle/color@0.3.2/entrypoints',
                    'response' => ['body' => ['entrypoints' => []]],
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
                [
                    'url' => '/v1/packages/npm/@popperjs/core@2.11.7/entrypoints',
                    'response' => ['body' => ['entrypoints' => []]],
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

        yield 'require single CSS package' => [
            'packages' => [new PackageRequireOptions('bootstrap/dist/css/bootstrap.min.css')],
            'expectedRequests' => [
                [
                    'url' => '/v1/packages/npm/bootstrap/resolved?specifier=%2A',
                    'response' => ['body' => ['version' => '3.3.0']],
                ],
                [
                    // CSS is detected: +esm is left off
                    'url' => '/bootstrap@3.3.0/dist/css/bootstrap.min.css',
                    'response' => ['url' => 'https://cdn.jsdelivr.net/npm/bootstrap.js@3.3.0/dist/css/bootstrap.min.css'],
                ],
            ],
            'expectedResolvedPackages' => [
                'bootstrap/dist/css/bootstrap.min.css' => [
                    'url' => 'https://cdn.jsdelivr.net/npm/bootstrap.js@3.3.0/dist/css/bootstrap.min.css',
                ],
            ],
        ];

        yield 'require package with style key grabs the CSS' => [
            'packages' => [new PackageRequireOptions('bootstrap', '^5')],
            'expectedRequests' => [
                [
                    'url' => '/v1/packages/npm/bootstrap/resolved?specifier=%5E5',
                    'response' => ['body' => ['version' => '5.2.0']],
                ],
                [
                    'url' => '/bootstrap@5.2.0/+esm',
                    'response' => ['url' => 'https://cdn.jsdelivr.net/npm/bootstrap.js@5.2.0/+esm'],
                ],
                [
                    'url' => '/v1/packages/npm/bootstrap@5.2.0/entrypoints',
                    'response' => ['body' => ['entrypoints' => [
                        'css' => ['file' => '/dist/css/bootstrap.min.css'],
                    ]]],
                ],
                [
                    'url' => '/v1/packages/npm/bootstrap/resolved?specifier=5.2.0',
                    'response' => ['body' => ['version' => '5.2.0']],
                ],
                [
                    // grab the found CSS
                    'url' => '/bootstrap@5.2.0/dist/css/bootstrap.min.css',
                    'response' => ['url' => 'https://cdn.jsdelivr.net/npm/bootstrap.js@5.2.0/dist/css/bootstrap.min.css'],
                ],
            ],
            'expectedResolvedPackages' => [
                'bootstrap' => [
                    'url' => 'https://cdn.jsdelivr.net/npm/bootstrap.js@5.2.0/+esm',
                ],
                'bootstrap/dist/css/bootstrap.min.css' => [
                    'url' => 'https://cdn.jsdelivr.net/npm/bootstrap.js@5.2.0/dist/css/bootstrap.min.css',
                ],
            ],
        ];

        yield 'require path in package skips grabbing the style key' => [
            'packages' => [new PackageRequireOptions('bootstrap/dist/modal.js', '^5')],
            'expectedRequests' => [
                [
                    'url' => '/v1/packages/npm/bootstrap/resolved?specifier=%5E5',
                    'response' => ['body' => ['version' => '5.2.0']],
                ],
                [
                    'url' => '/bootstrap@5.2.0/dist/modal.js/+esm',
                    'response' => ['url' => 'https://cdn.jsdelivr.net/npm/bootstrap.js@5.2.0/dist/modal.js+esm'],
                ],
            ],
            'expectedResolvedPackages' => [
                'bootstrap/dist/modal.js' => [
                    'url' => 'https://cdn.jsdelivr.net/npm/bootstrap.js@5.2.0/dist/modal.js+esm',
                ],
            ],
        ];
    }

    /**
     * @dataProvider provideImportRegex
     */
    public function testImportRegex(string $subject, array $expectedPackages)
    {
        preg_match_all(JsDelivrEsmResolver::IMPORT_REGEX, $subject, $matches);

        $this->assertCount(\count($expectedPackages), $matches[0]);
        $expectedNames = [];
        $expectedVersions = [];
        foreach ($expectedPackages as $packageData) {
            $expectedNames[] = $packageData[0];
            $expectedVersions[] = $packageData[1];
        }
        $this->assertSame($expectedNames, $matches[1]);
        $this->assertSame($expectedVersions, $matches[2]);
    }

    public static function provideImportRegex(): iterable
    {
        yield 'standard import format' => [
            'import{Color as t}from"/npm/@kurkle/color@0.3.2/+esm";import t from"/npm/jquery@3.7.0/+esm";import e from"/npm/popper.js@1.16.1/+esm";console.log("yo");',
            [
                ['@kurkle/color', '0.3.2'],
                ['jquery', '3.7.0'],
                ['popper.js', '1.16.1'],
            ],
        ];

        yield 'export and import format' => [
            'export*from"/npm/@vue/runtime-dom@3.3.4/+esm";const e=()=>{};export{e as compile};export default null;',
            [
                ['@vue/runtime-dom', '3.3.4'],
            ],
        ];

        yield 'multiple export format & import' => [
            'import{defineComponent as e,nextTick as t,createVNode as n,getCurrentInstance as r,watchPostEffect as s,onMounted as o,onUnmounted as i,h as a,BaseTransition as l,BaseTransitionPropsValidators as c,Fragment as u,Static as p,useTransitionState as f,onUpdated as d,toRaw as m,getTransitionRawChildren as h,setTransitionHooks as v,resolveTransitionHooks as g,createRenderer as _,createHydrationRenderer as b,camelize as y,callWithAsyncErrorHandling as C}from"/npm/@vue/runtime-core@3.3.4/+esm";export*from"/npm/@vue/runtime-core@3.3.4/+esm";import{isArray as S,camelize as E,toNumber as A,hyphenate as w,extend as T,EMPTY_OBJ as x,isObject as P,looseToNumber as k,looseIndexOf as L,isSet as N,looseEqual as $,isFunction as R,isString as M,invokeArrayFns as V,isOn as B,isModelListener as D,capitalize as I,isSpecialBooleanAttr as O,includeBooleanAttr as F}from"/npm/@vue/shared@3.3.4/+esm";const U="undefined"!=typeof document?',
            [
                ['@vue/runtime-core', '3.3.4'],
                ['@vue/runtime-core', '3.3.4'],
                ['@vue/shared', '3.3.4'],
            ],
        ];
    }

    /**
     * @dataProvider provideGetPackageVersion
     */
    public function testGetPackageVersion(string $url, ?string $expected)
    {
        $resolver = new JsDelivrEsmResolver();

        $this->assertSame($expected, $resolver->getPackageVersion($url));
    }

    public static function provideGetPackageVersion(): iterable
    {
        yield 'with no result' => ['https://cdn.jsdelivr.net/npm/lodash.js/+esm', null];
        yield 'with a package name' => ['https://cdn.jsdelivr.net/npm/lodash.js@1.2.3/+esm', '1.2.3'];
        yield 'with a dash in the package_name' => ['https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.7/+esm', '2.11.7'];
    }
}
