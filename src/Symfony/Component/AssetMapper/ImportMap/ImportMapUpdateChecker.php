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

use Symfony\Contracts\HttpClient\HttpClientInterface;

class ImportMapUpdateChecker
{
    private const URL_PACKAGE_METADATA = 'https://registry.npmjs.org/%s';

    public function __construct(
        private readonly ImportMapConfigReader $importMapConfigReader,
        private readonly HttpClientInterface $httpClient,
    ) {
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

            $responses[$entry->importName] = $this->httpClient->request('GET', sprintf(self::URL_PACKAGE_METADATA, $entry->getPackageName()), ['headers' => ['Accept' => 'application/vnd.npm.install-v1+json']]);
        }

        foreach ($responses as $importName => $response) {
            $entry = $entries->get($importName);
            if (200 !== $response->getStatusCode()) {
                throw new \RuntimeException(sprintf('Unable to get latest version for package "%s".', $entry->getPackageName()));
            }
            $updateInfo = new PackageUpdateInfo($entry->getPackageName(), $entry->version);
            try {
                $updateInfo->latestVersion = json_decode($response->getContent(), true)['dist-tags']['latest'];
                $updateInfo->updateType = $this->getUpdateType($updateInfo->currentVersion, $updateInfo->latestVersion);
            } catch (\Exception $e) {
                throw new \RuntimeException(sprintf('Unable to get latest version for package "%s".', $entry->getPackageName()), 0, $e);
            }
            $updateInfos[$importName] = $updateInfo;
        }

        return $updateInfos;
    }

    private function getVersionPart(string $version, int $part): ?string
    {
        return explode('.', $version)[$part] ?? $version;
    }

    private function getUpdateType(string $currentVersion, string $latestVersion): string
    {
        if (version_compare($currentVersion, $latestVersion, '>')) {
            return PackageUpdateInfo::UPDATE_TYPE_DOWNGRADE;
        }
        if (version_compare($currentVersion, $latestVersion, '==')) {
            return PackageUpdateInfo::UPDATE_TYPE_UP_TO_DATE;
        }
        if ($this->getVersionPart($currentVersion, 0) < $this->getVersionPart($latestVersion, 0)) {
            return PackageUpdateInfo::UPDATE_TYPE_MAJOR;
        }
        if ($this->getVersionPart($currentVersion, 1) < $this->getVersionPart($latestVersion, 1)) {
            return PackageUpdateInfo::UPDATE_TYPE_MINOR;
        }
        if ($this->getVersionPart($currentVersion, 2) < $this->getVersionPart($latestVersion, 2)) {
            return PackageUpdateInfo::UPDATE_TYPE_PATCH;
        }

        throw new \LogicException(sprintf('Unable to determine update type for "%s" and "%s".', $currentVersion, $latestVersion));
    }
}
