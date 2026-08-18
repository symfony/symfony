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

use Composer\Semver\VersionParser;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ImportMapUpdateChecker
{
    private const URL_PACKAGE_METADATA = 'https://registry.npmjs.org/%s';

    /**
     * The abbreviated metadata carries `dist-tags` but omits per-version publish
     * times, so it is only enough when the minimum release age is disabled.
     */
    private const NPM_ABBREVIATED_ACCEPT_HEADER = 'application/vnd.npm.install-v1+json';

    /**
     * The full packument (default `application/json`) is the only document that
     * exposes the `time` map required to honor the minimum release age.
     */
    private const NPM_FULL_PACKUMENT_ACCEPT_HEADER = 'application/json';

    private const CREATED_TIME_KEY = 'created';
    private const MODIFIED_TIME_KEY = 'modified';
    private const STABLE_STABILITY = 'stable';
    private const PRERELEASE_SEPARATOR = '-';
    private const RELEASE_AGE_MODIFIER_FORMAT = '-%d seconds';

    private readonly HttpClientInterface $httpClient;
    private readonly ClockInterface $clock;

    /**
     * @param int $minimumReleaseAge Minimum age, in seconds, a package version must have to be
     *                               eligible for an update (0 disables the check)
     */
    public function __construct(
        private readonly ImportMapConfigReader $importMapConfigReader,
        ?HttpClientInterface $httpClient = null,
        ?ClockInterface $clock = null,
        private readonly int $minimumReleaseAge = 0,
        private readonly ?LoggerInterface $logger = null,
    ) {
        $this->httpClient = new BatchHttpClient($httpClient ?? HttpClient::create());
        $this->clock = $clock ?? Clock::get();
    }

    public function getMinimumReleaseAge(): int
    {
        return $this->minimumReleaseAge;
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

            $responses[$entry->importName] = $this->httpClient->request('GET', \sprintf(self::URL_PACKAGE_METADATA, $entry->getPackageName()), [
                'headers' => ['Accept' => $this->minimumReleaseAge ? self::NPM_FULL_PACKUMENT_ACCEPT_HEADER : self::NPM_ABBREVIATED_ACCEPT_HEADER],
            ]);
        }

        foreach ($responses as $importName => $response) {
            $entry = $entries->get($importName);
            if (200 !== $response->getStatusCode()) {
                throw new \RuntimeException(\sprintf('Unable to get latest version for package "%s".', $entry->getPackageName()));
            }
            $updateInfo = new PackageUpdateInfo($entry->getPackageName(), $entry->version);
            try {
                $metadata = json_decode($response->getContent(), true);
                $declaredLatest = $metadata['dist-tags']['latest'] ?? null;
                $latestVersion = $this->getLatestVersion($metadata);

                // Under a minimum release age, a package can legitimately have no eligible
                // version yet: report it as up to date rather than dropping it, so that
                // "importmap:outdated" can still name the version being held back.
                if (null === $latestVersion) {
                    if (null === $declaredLatest) {
                        continue;
                    }

                    $this->logger?->warning('No stable version of package "{package}" is older than the configured minimum release age ({age} seconds).', [
                        'package' => $entry->getPackageName(),
                        'age' => $this->minimumReleaseAge,
                    ]);

                    $latestVersion = $entry->version;
                }

                $updateInfo->latestVersion = $latestVersion;
                $updateInfo->withheldVersion = $declaredLatest !== $latestVersion ? $declaredLatest : null;
                $updateInfo->updateType = $this->getUpdateType($updateInfo->currentVersion, $updateInfo->latestVersion);
            } catch (\Exception $e) {
                throw new \RuntimeException(\sprintf('Unable to get latest version for package "%s".', $entry->getPackageName()), 0, $e);
            }
            $updateInfos[$importName] = $updateInfo;
        }

        return $updateInfos;
    }

    private function getLatestVersion(array $metadata): ?string
    {
        if (!$this->minimumReleaseAge) {
            return $metadata['dist-tags']['latest'];
        }

        // `dist-tags.latest` is the maintainer's declared stable release and the upper
        // bound of what may be proposed. Without it there is no safe boundary, so nothing
        // is eligible.
        $latest = $metadata['dist-tags']['latest'] ?? null;
        if (null === $latest) {
            return null;
        }

        $boundary = $this->clock->now()->modify(\sprintf(self::RELEASE_AGE_MODIFIER_FORMAT, $this->minimumReleaseAge));

        // `time` records a publish timestamp for every version plus the `created`/`modified`
        // meta keys, and also keeps unpublished versions that are absent from `versions`.
        $publishedVersions = $metadata['versions'] ?? [];
        $times = $metadata['time'] ?? [];
        unset($times[self::CREATED_TIME_KEY], $times[self::MODIFIED_TIME_KEY]);
        uksort($times, version_compare(...));

        foreach (array_reverse($times, true) as $version => $timestamp) {
            if (!isset($publishedVersions[$version])) {
                continue; // unpublished/removed version, not installable
            }
            if (version_compare($version, $latest, '>')) {
                continue; // never exceed the declared latest stable
            }
            if (!$this->isStableVersion($version)) {
                continue; // ignore prereleases (alpha/beta/rc/dev/canary/next/...)
            }
            if (new \DateTimeImmutable($timestamp) <= $boundary) {
                return $version;
            }
        }

        return null;
    }

    private function isStableVersion(string $version): bool
    {
        // A SemVer prerelease is anything after a hyphen (rc/beta/canary/next/...), which
        // `parseStability()` does not always flag (e.g. `canary`); reject both signals.
        return !str_contains($version, self::PRERELEASE_SEPARATOR)
            && self::STABLE_STABILITY === VersionParser::parseStability($version);
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

        throw new \LogicException(\sprintf('Unable to determine update type for "%s" and "%s".', $currentVersion, $latestVersion));
    }
}
