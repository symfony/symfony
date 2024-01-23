<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper\ImportMap;

use Symfony\Component\AssetMapper\Exception\RuntimeException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ImportMapAuditor
{
    private const AUDIT_URL = 'https://api.github.com/advisories';

    private readonly HttpClientInterface $httpClient;

    public function __construct(
        private readonly ImportMapConfigReader $configReader,
        ?HttpClientInterface $httpClient = null,
    ) {
        $this->httpClient = $httpClient ?? HttpClient::create();
    }

    /**
     * @return list<ImportMapPackageAudit>
     */
    public function audit(): array
    {
        $entries = $this->configReader->getEntries();

        /** @var array<string, ImportMapPackageAudit> $packageAudits */
        $packageAudits = [];

        /** @var array<string, list<string>> $installed */
        $installed = [];
        $affectsQuery = [];
        foreach ($entries as $entry) {
            if (!$entry->isRemotePackage()) {
                continue;
            }
            $version = $entry->version;

            $packageName = $entry->getPackageName();
            $installed[$packageName] ??= [];
            $installed[$packageName][] = $version;

            $packageVersion = $packageName.'@'.$version;
            $packageAudits[$packageVersion] ??= new ImportMapPackageAudit($packageName, $version);
            $affectsQuery[] = $packageVersion;
        }

        if (!$affectsQuery) {
            return [];
        }

        // @see https://docs.github.com/en/rest/security-advisories/global-advisories?apiVersion=2022-11-28#list-global-security-advisories
        $response = $this->httpClient->request('GET', self::AUDIT_URL, [
            'query' => ['affects' => implode(',', $affectsQuery)],
        ]);

        if (200 !== $response->getStatusCode()) {
            throw new RuntimeException(sprintf('Error %d auditing packages. Response: '.$response->getContent(false), $response->getStatusCode()));
        }

        foreach ($response->toArray() as $advisory) {
            foreach ($advisory['vulnerabilities'] ?? [] as $vulnerability) {
                if (
                    null === $vulnerability['package']
                    || 'npm' !== $vulnerability['package']['ecosystem']
                    || !\array_key_exists($package = $vulnerability['package']['name'], $installed)
                ) {
                    continue;
                }
                foreach ($installed[$package] as $version) {
                    if (!$version || !$this->versionMatches($version, $vulnerability['vulnerable_version_range'] ?? '>= *')) {
                        continue;
                    }
                    $packageAudits[$package.'@'.$version] = $packageAudits[$package.'@'.$version]->withVulnerability(
                        new ImportMapPackageAuditVulnerability(
                            $advisory['ghsa_id'],
                            $advisory['cve_id'],
                            $advisory['url'],
                            $advisory['summary'],
                            $advisory['severity'],
                            $vulnerability['vulnerable_version_range'],
                            $vulnerability['first_patched_version'],
                        )
                    );
                }
            }
        }

        return array_values($packageAudits);
    }

    private function versionMatches(string $version, string $ranges): bool
    {
        foreach (explode(',', $ranges) as $rangeString) {
            $range = explode(' ', trim($rangeString));
            if (1 === \count($range)) {
                $range = ['=', $range[0]];
            }

            if (!version_compare($version, $range[1], $range[0])) {
                return false;
            }
        }

        return true;
    }
}
