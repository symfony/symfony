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
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Manages the local storage of remote/vendor importmap packages.
 */
class RemotePackageStorage
{
    private readonly Filesystem $filesystem;

    public function __construct(private readonly string $vendorDir)
    {
        $this->filesystem = new Filesystem();
    }

    public function getStorageDir(): string
    {
        return $this->vendorDir;
    }

    public function isDownloaded(ImportMapEntry $entry): bool
    {
        if (!$entry->isRemotePackage()) {
            throw new \InvalidArgumentException(\sprintf('The entry "%s" is not a remote package.', $entry->importName));
        }

        return is_file($this->getDownloadPath($entry->packageModuleSpecifier, $entry->type));
    }

    public function isExtraFileDownloaded(ImportMapEntry $entry, string $extraFilename): bool
    {
        if (!$entry->isRemotePackage()) {
            throw new \InvalidArgumentException(\sprintf('The entry "%s" is not a remote package.', $entry->importName));
        }

        return is_file($this->getExtraFileDownloadPath($entry, $extraFilename));
    }

    public function save(ImportMapEntry $entry, string $contents): void
    {
        if (!$entry->isRemotePackage()) {
            throw new \InvalidArgumentException(\sprintf('The entry "%s" is not a remote package.', $entry->importName));
        }

        $vendorPath = $this->getDownloadPath($entry->packageModuleSpecifier, $entry->type);

        try {
            $this->filesystem->dumpFile($vendorPath, $contents);
        } catch (IOException $e) {
            throw new RuntimeException(\sprintf('Failed to write file "%s".', $vendorPath), 0, $e);
        }
    }

    public function saveExtraFile(ImportMapEntry $entry, string $extraFilename, string $contents): void
    {
        if (!$entry->isRemotePackage()) {
            throw new \InvalidArgumentException(\sprintf('The entry "%s" is not a remote package.', $entry->importName));
        }

        $vendorPath = $this->getExtraFileDownloadPath($entry, $extraFilename);

        try {
            $this->filesystem->dumpFile($vendorPath, $contents);
        } catch (IOException $e) {
            throw new RuntimeException(\sprintf('Failed to write file "%s".', $vendorPath), 0, $e);
        }
    }

    /**
     * The local file path where a downloaded package should be stored.
     */
    public function getDownloadPath(string $packageModuleSpecifier, ImportMapType $importMapType): string
    {
        [$packageName, $packagePathString] = ImportMapEntry::splitPackageNameAndFilePath($packageModuleSpecifier);
        $filename = $packageName;
        if ($packagePathString) {
            $filename .= '/'.ltrim($packagePathString, '/');
        } else {
            // if we're requiring a bare package, we put it into the directory
            // (in case we also import other files from the package) and arbitrarily
            // name it the same as the package name + ".index"
            $filename .= '/'.basename($packageName).'.index';
        }

        if (!str_ends_with($filename, '.'.$importMapType->value)) {
            $filename .= '.'.$importMapType->value;
        }

        return $this->vendorDir.'/'.$filename;
    }

    private function getExtraFileDownloadPath(ImportMapEntry $entry, string $extraFilename): string
    {
        return $this->vendorDir.'/'.$entry->getPackageName().'/'.ltrim($extraFilename, '/');
    }
}
