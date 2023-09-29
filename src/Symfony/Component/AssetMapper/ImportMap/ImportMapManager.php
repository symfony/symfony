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
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Kévin Dunglas <kevin@dunglas.dev>
 * @author Ryan Weaver <ryan@symfonycasts.com>
 *
 * @final
 */
class ImportMapManager
{
    public const PROVIDER_JSPM = 'jspm';
    public const PROVIDER_JSPM_SYSTEM = 'jspm.system';
    public const PROVIDER_SKYPACK = 'skypack';
    public const PROVIDER_JSDELIVR = 'jsdelivr';
    public const PROVIDER_JSDELIVR_ESM = 'jsdelivr.esm';
    public const PROVIDER_UNPKG = 'unpkg';
    public const PROVIDERS = [
        self::PROVIDER_JSPM,
        self::PROVIDER_JSPM_SYSTEM,
        self::PROVIDER_SKYPACK,
        self::PROVIDER_JSDELIVR,
        self::PROVIDER_JSDELIVR_ESM,
        self::PROVIDER_UNPKG,
    ];

    public const POLYFILL_URL = 'https://ga.jspm.io/npm:es-module-shims@1.7.2/dist/es-module-shims.js';

    /**
     * @see https://regex101.com/r/2cR9Rh/1
     *
     * Partially based on https://github.com/dword-design/package-name-regex
     */
    private const PACKAGE_PATTERN = '/^(?:https?:\/\/[\w\.-]+\/)?(?:(?<registry>\w+):)?(?<package>(?:@[a-z0-9-~][a-z0-9-._~]*\/)?[a-z0-9-~][a-z0-9-._~]*)(?:@(?<version>[\w\._-]+))?(?:(?<subpath>\/.*))?$/';
    public const IMPORT_MAP_CACHE_FILENAME = 'importmap.json';
    public const ENTRYPOINT_CACHE_FILENAME_PATTERN = 'entrypoint.%s.json';

    private readonly HttpClientInterface $httpClient;

    public function __construct(
        private readonly AssetMapperInterface $assetMapper,
        private readonly PublicAssetsPathResolverInterface $assetsPathResolver,
        private readonly ImportMapConfigReader $importMapConfigReader,
        private readonly string $vendorDir,
        private readonly PackageResolverInterface $resolver,
        HttpClientInterface $httpClient = null,
    ) {
        $this->httpClient = $httpClient ?? HttpClient::create();
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
     * Downloads all missing downloaded packages.
     *
     * @return string[] The downloaded packages
     */
    public function downloadMissingPackages(): array
    {
        $entries = $this->importMapConfigReader->getEntries();
        $downloadedPackages = [];

        foreach ($entries as $entry) {
            if (!$entry->isDownloaded || $this->findAsset($entry->path)) {
                continue;
            }

            $this->downloadPackage(
                $entry->importName,
                $this->httpClient->request('GET', $entry->url)->getContent(),
                self::getImportMapTypeFromFilename($entry->url),
            );

            $downloadedPackages[] = $entry->importName;
        }

        return $downloadedPackages;
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
                    if ($entry->isDownloaded) {
                        throw new \InvalidArgumentException(sprintf('The "%s" downloaded asset is missing. Run "php bin/console importmap:install".', $entry->path));
                    }

                    throw new \InvalidArgumentException(sprintf('The asset "%s" cannot be found in any asset map paths.', $entry->path));
                }

                $path = $asset->publicPath;
            } else {
                $path = $entry->url;
            }

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
        // https://regex101.com/r/MDz0bN/1
        $regex = '/(?:(?P<registry>[^:\n]+):)?((?P<package>@?[^=@\n]+))(?:@(?P<version>[^=\s\n]+))?(?:=(?P<alias>[^\s\n]+))?/';

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
                if (null === $entry->url || (0 !== \count($packagesToUpdate) && !\in_array($importName, $packagesToUpdate, true))) {
                    continue;
                }

                // assume the import name === package name, unless we can parse
                // the true package name from the URL
                $packageName = $importName;
                $registry = null;

                // try to grab the package name & jspm "registry" from the URL
                if (str_starts_with($entry->url, 'https://ga.jspm.io') && 1 === preg_match(self::PACKAGE_PATTERN, $entry->url, $matches)) {
                    $packageName = $matches['package'];
                    $registry = $matches['registry'] ?? null;
                }

                $packagesToRequire[] = new PackageRequireOptions(
                    $packageName,
                    null,
                    $entry->isDownloaded,
                    $importName,
                    $registry,
                );

                // remove it: then it will be re-added
                $this->cleanupPackageFiles($entry);
                $currentEntries->remove($importName);
            }
        }

        $newEntries = $this->requirePackages($packagesToRequire, $currentEntries);
        $this->importMapConfigReader->writeEntries($currentEntries);

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
            $path = null;
            $type = self::getImportMapTypeFromFilename($resolvedPackage->url);
            if ($resolvedPackage->requireOptions->download) {
                if (null === $resolvedPackage->content) {
                    throw new \LogicException(sprintf('The contents of package "%s" were not downloaded.', $resolvedPackage->requireOptions->packageName));
                }

                $path = $this->downloadPackage($importName, $resolvedPackage->content, $type);
            }

            $newEntry = new ImportMapEntry(
                $importName,
                path: $path,
                url: $resolvedPackage->url,
                isDownloaded: $resolvedPackage->requireOptions->download,
                type: $type,
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
        if ($entry->isRemote()) {
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

    private function downloadPackage(string $packageName, string $packageContents, ImportMapType $importMapType): string
    {
        $vendorPath = $this->vendorDir.'/'.$packageName;
        // add an extension of there is none
        if (!str_contains($packageName, '.')) {
            $vendorPath .= '.'.$importMapType->value;
        }

        @mkdir(\dirname($vendorPath), 0777, true);
        file_put_contents($vendorPath, $packageContents);

        if (null === $mappedAsset = $this->assetMapper->getAssetFromSourcePath($vendorPath)) {
            unlink($vendorPath);

            throw new \LogicException(sprintf('The package was downloaded to "%s", but this path does not appear to be in any of your asset paths.', $vendorPath));
        }

        return $mappedAsset->logicalPath;
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

        if ($rootImportEntries->get($entryName)->isRemote()) {
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
