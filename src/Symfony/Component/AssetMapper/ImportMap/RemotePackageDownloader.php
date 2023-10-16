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

use Symfony\Component\AssetMapper\ImportMap\Resolver\PackageResolverInterface;

/**
 * @final
 */
class RemotePackageDownloader
{
    private array $installed;

    public function __construct(
        private readonly RemotePackageStorage $remotePackageStorage,
        private readonly ImportMapConfigReader $importMapConfigReader,
        private readonly PackageResolverInterface $packageResolver,
    ) {
    }

    /**
     * Downloads all packages.
     *
     * @return string[] The downloaded packages
     */
    public function downloadPackages(callable $progressCallback = null): array
    {
        try {
            $installed = $this->loadInstalled();
        } catch (\InvalidArgumentException) {
            $installed = [];
        }
        $entries = $this->importMapConfigReader->getEntries();
        $remoteEntriesToDownload = [];
        $newInstalled = [];
        foreach ($entries as $entry) {
            if (!$entry->isRemotePackage()) {
                continue;
            }

            // if the file exists at the correct version, skip it
            if (
                isset($installed[$entry->importName])
                && $installed[$entry->importName]['version'] === $entry->version
                && $this->remotePackageStorage->isDownloaded($entry)
            ) {
                $newInstalled[$entry->importName] = $installed[$entry->importName];
                continue;
            }

            $remoteEntriesToDownload[$entry->importName] = $entry;
        }

        if (!$remoteEntriesToDownload) {
            return [];
        }

        $contents = $this->packageResolver->downloadPackages($remoteEntriesToDownload, $progressCallback);
        $downloadedPackages = [];
        foreach ($remoteEntriesToDownload as $package => $entry) {
            if (!isset($contents[$package])) {
                throw new \LogicException(sprintf('The package "%s" was not downloaded.', $package));
            }

            $this->remotePackageStorage->save($entry, $contents[$package]);
            $newInstalled[$package] = [
                'version' => $entry->version,
            ];

            $downloadedPackages[] = $package;
            unset($contents[$package]);
        }

        if ($contents) {
            throw new \LogicException(sprintf('The following packages were unexpectedly downloaded: "%s".', implode('", "', array_keys($contents))));
        }

        $this->saveInstalled($newInstalled);

        return $downloadedPackages;
    }

    public function getVendorDir(): string
    {
        return $this->remotePackageStorage->getStorageDir();
    }

    /**
     * @return array<string, array{path: string, version: string}>
     */
    private function loadInstalled(): array
    {
        if (isset($this->installed)) {
            return $this->installed;
        }

        $installedPath = $this->remotePackageStorage->getStorageDir().'/installed.php';
        $installed = is_file($installedPath) ? (static fn () => include $installedPath)() : [];

        foreach ($installed as $package => $data) {
            if (!isset($data['version'])) {
                throw new \InvalidArgumentException(sprintf('The package "%s" is missing its version.', $package));
            }
        }

        return $this->installed = $installed;
    }

    private function saveInstalled(array $installed): void
    {
        $this->installed = $installed;
        file_put_contents($this->remotePackageStorage->getStorageDir().'/installed.php', sprintf('<?php return %s;', var_export($installed, true)));
    }
}
