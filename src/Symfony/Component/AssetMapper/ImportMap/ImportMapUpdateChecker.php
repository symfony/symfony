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

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ImportMapUpdateChecker
{
    private const URL_PACKAGE_METADATA = 'https://registry.npmjs.org/%s';

    private readonly HttpClientInterface $httpClient;

    public function __construct(
        private readonly ImportMapConfigReader $importMapConfigReader,
        ?HttpClientInterface $httpClient = null,
    ) {
        $this->httpClient = new BatchHttpClient($httpClient ?? HttpClient::create());
    }

    /**
     * @param string[] $packages
     *
     * @return PackageUpdateInfo[]
     */
    public function getAvailableUpdates(array $packages = []): array
    {
        $entries = $this->importMapConfigReader->getEntries();
        $updateInfos = [];
        $responses = [];
        foreach ($entries as $entry) {
            if (!$entry->isRemotePackage()) {
                continue;
            }
            if ($packages
                && !\in_array($entry->getPackageName(), $packages, true)
                && !\in_array($entry->importName, $packages, true)
            ) {
                continue;
            }

            $responses[$entry->importName] = $this->httpClient->request('GET', \sprintf(self::URL_PACKAGE_METADATA, $entry->getPackageName()), ['headers' => ['Accept' => 'application/vnd.npm.install-v1+json']]);
        }

        foreach ($responses as $importName => $response) {
            $entry = $entries->get($importName);
            if (200 !== $response->getStatusCode()) {
                throw new \RuntimeException(\sprintf('Unable to get latest version for package "%s".', $entry->getPackageName()));
            }
            $updateInfo = new PackageUpdateInfo($entry->getPackageName(), $entry->version);
            try {
                $updateInfo->latestVersion = json_decode($response->getContent(), true)['dist-tags']['latest'];
                $updateInfo->updateType = $this->getUpdateType($updateInfo->currentVersion, $updateInfo->latestVersion);
            } catch (\Exception $e) {
                throw new \RuntimeException(\sprintf('Unable to get latest version for package "%s".', $entry->getPackageName()), 0, $e);
            }
            $updateInfos[$importName] = $updateInfo;
        }

        return $updateInfos;
    }

    private function getVersionPart(string $version, int $part): ?string
    {
        return explode('.', $this->getVersionCore($version))[$part] ?? $version;
    }

    private function getVersionCore(string $version): string
    {
        return explode('-', $version, 2)[0];
    }

    private function getUpdateType(string $currentVersion, string $latestVersion): string
    {
        if (0 < ImportMapVersionChecker::compareVersions($currentVersion, $latestVersion)) {
            return PackageUpdateInfo::UPDATE_TYPE_DOWNGRADE;
        }
        if (0 === ImportMapVersionChecker::compareVersions($currentVersion, $latestVersion)) {
            return PackageUpdateInfo::UPDATE_TYPE_UP_TO_DATE;
        }
        if ($this->getVersionPart($currentVersion, 0) < $this->getVersionPart($latestVersion, 0)) {
            return PackageUpdateInfo::UPDATE_TYPE_MAJOR;
        }
        // like npm and Composer, ^0.186.0 excludes 0.187.0 and ^0.0.3 excludes 0.0.4
        if ($this->getVersionPart($currentVersion, 1) < $this->getVersionPart($latestVersion, 1)) {
            return str_starts_with($currentVersion, '0.') ? PackageUpdateInfo::UPDATE_TYPE_MAJOR : PackageUpdateInfo::UPDATE_TYPE_MINOR;
        }
        if ($this->getVersionPart($currentVersion, 2) < $this->getVersionPart($latestVersion, 2)) {
            return str_starts_with($currentVersion, '0.0.') ? PackageUpdateInfo::UPDATE_TYPE_MAJOR : PackageUpdateInfo::UPDATE_TYPE_PATCH;
        }
        if ($this->getVersionCore($currentVersion) === $this->getVersionCore($latestVersion)) {
            // only the pre-release differs: npm and Composer both accept 2.0.0-beta.4 and 2.0.0 for ^2.0.0-beta.3
            return PackageUpdateInfo::UPDATE_TYPE_PATCH;
        }

        throw new \LogicException(\sprintf('Unable to determine update type for "%s" and "%s".', $currentVersion, $latestVersion));
    }
}
