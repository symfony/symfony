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
use Symfony\Component\AssetMapper\Exception\RuntimeException;
use Symfony\Component\AssetMapper\ImportMap\ImportMapAuditor;
use Symfony\Component\AssetMapper\ImportMap\ImportMapConfigReader;
use Symfony\Component\AssetMapper\ImportMap\ImportMapEntries;
use Symfony\Component\AssetMapper\ImportMap\ImportMapEntry;
use Symfony\Component\AssetMapper\ImportMap\ImportMapManager;
use Symfony\Component\AssetMapper\ImportMap\ImportMapPackageAudit;
use Symfony\Component\AssetMapper\ImportMap\ImportMapPackageAuditVulnerability;
use Symfony\Component\AssetMapper\ImportMap\Resolver\PackageResolver;
use Symfony\Component\AssetMapper\ImportMap\Resolver\PackageResolverInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ImportMapAuditorTest extends TestCase
{
    private ImportMapConfigReader $importMapConfigReader;
    private PackageResolverInterface $packageResolver;
    private HttpClientInterface $httpClient;
    private ImportMapAuditor $importMapAuditor;

    protected function setUp(): void
    {
        $this->importMapConfigReader = $this->createMock(ImportMapConfigReader::class);
        $this->packageResolver = $this->createMock(PackageResolverInterface::class);
        $this->httpClient = new MockHttpClient();
        $this->importMapAuditor = new ImportMapAuditor($this->importMapConfigReader, $this->packageResolver, $this->httpClient);
    }

    public function testAudit()
    {
        $this->httpClient->setResponseFactory(new MockResponse(json_encode([
            [
                "ghsa_id" => "GHSA-abcd-1234-efgh",
                "cve_id" => "CVE-2050-00000",
                "url" => "https =>//api.github.com/repos/repo/a-package/security-advisories/GHSA-abcd-1234-efgh",
                "summary" => "A short summary of the advisory.",
                "severity" => "critical",
                "vulnerabilities" => [
                    [
                        "package" => ["ecosystem" => "pip", "name" => "json5"],
                        "vulnerable_version_range" => ">= 1.0.0, < 1.0.1",
                        "first_patched_version" => "1.0.1",
                    ],
                    [
                        "package" => ["ecosystem" => "npm", "name" => "json5"],
                        "vulnerable_version_range" => ">= 1.0.0, < 1.0.1",
                        "first_patched_version" => "1.0.1",
                    ],
                    [
                        "package" => ["ecosystem" => "npm", "name" => "another-package"],
                        "vulnerable_version_range" => ">= 1.0.0, < 1.0.1",
                        "first_patched_version" => "1.0.2",
                    ],
                ],
            ],
        ])));
        $this->importMapConfigReader->method('getEntries')->willReturn(new ImportMapEntries([
            '@hotwired/stimulus' => new ImportMapEntry(
                importName: '@hotwired/stimulus',
                url: 'https://unpkg.com/@hotwired/stimulus@3.2.1/dist/stimulus.js',
                version: '3.2.1',
            ),
            'json5' => new ImportMapEntry(
                importName: 'json5',
                url: 'https://cdn.jsdelivr.net/npm/json5@1.0.0/+esm',
                version: '1.0.0',
            ),
            'lodash' => new ImportMapEntry(
                importName: 'lodash',
                url: 'https://ga.jspm.io/npm:lodash@4.17.21/lodash.js',
                version: '4.17.21',
            ),
        ]));

        $audit = $this->importMapAuditor->audit();

        $this->assertEquals([
            new ImportMapPackageAudit('@hotwired/stimulus', '3.2.1'),
            new ImportMapPackageAudit('json5', '1.0.0', [new ImportMapPackageAuditVulnerability(
                'GHSA-abcd-1234-efgh',
                'CVE-2050-00000',
                'https =>//api.github.com/repos/repo/a-package/security-advisories/GHSA-abcd-1234-efgh',
                'A short summary of the advisory.',
                'critical',
                '>= 1.0.0, < 1.0.1',
                '1.0.1',
            )]),
            new ImportMapPackageAudit('lodash', '4.17.21'),
        ], $audit);
    }

    /**
     * @dataProvider provideAuditWithVersionRange
     */
    public function testAuditWithVersionRange(bool $expectMatch, string $version, ?string $versionRange)
    {
        $this->httpClient->setResponseFactory(new MockResponse(json_encode([
            [
                "ghsa_id" => "GHSA-abcd-1234-efgh",
                "cve_id" => "CVE-2050-00000",
                "url" => "https =>//api.github.com/repos/repo/a-package/security-advisories/GHSA-abcd-1234-efgh",
                "summary" => "A short summary of the advisory.",
                "severity" => "critical",
                "vulnerabilities" => [
                    [
                        "package" => ["ecosystem" => "npm", "name" => "json5"],
                        "vulnerable_version_range" => $versionRange,
                        "first_patched_version" => "1.0.1",
                    ],
                ],
            ],
        ])));
        $this->importMapConfigReader->method('getEntries')->willReturn(new ImportMapEntries([
            'json5' => new ImportMapEntry(
                importName: 'json5',
                url: "https://cdn.jsdelivr.net/npm/json5@$version/+esm",
                version: $version,
            ),
        ]));

        $audit = $this->importMapAuditor->audit();

        $this->assertSame($expectMatch, 0 < count($audit[0]->vulnerabilities));
    }

    public function provideAuditWithVersionRange(): iterable
    {
        yield [true, '1.0.0', null];
        yield [true, '1.0.0', '>= *'];
        yield [true, '1.0.0', '< 1.0.1'];
        yield [true, '1.0.0', '<= 1.0.0'];
        yield [false, '1.0.0', '< 1.0.0'];
        yield [true, '1.0.0', '= 1.0.0'];
        yield [false, '1.0.0', '> 1.0.0, < 1.2.0'];
        yield [true, '1.1.0', '> 1.0.0, < 1.2.0'];
        yield [false, '1.2.0', '> 1.0.0, < 1.2.0'];
    }

    public function testAuditWithVersionResolving()
    {
        $this->httpClient->setResponseFactory(new MockResponse('[]'));
        $this->importMapConfigReader->method('getEntries')->willReturn(new ImportMapEntries([
            '@hotwired/stimulus' => new ImportMapEntry(
                importName: '@hotwired/stimulus',
                url: 'https://unpkg.com/@hotwired/stimulus/dist/stimulus.js',
                version: '3.2.1',
            ),
            'json5' => new ImportMapEntry(
                importName: 'json5',
                url: 'https://cdn.jsdelivr.net/npm/json5/+esm',
            ),
            'lodash' => new ImportMapEntry(
                importName: 'lodash',
                url: 'https://ga.jspm.io/npm:lodash@4.17.21/lodash.js',
            ),
        ]));
        $this->packageResolver->method('getPackageVersion')->willReturn('1.2.3');

        $audit = $this->importMapAuditor->audit();

        $this->assertSame('3.2.1', $audit[0]->version);
        $this->assertSame('1.2.3', $audit[1]->version);
        $this->assertSame('1.2.3', $audit[2]->version);
    }

    public function testAuditError()
    {
        $this->httpClient->setResponseFactory(new MockResponse('Server error', ['http_code' => 500]));
        $this->importMapConfigReader->method('getEntries')->willReturn(new ImportMapEntries([
            'json5' => new ImportMapEntry(
                importName: 'json5',
                url: 'https://cdn.jsdelivr.net/npm/json5@1.0.0/+esm',
                version: '1.0.0',
            ),
        ]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error 500 auditing packages. Response: Server error');

        $this->importMapAuditor->audit();
    }
}
