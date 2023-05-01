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
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\VarExporter\VarExporter;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @experimental
 *
 * @author Kévin Dunglas <kevin@dunglas.dev>
 * @author Ryan Weaver <ryan@symfonycasts.com>
 * @final
 */
class ImportMapManager
{
    public const PROVIDER_JSPM = 'jspm';
    public const PROVIDER_JSPM_SYSTEM = 'jspm.system';
    public const PROVIDER_SKYPACK = 'skypack';
    public const PROVIDER_JSDELIVR = 'jsdelivr';
    public const PROVIDER_UNPKG = 'unpkg';
    public const PROVIDERS = [
        self::PROVIDER_JSPM,
        self::PROVIDER_JSPM_SYSTEM,
        self::PROVIDER_SKYPACK,
        self::PROVIDER_JSDELIVR,
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

    private array $importMapEntries;
    private array $modulesToPreload;
    private string $json;

    public function __construct(
        private readonly AssetMapperInterface $assetMapper,
        private readonly string $importMapConfigPath,
        private readonly string $vendorDir,
        private readonly string $provider = self::PROVIDER_JSPM,
        private ?HttpClientInterface $httpClient = null,
    ) {
        $this->httpClient = $httpClient ?? HttpClient::create(['base_uri' => 'https://api.jspm.io/']);
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
        // https://regex101.com/r/58bl9L/1
        $regex = '/(?:(?P<registry>[^:\n]+):)?(?P<package>[^@\n]+)(?:@(?P<version>[^\s\n]+))?/';

        return preg_match($regex, $packageName, $matches) ? $matches : null;
    }

    private function buildImportMapJson(): void
    {
        if (isset($this->json)) {
            return;
        }

        $dumpedPath = $this->assetMapper->getPublicAssetsFilesystemPath().'/'.self::IMPORT_MAP_FILE_NAME;
        if (file_exists($dumpedPath)) {
            $this->json = file_get_contents($dumpedPath);

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
     * @param string[] $packagesToRemove
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
     * @param PackageRequireOptions[] $packagesToRequire
     * @param array<string, ImportMapEntry> $importMapEntries
     */
    private function requirePackages(array $packagesToRequire, array &$importMapEntries): array
    {
        if (!$packagesToRequire) {
            return [];
        }

        $installData = [];
        $packageRequiresByName = [];
        foreach ($packagesToRequire as $requireOptions) {
            $constraint = $requireOptions->packageName;
            if (null !== $requireOptions->versionConstraint) {
                $constraint .= '@' . $requireOptions->versionConstraint;
            }
            if (null !== $requireOptions->registryName) {
                $constraint = sprintf('%s:%s', $requireOptions->registryName, $constraint);
            }
            $installData[] = $constraint;
            $packageRequiresByName[$requireOptions->packageName] = $requireOptions;
        }

        $json = [
            'install' => $installData,
            'flattenScope' => true,
            // always grab production-ready assets
            'env' => ['browser', 'module', 'production'],
        ];
        if (self::PROVIDER_JSPM !== $this->provider) {
            $json['provider'] = $this->provider;
        }

        $response = $this->httpClient->request('POST', 'generate', [
            'json' => $json,
        ]);

        if (200 !== $response->getStatusCode()) {
            $data = $response->toArray(false);

            if (isset($data['error'])) {
                throw new \RuntimeException(sprintf('Error requiring JavaScript package: "%s"', $data['error']));
            }

            // Throws the original HttpClient exception
            $response->getHeaders();
        }

        // if we're requiring just one package, in case it has any peer deps, match the preload
        $defaultPreload = 1 === count($packagesToRequire) ? $packagesToRequire[0]->preload : false;

        $addedEntries = [];
        foreach ($response->toArray()['map']['imports'] as $packageName => $url) {
            $requireOptions = $packageRequiresByName[$packageName] ?? null;
            $importName = $requireOptions && $requireOptions->importName ? $requireOptions->importName : $packageName;
            $preload = $requireOptions ? $requireOptions->preload : $defaultPreload;
            $download = $requireOptions ? $requireOptions->download : false;
            $path = null;

            if ($download) {
                $vendorPath = $this->vendorDir.'/'.$packageName.'.js';

                @mkdir(\dirname($vendorPath), 0777, true);
                file_put_contents($vendorPath, $this->httpClient->request('GET', $url)->getContent());

                $mappedAsset = $this->assetMapper->getAssetFromSourcePath($vendorPath);
                if (null === $mappedAsset) {
                    unlink($vendorPath);

                    throw new \LogicException(sprintf('The package was downloaded to "%s", but this path does not appear to be in any of your asset paths.', $vendorPath));
                }
                $path = $mappedAsset->logicalPath;
            }

            $newEntry = new ImportMapEntry($importName, $path, $url, $download, $preload);
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

        if (is_file($asset->getSourcePath())) {
            @unlink($asset->getSourcePath());
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
                $config[$entry->isDownloaded ? 'downloaded_to' : 'path'] = $entry->path;
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
        file_put_contents($this->importMapConfigPath, "<?php\n\nreturn {$map};\n");
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
                $asset = $this->assetMapper->getAsset($entryOptions->path);
                if (!$asset) {
                    throw new \InvalidArgumentException(sprintf('The asset "%s" mentioned in "%s" cannot be found in any asset map paths.', $entryOptions->path, basename($this->importMapConfigPath)));
                }
                $path = $asset->getPublicPath();
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

            $dependencyImportMapEntries = array_map(function (AssetDependency $dependency) {
                return new ImportMapEntry(
                    $dependency->asset->getPublicPathWithoutDigest(),
                    $dependency->asset->logicalPath,
                    preload: !$dependency->isLazy,
                );
            }, $dependencies);
            $imports = array_merge($imports, $this->convertEntriesToImports($dependencyImportMapEntries));
        }

        return $imports;
    }
}
