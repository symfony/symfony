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
use Symfony\Component\AssetMapper\Path\PublicAssetsPathResolverInterface;

/**
 * @author Kévin Dunglas <kevin@dunglas.dev>
 * @author Ryan Weaver <ryan@symfonycasts.com>
 *
 * @final
 */
class ImportMapManager
{
    public const POLYFILL_URL = 'https://ga.jspm.io/npm:es-module-shims@1.7.2/dist/es-module-shims.js';
    public const IMPORT_MAP_CACHE_FILENAME = 'importmap.json';
    public const ENTRYPOINT_CACHE_FILENAME_PATTERN = 'entrypoint.%s.json';

    public function __construct(
        private readonly AssetMapperInterface $assetMapper,
        private readonly PublicAssetsPathResolverInterface $assetsPathResolver,
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

    public function findRootImportMapEntry(string $moduleName): ?ImportMapEntry
    {
        $entries = $this->importMapConfigReader->getEntries();

        return $entries->has($moduleName) ? $entries->get($moduleName) : null;
    }

    /**
     * @internal
     *
     * @param string[] $entrypointNames
     *
     * @return array<string, array{path: string, type: string, preload?: bool}>
     */
    public function getImportMapData(array $entrypointNames): array
    {
        $rawImportMapData = $this->getRawImportMapData();
        $finalImportMapData = [];
        foreach ($entrypointNames as $entry) {
            $finalImportMapData[$entry] = $rawImportMapData[$entry];
            foreach ($this->findEagerEntrypointImports($entry) as $dependency) {
                if (isset($finalImportMapData[$dependency])) {
                    continue;
                }

                if (!isset($rawImportMapData[$dependency])) {
                    // missing dependency - rely on browser or compilers to warn
                    continue;
                }

                // re-order the final array by order of dependencies
                $finalImportMapData[$dependency] = $rawImportMapData[$dependency];
                // and mark for preloading
                $finalImportMapData[$dependency]['preload'] = true;
                unset($rawImportMapData[$dependency]);
            }
        }

        return array_merge($finalImportMapData, $rawImportMapData);
    }

    /**
     * @internal
     */
    public function getEntrypointMetadata(string $entrypointName): array
    {
        return $this->findEagerEntrypointImports($entrypointName);
    }

    /**
     * @internal
     */
    public function getEntrypointNames(): array
    {
        $rootEntries = $this->importMapConfigReader->getEntries();
        $entrypointNames = [];
        foreach ($rootEntries as $entry) {
            if ($entry->isEntrypoint) {
                $entrypointNames[] = $entry->importName;
            }
        }

        return $entrypointNames;
    }

    /**
     * @internal
     *
     * @return array<string, array{path: string, type: string}>
     */
    public function getRawImportMapData(): array
    {
        $dumpedImportMapPath = $this->assetsPathResolver->getPublicFilesystemPath().'/'.self::IMPORT_MAP_CACHE_FILENAME;
        if (is_file($dumpedImportMapPath)) {
            return json_decode(file_get_contents($dumpedImportMapPath), true, 512, \JSON_THROW_ON_ERROR);
        }

        $rootEntries = $this->importMapConfigReader->getEntries();
        $allEntries = [];
        foreach ($rootEntries as $rootEntry) {
            $allEntries[$rootEntry->importName] = $rootEntry;
            $allEntries = $this->addImplicitEntries($rootEntry, $allEntries, $rootEntries);
        }

        $rawImportMapData = [];
        foreach ($allEntries as $entry) {
            if ($entry->path) {
                $asset = $this->findAsset($entry->path);

                if (!$asset) {
                    throw new \InvalidArgumentException(sprintf('The asset "%s" cannot be found in any asset map paths.', $entry->path));
                }
            } else {
                $sourcePath = $this->packageDownloader->getDownloadedPath($entry->importName);
                $asset = $this->assetMapper->getAssetFromSourcePath($sourcePath);

                if (!$asset) {
                    throw new \InvalidArgumentException(sprintf('The "%s" vendor asset is missing. Run "php bin/console importmap:install".', $entry->importName));
                }
            }

            $path = $asset->publicPath;
            $data = ['path' => $path, 'type' => $entry->type->value];
            $rawImportMapData[$entry->importName] = $data;
        }

        return $rawImportMapData;
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
                throw new \InvalidArgumentException(sprintf('Package "%s" listed for removal was not found in "importmap.php".', $packageName));
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

                // assume the import name === package name, unless we can parse
                // the true package name from the URL
                $packageName = $importName;

                $packagesToRequire[] = new PackageRequireOptions(
                    $packageName,
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
                throw new \LogicException(sprintf('The path "%s" of the package "%s" cannot be found: either pass the logical name of the asset or a relative path starting with "./".', $requireOptions->path, $requireOptions->packageName));
            }

            $rootImportMapDir = $this->importMapConfigReader->getRootDirectory();
            // convert to a relative path (or fallback to the logical path)
            $path = $asset->logicalPath;
            if ($rootImportMapDir && str_starts_with(realpath($asset->sourcePath), realpath($rootImportMapDir))) {
                $path = './'.substr(realpath($asset->sourcePath), \strlen(realpath($rootImportMapDir)) + 1);
            }

            $newEntry = new ImportMapEntry(
                $requireOptions->packageName,
                path: $path,
                type: self::getImportMapTypeFromFilename($requireOptions->path),
                isEntrypoint: $requireOptions->entrypoint,
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
            $importName = $resolvedPackage->requireOptions->importName ?: $resolvedPackage->requireOptions->packageName;

            $newEntry = new ImportMapEntry(
                $importName,
                path: $resolvedPackage->requireOptions->path,
                version: $resolvedPackage->version,
                type: $resolvedPackage->type,
                isEntrypoint: $resolvedPackage->requireOptions->entrypoint,
            );
            $importMapEntries->add($newEntry);
            $addedEntries[] = $newEntry;
        }

        return $addedEntries;
    }

    private function cleanupPackageFiles(ImportMapEntry $entry): void
    {
        if (null === $entry->path) {
            return;
        }

        $asset = $this->findAsset($entry->path);

        if (!$asset) {
            throw new \LogicException(sprintf('The path "%s" of the package "%s" cannot be found in any asset map paths.', $entry->path, $entry->importName));
        }

        if (is_file($asset->sourcePath)) {
            @unlink($asset->sourcePath);
        }
    }

    /**
     * Adds "implicit" entries to the importmap.
     *
     * This recursively searches the dependencies of the given entry
     * (i.e. it looks for modules imported from other modules)
     * and adds them to the importmap.
     *
     * @param array<string, ImportMapEntry> $currentImportEntries
     *
     * @return array<string, ImportMapEntry>
     */
    private function addImplicitEntries(ImportMapEntry $entry, array $currentImportEntries, ImportMapEntries $rootEntries): array
    {
        // only process import dependencies for JS files
        if (ImportMapType::JS !== $entry->type) {
            return $currentImportEntries;
        }

        // remote packages aren't in the asset mapper & so don't have dependencies
        if ($entry->isRemotePackage()) {
            return $currentImportEntries;
        }

        if (!$asset = $this->findAsset($entry->path)) {
            // should only be possible at this point for root importmap.php entries
            throw new \InvalidArgumentException(sprintf('The asset "%s" mentioned in "importmap.php" cannot be found in any asset map paths.', $entry->path));
        }

        foreach ($asset->getJavaScriptImports() as $javaScriptImport) {
            $importName = $javaScriptImport->importName;

            if (isset($currentImportEntries[$importName])) {
                // entry already exists
                continue;
            }

            // check if this import requires an automatic importmap name
            if ($javaScriptImport->addImplicitlyToImportMap && $javaScriptImport->asset) {
                $nextEntry = new ImportMapEntry(
                    $importName,
                    path: $javaScriptImport->asset->logicalPath,
                    type: ImportMapType::tryFrom($javaScriptImport->asset->publicExtension) ?: ImportMapType::JS,
                    isEntrypoint: false,
                );
                $currentImportEntries[$importName] = $nextEntry;
            } else {
                $nextEntry = $this->findRootImportMapEntry($importName);
            }

            // unless there was some missing importmap entry, recurse
            if ($nextEntry) {
                $currentImportEntries = $this->addImplicitEntries($nextEntry, $currentImportEntries, $rootEntries);
            }
        }

        return $currentImportEntries;
    }

    /**
     * Given an importmap entry name, finds all the non-lazy module imports in its chain.
     *
     * @return array<string> The array of import names
     */
    private function findEagerEntrypointImports(string $entryName): array
    {
        $dumpedEntrypointPath = $this->assetsPathResolver->getPublicFilesystemPath().'/'.sprintf(self::ENTRYPOINT_CACHE_FILENAME_PATTERN, $entryName);
        if (is_file($dumpedEntrypointPath)) {
            return json_decode(file_get_contents($dumpedEntrypointPath), true, 512, \JSON_THROW_ON_ERROR);
        }

        $rootImportEntries = $this->importMapConfigReader->getEntries();
        if (!$rootImportEntries->has($entryName)) {
            throw new \InvalidArgumentException(sprintf('The entrypoint "%s" does not exist in "importmap.php".', $entryName));
        }

        if (!$rootImportEntries->get($entryName)->isEntrypoint) {
            throw new \InvalidArgumentException(sprintf('The entrypoint "%s" is not an entry point in "importmap.php". Set "entrypoint" => true to make it available as an entrypoint.', $entryName));
        }

        if ($rootImportEntries->get($entryName)->isRemotePackage()) {
            throw new \InvalidArgumentException(sprintf('The entrypoint "%s" is a remote package and cannot be used as an entrypoint.', $entryName));
        }

        $asset = $this->findAsset($rootImportEntries->get($entryName)->path);
        if (!$asset) {
            throw new \InvalidArgumentException(sprintf('The path "%s" of the entrypoint "%s" mentioned in "importmap.php" cannot be found in any asset map paths.', $rootImportEntries->get($entryName)->path, $entryName));
        }

        return $this->findEagerImports($asset);
    }

    private function findEagerImports(MappedAsset $asset): array
    {
        $dependencies = [];
        foreach ($asset->getJavaScriptImports() as $javaScriptImport) {
            if ($javaScriptImport->isLazy) {
                continue;
            }

            $dependencies[] = $javaScriptImport->importName;

            // the import is for a MappedAsset? Follow its imports!
            if ($javaScriptImport->asset) {
                $dependencies = array_merge($dependencies, $this->findEagerImports($javaScriptImport->asset));
            }
        }

        return $dependencies;
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

        if (str_starts_with($path, '.')) {
            $path = $this->importMapConfigReader->getRootDirectory().'/'.$path;
        }

        return $this->assetMapper->getAssetFromSourcePath($path);
    }
}
