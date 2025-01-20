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
use Symfony\Component\AssetMapper\ImportMap\ImportMapVersionChecker;
use Symfony\Component\AssetMapper\ImportMap\PackageVersionProblem;
use Symfony\Component\AssetMapper\ImportMap\RemotePackageDownloader;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class ImportMapVersionCheckerTest extends TestCase
{
    /**
     * @dataProvider getCheckVersionsTests
     */
    public function testCheckVersions(array $importMapEntries, array $dependencies, array $expectedRequests, array $expectedProblems)
    {
        $configReader = $this->createMock(ImportMapConfigReader::class);
        $configReader->expects($this->once())
            ->method('getEntries')
            ->willReturn(new ImportMapEntries($importMapEntries));

        $remoteDownloader = $this->createMock(RemotePackageDownloader::class);
        $remoteDownloader->expects($this->exactly(\count($importMapEntries)))
            ->method('getDependencies')
            ->with($this->callback(function ($importName) use ($importMapEntries) {
                foreach ($importMapEntries as $entry) {
                    if ($entry->importName === $importName) {
                        return true;
                    }
                }

                return false;
            }))
            ->willReturnCallback(function ($importName) use ($dependencies) {
                if (!isset($dependencies[$importName])) {
                    throw new \InvalidArgumentException(\sprintf('Missing dependencies in test for "%s"', $importName));
                }

                return $dependencies[$importName];
            });

        $responses = [];
        foreach ($expectedRequests as $expectedRequest) {
            $responses[] = function ($method, $url) use ($expectedRequest) {
                $this->assertStringEndsWith($expectedRequest['url'], $url);

                return new MockResponse(json_encode($expectedRequest['response']));
            };
        }
        $httpClient = new MockHttpClient($responses);

        $versionChecker = new ImportMapVersionChecker($configReader, $remoteDownloader, $httpClient);
        $problems = $versionChecker->checkVersions();
        $this->assertEquals($expectedProblems, $problems);
        $this->assertSame(\count($expectedRequests), $httpClient->getRequestsCount());
    }

    public static function getCheckVersionsTests()
    {
        yield 'no dependencies' => [
            [
                self::createRemoteEntry('foo', '1.0.0'),
            ],
            [
                'foo' => [],
            ],
            [],
            [],
        ];

        yield 'single with dependency but no problem' => [
            [
                self::createRemoteEntry('foo', version: '1.0.0'),
                self::createRemoteEntry('bar', version: '1.5.0'),
            ],
            [
                'foo' => ['bar'],
                'bar' => [],
            ],
            [
                [
                    'url' => '/foo/1.0.0',
                    'response' => [
                        'dependencies' => ['bar' => '1.2.7 || 1.2.9- v2.0.0'],
                    ],
                ],
            ],
            [],
        ];

        yield 'single with dependency with problem' => [
            [
                self::createRemoteEntry('foo', version: '1.0.0'),
                self::createRemoteEntry('bar', version: '1.5.0'),
            ],
            [
                'foo' => ['bar'],
                'bar' => [],
            ],
            [
                [
                    'url' => '/foo/1.0.0',
                    'response' => [
                        'dependencies' => ['bar' => '^2.0.0'],
                    ],
                ],
            ],
            [
                new PackageVersionProblem('foo', 'bar', '^2.0.0', '1.5.0'),
            ],
        ];

        yield 'single with dependency & different package specifier with problem' => [
            [
                self::createRemoteEntry('foo', version: '1.0.0', packageModuleSpecifier: 'foo_package'),
                self::createRemoteEntry('bar', version: '1.5.0', packageModuleSpecifier: 'bar_package'),
            ],
            [
                'foo' => ['bar'],
                'bar' => [],
            ],
            [
                [
                    'url' => '/foo_package/1.0.0',
                    'response' => [
                        'dependencies' => ['bar_package' => '^2.0.0'],
                    ],
                ],
            ],
            [
                new PackageVersionProblem('foo_package', 'bar_package', '^2.0.0', '1.5.0'),
            ],
        ];

        yield 'single with missing dependency' => [
            [
                self::createRemoteEntry('foo', version: '1.0.0'),
            ],
            [
                'foo' => ['bar'],
            ],
            [
                [
                    'url' => '/foo/1.0.0',
                    'response' => [
                        'dependencies' => ['bar' => '^2.0.0'],
                    ],
                ],
            ],
            [
                new PackageVersionProblem('foo', 'bar', '^2.0.0', null),
            ],
        ];

        yield 'multiple package and problems' => [
            [
                self::createRemoteEntry('foo', version: '1.0.0'),
                self::createRemoteEntry('bar', version: '1.5.0'),
                self::createRemoteEntry('baz', version: '2.0.0'),
            ],
            [
                'foo' => ['bar'],
                'bar' => ['baz'],
                'baz' => [],
            ],
            [
                [
                    'url' => '/foo/1.0.0',
                    'response' => [
                        'dependencies' => ['bar' => '^2.0.0'],
                    ],
                ],
                [
                    'url' => '/bar/1.5.0',
                    'response' => [
                        'dependencies' => ['baz' => '^1.0.0'],
                    ],
                ],
            ],
            [
                new PackageVersionProblem('foo', 'bar', '^2.0.0', '1.5.0'),
                new PackageVersionProblem('bar', 'baz', '^1.0.0', '2.0.0'),
            ],
        ];

        yield 'single with problem on peerDependency' => [
            [
                self::createRemoteEntry('foo', version: '1.0.0'),
                self::createRemoteEntry('bar', version: '1.5.0'),
            ],
            [
                'foo' => ['bar'],
                'bar' => [],
            ],
            [
                [
                    'url' => '/foo/1.0.0',
                    'response' => [
                        'peerDependencies' => ['bar' => '^2.0.0'],
                    ],
                ],
            ],
            [
                new PackageVersionProblem('foo', 'bar', '^2.0.0', '1.5.0'),
            ],
        ];

        yield 'single with npm-style constraint' => [
            [
                self::createRemoteEntry('foo', version: '1.0.0'),
                self::createRemoteEntry('bar', version: '1.5.0'),
            ],
            [
                'foo' => ['bar'],
                'bar' => [],
            ],
            [
                [
                    'url' => '/foo/1.0.0',
                    'response' => [
                        'dependencies' => ['bar' => '1.0.0 - v2.0.0'],
                    ],
                ],
            ],
            [],
        ];

        yield 'single with invalid constraint shows as problem' => [
            [
                self::createRemoteEntry('foo', version: '1.0.0'),
                self::createRemoteEntry('bar', version: '1.5.0'),
            ],
            [
                'foo' => ['bar'],
                'bar' => [],
            ],
            [
                [
                    'url' => '/foo/1.0.0',
                    'response' => [
                        'dependencies' => ['bar' => 'some/repo'],
                    ],
                ],
            ],
            [
                new PackageVersionProblem('foo', 'bar', 'some/repo', '1.5.0'),
            ],
        ];

        yield 'single with range constraint but no problem' => [
            [
                self::createRemoteEntry('foo', version: '1.0'),
                self::createRemoteEntry('bar', version: '2.0.3'),
            ],
            [
                'foo' => ['bar'],
                'bar' => [],
            ],
            [
                [
                    'url' => '/foo/1.0',
                    'response' => [
                        'dependencies' => ['bar' => '1.11 - 2'],
                    ],
                ],
            ],
            [],
        ];
    }

    /**
     * @dataProvider getNpmSpecificVersionConstraints
     */
    public function testNpmSpecificConstraints(string $npmConstraint, ?string $expectedComposerConstraint)
    {
        $this->assertSame($expectedComposerConstraint, ImportMapVersionChecker::convertNpmConstraint($npmConstraint));
    }

    public static function getNpmSpecificVersionConstraints()
    {
        // Simple cases
        yield 'simple no change' => [
            '1.2.*',
            '1.2.*',
        ];

        yield 'logical or with no change' => [
            '5.4.*|6.0.*',
            '5.4.*|6.0.*',
        ];

        yield 'other or syntax, spaces, no change' => [
            '>1.2.7 || <1.0.0',
            '>1.2.7 || <1.0.0',
        ];

        yield 'using v prefix' => [
            'v1.2.*',
            '1.2.*',
        ];

        // Hyphen Ranges
        yield 'hyphen range simple' => [
            '1.0.0 - 2.0.0',
            '1.0.0 - 2.0.0',
        ];

        yield 'hyphen range with v prefix' => [
            'v1.0.0 - 2.0.0',
            '1.0.0 - 2.0.0',
        ];

        yield 'hyphen range without patch' => [
            '1.0 - 2.0',
            '1.0 - 2.0',
        ];

        yield 'hyphen range with no spaces' => [
            '1.0-v2.0',
            '1.0 - 2.0',
        ];

        // .x Wildcards
        yield '.x wildcard' => [
            '5.4.x',
            '5.4.*',
        ];

        yield '.x wildcard without minor' => [
            '5.x',
            '5.*',
        ];

        // Multiple Constraints with Spaces
        yield 'multiple constraints' => [
            '>1.2.7 <=1.3.0',
            '>1.2.7 <=1.3.0',
        ];

        yield 'multiple constraints with v' => [
            '>v1.2.7 <=v1.3.0',
            '>1.2.7 <=1.3.0',
        ];

        yield 'mixed constraints with wildcard' => [
            '>=5.x <6.0.0',
            '>=5.* <6.0.0',
        ];

        // Pre-release Versions
        yield 'pre-release version' => [
            '1.2.3-beta.0',
            '1.2.3-beta.0',
        ];

        yield 'pre-release with v prefix' => [
            'v1.2.3-alpha.1',
            '1.2.3-alpha.1',
        ];

        // Constraints that don't translate to Composer
        yield 'latest tag' => [
            'latest',
            null,
        ];

        yield 'next tag' => [
            'next',
            null,
        ];

        yield 'local path' => [
            'file:../my-lib',
            null,
        ];

        yield 'git repository' => [
            'git://github.com/user/project.git#commit-ish',
            null,
        ];

        yield 'github shorthand' => [
            'user/repo#semver:^1.0.0',
            null,
        ];

        yield 'url' => [
            'https://example.com/module.tgz',
            null,
        ];

        yield 'multiple constraints with space and or operator' => [
            '1.2.7 || 1.2.9- v2.0.0',
            '1.2.7 || 1.2.9 - 2.0.0',
        ];

        yield 'tilde constraint with patch version no change' => [
            '~1.2.3',
            '~1.2.3',
        ];

        yield 'tilde constraint with minor version changes' => [
            '~1.2',
            '>=1.2.0 <1.3.0',
        ];

        yield 'tilde constraint with major version no change' => [
            '~1',
            '~1',
        ];
    }

    private static function createRemoteEntry(string $importName, string $version, ?string $packageModuleSpecifier = null): ImportMapEntry
    {
        $packageModuleSpecifier = $packageModuleSpecifier ?? $importName;

        return ImportMapEntry::createRemote($importName, ImportMapType::JS, '/path/to/'.$importName, $version, $packageModuleSpecifier, false);
    }
}
