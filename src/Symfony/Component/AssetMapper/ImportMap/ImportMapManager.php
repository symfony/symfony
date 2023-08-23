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

use Symfony\Component\AssetMapper\AssetDependency;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\AssetMapper\ImportMap\Resolver\PackageResolverInterface;
use Symfony\Component\AssetMapper\Path\PublicAssetsPathResolverInterface;
use Symfony\Component\VarExporter\VarExporter;

/**
 * @experimental
 *
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
    public const IMPORT_MAP_FILE_NAME = 'importmap.json';
    public const IMPORT_MAP_PRELOAD_FILE_NAME = 'importmap.preload.json';

    private array $importMapEntries;
    private array $modulesToPreload;
    private string $json;

    public function __construct(
        private readonly AssetMapperInterface $assetMapper,
        private readonly PublicAssetsPathResolverInterface $assetsPathResolver,
        private readonly string $importMapConfigPath,
        private readonly string $vendorDir,
        private readonly PackageResolverInterface $resolver,
    ) {
    }

    public function getModulesToPreload(): array
    {
        $this->buildImportMapJson();

        return $this->modulesToPreload;
    }

    public function getImportMapJson(): string
    {
        $this->buildImportMapJson();

        return $this->json;
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
        return $this->updateImportMapConfig(false, $packages, []);
    }

    /**
     * Removes packages.
     *
     * @param string[] $packages
     */
    public function remove(array $packages): void
    {
        $this->updateImportMapConfig(false, [], $packages);
    }

    /**
     * Updates all existing packages to the latest version.
     */
    public function update(): array
    {
        return $this->updateImportMapConfig(true, [], []);
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

    private function buildImportMapJson(): void
    {
        if (isset($this->json)) {
            return;
        }

        $dumpedImportMapPath = $this->assetsPathResolver->getPublicFilesystemPath().'/'.self::IMPORT_MAP_FILE_NAME;
        $dumpedModulePreloadPath = $this->assetsPathResolver->getPublicFilesystemPath().'/'.self::IMPORT_MAP_PRELOAD_FILE_NAME;
        if (is_file($dumpedImportMapPath) && is_file($dumpedModulePreloadPath)) {
            $this->json = file_get_contents($dumpedImportMapPath);
            $this->modulesToPreload = json_decode(file_get_contents($dumpedModulePreloadPath), true, 512, \JSON_THROW_ON_ERROR);

            return;
        }

        $entries = $this->loadImportMapEntries();
        $this->modulesToPreload = [];

        $imports = $this->convertEntriesToImports($entries);

        $importmap['imports'] = $imports;

        // Use JSON_UNESCAPED_SLASHES | JSON_HEX_TAG to prevent XSS
        $this->json = json_encode($importmap, \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_HEX_TAG);
    }

    /**
     * @param PackageRequireOptions[] $packagesToRequire
     * @param string[]                $packagesToRemove
     *
     * @return ImportMapEntry[]
     */
    private function updateImportMapConfig(bool $update, array $packagesToRequire, array $packagesToRemove): array
    {
        $currentEntries = $this->loadImportMapEntries();

        foreach ($packagesToRemove as $packageName) {
            if (!isset($currentEntries[$packageName])) {
                throw new \InvalidArgumentException(sprintf('Package "%s" listed for removal was not found in "%s".', $packageName, basename($this->importMapConfigPath)));
            }

            $this->cleanupPackageFiles($currentEntries[$packageName]);
            unset($currentEntries[$packageName]);
        }

        if ($update) {
            foreach ($currentEntries as $importName => $entry) {
                if (null === $entry->url) {
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
                    $entry->preload,
                    $importName,
                    $registry,
                );

                // remove it: then it will be re-added
                $this->cleanupPackageFiles($entry);
                unset($currentEntries[$importName]);
            }
        }

        $newEntries = $this->requirePackages($packagesToRequire, $currentEntries);
        $this->writeImportMapConfig($currentEntries);

        return $newEntries;
    }

    /**
     * Gets information about (and optionally downloads) the packages & updates the entries.
     *
     * Returns an array of the entries that were added.
     *
     * @param PackageRequireOptions[]       $packagesToRequire
     * @param array<string, ImportMapEntry> $importMapEntries
     */
    private function requirePackages(array $packagesToRequire, array &$importMapEntries): array
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

            $newEntry = new ImportMapEntry(
                $requireOptions->packageName,
                $requireOptions->path,
                $requireOptions->preload,
            );
            $importMapEntries[$requireOptions->packageName] = $newEntry;
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
            if ($resolvedPackage->requireOptions->download) {
                if (null === $resolvedPackage->content) {
                    throw new \LogicException(sprintf('The contents of package "%s" were not downloaded.', $resolvedPackage->requireOptions->packageName));
                }

                $path = $this->downloadPackage($importName, $resolvedPackage->content);
            }

            $newEntry = new ImportMapEntry(
                $importName,
                $path,
                $resolvedPackage->url,
                $resolvedPackage->requireOptions->download,
                $resolvedPackage->requireOptions->preload,
            );
            $importMapEntries[$importName] = $newEntry;
            $addedEntries[] = $newEntry;
        }

        return $addedEntries;
    }

    private function cleanupPackageFiles(ImportMapEntry $entry): void
    {
        if (null === $entry->path) {
            return;
        }

        $asset = $this->assetMapper->getAsset($entry->path);

        if (is_file($asset->sourcePath)) {
            @unlink($asset->sourcePath);
        }
    }

    /**
     * @return array<string, ImportMapEntry>
     */
    private function loadImportMapEntries(): array
    {
        if (isset($this->importMapEntries)) {
            return $this->importMapEntries;
        }

        $path = $this->importMapConfigPath;
        $importMapConfig = is_file($path) ? (static fn () => include $path)() : [];

        $entries = [];
        foreach ($importMapConfig ?? [] as $importName => $data) {
            $entries[$importName] = new ImportMapEntry(
                $importName,
                path: $data['path'] ?? $data['downloaded_to'] ?? null,
                url: $data['url'] ?? null,
                isDownloaded: isset($data['downloaded_to']),
                preload: $data['preload'] ?? false,
            );
        }

        return $this->importMapEntries = $entries;
    }

    /**
     * @param ImportMapEntry[] $entries
     */
    private function writeImportMapConfig(array $entries): void
    {
        $this->importMapEntries = $entries;
        unset($this->modulesToPreload);
        unset($this->json);

        $importMapConfig = [];
        foreach ($entries as $entry) {
            $config = [];
            if ($entry->path) {
                $path = $entry->path;
                // if the path is an absolute path, convert it to an asset path
                if (is_file($path)) {
                    if (null === $asset = $this->assetMapper->getAssetFromSourcePath($path)) {
                        throw new \LogicException(sprintf('The "%s" importmap entry contains the path "%s" but it does not appear to be in any of your asset paths.', $entry->importName, $path));
                    }
                    $path = $asset->logicalPath;
                }
                $config[$entry->isDownloaded ? 'downloaded_to' : 'path'] = $path;
            }
            if ($entry->url) {
                $config['url'] = $entry->url;
            }
            if ($entry->preload) {
                $config['preload'] = $entry->preload;
            }
            $importMapConfig[$entry->importName] = $config;
        }

        $map = class_exists(VarExporter::class) ? VarExporter::export($importMapConfig) : var_export($importMapConfig, true);
        file_put_contents($this->importMapConfigPath, <<<EOF
        <?php

        /**
         * Returns the import map for this application.
         *
         * - "path" is a path inside the asset mapper system. Use the
         *     "debug:asset-map" command to see the full list of paths.
         *
         * - "preload" set to true for any modules that are loaded on the initial
         *     page load to help the browser download them earlier.
         *
         * The "importmap:require" command can be used to add new entries to this file.
         *
         * This file has been auto-generated by the importmap commands.
         */
        return $map;

        EOF);
    }

    /**
     * @param ImportMapEntry[] $entries
     */
    private function convertEntriesToImports(array $entries): array
    {
        $imports = [];
        foreach ($entries as $entryOptions) {
            // while processing dependencies, we may recurse: no reason to calculate the same entry twice
            if (isset($imports[$entryOptions->importName])) {
                continue;
            }

            $dependencies = [];

            if (null !== $entryOptions->path) {
                if (!$asset = $this->assetMapper->getAsset($entryOptions->path)) {
                    if ($entryOptions->isDownloaded) {
                        throw new \InvalidArgumentException(sprintf('The "%s" downloaded asset is missing. Run "php bin/console importmap:require "%s" --download".', $entryOptions->path, $entryOptions->importName));
                    }

                    throw new \InvalidArgumentException(sprintf('The asset "%s" mentioned in "%s" cannot be found in any asset map paths.', $entryOptions->path, basename($this->importMapConfigPath)));
                }
                $path = $asset->publicPath;
                $dependencies = $asset->getDependencies();
            } elseif (null !== $entryOptions->url) {
                $path = $entryOptions->url;
            } else {
                throw new \InvalidArgumentException(sprintf('The package "%s" mentioned in "%s" must have a "path" or "url" key.', $entryOptions->importName, basename($this->importMapConfigPath)));
            }

            $imports[$entryOptions->importName] = $path;

            if ($entryOptions->preload ?? false) {
                $this->modulesToPreload[] = $path;
            }

            $dependencyImportMapEntries = array_map(function (AssetDependency $dependency) use ($entryOptions) {
                return new ImportMapEntry(
                    $dependency->asset->publicPathWithoutDigest,
                    $dependency->asset->logicalPath,
                    preload: $entryOptions->preload && !$dependency->isLazy,
                );
            }, $dependencies);
            $imports = array_merge($imports, $this->convertEntriesToImports($dependencyImportMapEntries));
        }

        return $imports;
    }

    private function downloadPackage(string $packageName, string $packageContents): string
    {
        $vendorPath = $this->vendorDir.'/'.$packageName.'.js';

        @mkdir(\dirname($vendorPath), 0777, true);
        file_put_contents($vendorPath, $packageContents);

        if (null === $mappedAsset = $this->assetMapper->getAssetFromSourcePath($vendorPath)) {
            unlink($vendorPath);

            throw new \LogicException(sprintf('The package was downloaded to "%s", but this path does not appear to be in any of your asset paths.', $vendorPath));
        }

        return $mappedAsset->logicalPath;
    }
}
