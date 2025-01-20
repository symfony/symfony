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

use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\AssetMapper\ImportMap\Resolver\PackageResolverInterface;
use Symfony\Component\AssetMapper\MappedAsset;

/**
 * @author Kévin Dunglas <kevin@dunglas.dev>
 * @author Ryan Weaver <ryan@symfonycasts.com>
 *
 * @final
 */
class ImportMapManager
{
    public function __construct(
        private readonly AssetMapperInterface $assetMapper,
        private readonly ImportMapConfigReader $importMapConfigReader,
        private readonly RemotePackageDownloader $packageDownloader,
        private readonly PackageResolverInterface $resolver,
    ) {
    }

    /**
     * Adds or updates packages.
     *
     * @param PackageRequireOptions[] $packages
     *
     * @return ImportMapEntry[]
     */
    public function require(array $packages): array
    {
        return $this->updateImportMapConfig(false, $packages, [], []);
    }

    /**
     * Removes packages.
     *
     * @param string[] $packages
     */
    public function remove(array $packages): void
    {
        $this->updateImportMapConfig(false, [], $packages, []);
    }

    /**
     * Updates either all existing packages or the specified ones to the latest version.
     *
     * @return ImportMapEntry[]
     */
    public function update(array $packages = []): array
    {
        return $this->updateImportMapConfig(true, [], [], $packages);
    }

    /**
     * @internal
     */
    public static function parsePackageName(string $packageName): ?array
    {
        // https://regex101.com/r/z1nj7P/1
        $regex = '/((?P<package>@?[^=@\n]+))(?:@(?P<version>[^=\s\n]+))?(?:=(?P<alias>[^\s\n]+))?/';

        if (!preg_match($regex, $packageName, $matches)) {
            return null;
        }

        if (isset($matches['version']) && '' === $matches['version']) {
            unset($matches['version']);
        }

        return $matches;
    }

    /**
     * @param PackageRequireOptions[] $packagesToRequire
     * @param string[]                $packagesToRemove
     *
     * @return ImportMapEntry[]
     */
    private function updateImportMapConfig(bool $update, array $packagesToRequire, array $packagesToRemove, array $packagesToUpdate): array
    {
        $currentEntries = $this->importMapConfigReader->getEntries();

        foreach ($packagesToRemove as $packageName) {
            if (!$currentEntries->has($packageName)) {
                throw new \InvalidArgumentException(\sprintf('Package "%s" listed for removal was not found in "importmap.php".', $packageName));
            }

            $this->cleanupPackageFiles($currentEntries->get($packageName));
            $currentEntries->remove($packageName);
        }

        if ($update) {
            foreach ($currentEntries as $entry) {
                $importName = $entry->importName;
                if (!$entry->isRemotePackage() || ($packagesToUpdate && !\in_array($importName, $packagesToUpdate, true))) {
                    continue;
                }

                $packagesToRequire[] = new PackageRequireOptions(
                    $entry->packageModuleSpecifier,
                    null,
                    $importName,
                );

                // remove it: then it will be re-added
                $this->cleanupPackageFiles($entry);
                $currentEntries->remove($importName);
            }
        }

        $newEntries = $this->requirePackages($packagesToRequire, $currentEntries);
        $this->importMapConfigReader->writeEntries($currentEntries);
        $this->packageDownloader->downloadPackages();

        return $newEntries;
    }

    /**
     * Gets information about (and optionally downloads) the packages & updates the entries.
     *
     * Returns an array of the entries that were added.
     *
     * @param PackageRequireOptions[] $packagesToRequire
     */
    private function requirePackages(array $packagesToRequire, ImportMapEntries $importMapEntries): array
    {
        if (!$packagesToRequire) {
            return [];
        }

        $addedEntries = [];
        // handle local packages
        foreach ($packagesToRequire as $key => $requireOptions) {
            if (null === $requireOptions->path) {
                continue;
            }

            $path = $requireOptions->path;
            if (!$asset = $this->findAsset($path)) {
                throw new \LogicException(\sprintf('The path "%s" of the package "%s" cannot be found: either pass the logical name of the asset or a relative path starting with "./".', $requireOptions->path, $requireOptions->importName));
            }

            // convert to a relative path (or fallback to the logical path)
            $path = $asset->logicalPath;
            if (null !== $relativePath = $this->importMapConfigReader->convertFilesystemPathToPath($asset->sourcePath)) {
                $path = $relativePath;
            }

            $newEntry = ImportMapEntry::createLocal(
                $requireOptions->importName,
                self::getImportMapTypeFromFilename($requireOptions->path),
                $path,
                $requireOptions->entrypoint,
            );
            $importMapEntries->add($newEntry);
            $addedEntries[] = $newEntry;
            unset($packagesToRequire[$key]);
        }

        if (!$packagesToRequire) {
            return $addedEntries;
        }

        $resolvedPackages = $this->resolver->resolvePackages($packagesToRequire);
        foreach ($resolvedPackages as $resolvedPackage) {
            $newEntry = $this->importMapConfigReader->createRemoteEntry(
                $resolvedPackage->requireOptions->importName,
                $resolvedPackage->type,
                $resolvedPackage->version,
                $resolvedPackage->requireOptions->packageModuleSpecifier,
                $resolvedPackage->requireOptions->entrypoint,
            );
            $importMapEntries->add($newEntry);
            $addedEntries[] = $newEntry;
        }

        return $addedEntries;
    }

    private function cleanupPackageFiles(ImportMapEntry $entry): void
    {
        $asset = $this->findAsset($entry->path);

        if ($asset && is_file($asset->sourcePath)) {
            @unlink($asset->sourcePath);
        }
    }

    private static function getImportMapTypeFromFilename(string $path): ImportMapType
    {
        return str_ends_with($path, '.css') ? ImportMapType::CSS : ImportMapType::JS;
    }

    /**
     * Finds the MappedAsset allowing for a "logical path", relative or absolute filesystem path.
     */
    private function findAsset(string $path): ?MappedAsset
    {
        if ($asset = $this->assetMapper->getAsset($path)) {
            return $asset;
        }

        return $this->assetMapper->getAssetFromSourcePath($this->importMapConfigReader->convertPathToFilesystemPath($path));
    }
}
