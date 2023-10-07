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
        private readonly ImportMapConfigReader $importMapConfigReader,
        private readonly PackageResolverInterface $packageResolver,
        private readonly string $vendorDir,
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
                && file_exists($this->vendorDir.'/'.$installed[$entry->importName]['path'])
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

            $filename = $this->savePackage($package, $contents[$package], $entry->type);
            $newInstalled[$package] = [
                'path' => $filename,
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

    public function getDownloadedPath(string $importName): string
    {
        $installed = $this->loadInstalled();
        if (!isset($installed[$importName])) {
            throw new \InvalidArgumentException(sprintf('The "%s" vendor asset is missing. Run "php bin/console importmap:install".', $importName));
        }

        return $this->vendorDir.'/'.$installed[$importName]['path'];
    }

    public function getVendorDir(): string
    {
        return $this->vendorDir;
    }

    private function savePackage(string $packageName, string $packageContents, ImportMapType $importMapType): string
    {
        $filename = $packageName;
        if (!str_contains(basename($packageName), '.')) {
            $filename .= '.'.$importMapType->value;
        }
        $vendorPath = $this->vendorDir.'/'.$filename;

        @mkdir(\dirname($vendorPath), 0777, true);
        file_put_contents($vendorPath, $packageContents);

        return $filename;
    }

    /**
     * @return array<string, array{path: string, version: string}>
     */
    private function loadInstalled(): array
    {
        if (isset($this->installed)) {
            return $this->installed;
        }

        $installedPath = $this->vendorDir.'/installed.php';
        $installed = is_file($installedPath) ? (static fn () => include $installedPath)() : [];

        foreach ($installed as $package => $data) {
            if (!isset($data['path'])) {
                throw new \InvalidArgumentException(sprintf('The package "%s" is missing its path.', $package));
            }

            if (!isset($data['version'])) {
                throw new \InvalidArgumentException(sprintf('The package "%s" is missing its version.', $package));
            }

            if (!is_file($this->vendorDir.'/'.$data['path'])) {
                unset($installed[$package]);
            }
        }

        $this->installed = $installed;

        return $installed;
    }

    private function saveInstalled(array $installed): void
    {
        $this->installed = $installed;
        file_put_contents($this->vendorDir.'/installed.php', sprintf('<?php return %s;', var_export($installed, true)));
    }
}
