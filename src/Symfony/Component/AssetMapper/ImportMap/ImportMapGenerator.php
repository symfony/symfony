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
use Symfony\Component\AssetMapper\CompiledAssetMapperConfigReader;
use Symfony\Component\AssetMapper\Exception\LogicException;
use Symfony\Component\AssetMapper\MappedAsset;

/**
 * Provides data needed to write the importmap & preloads.
 */
class ImportMapGenerator
{
    public const IMPORT_MAP_CACHE_FILENAME = 'importmap.json';
    public const ENTRYPOINT_CACHE_FILENAME_PATTERN = 'entrypoint.%s.json';

    public function __construct(
        private readonly AssetMapperInterface $assetMapper,
        private readonly CompiledAssetMapperConfigReader $compiledConfigReader,
        private readonly ImportMapConfigReader $importMapConfigReader,
    ) {
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
     * @param string[] $entrypointNames
     *
     * @return array<string, array{path: string, type: string, preload?: bool}>
     *
     * @internal
     */
    public function getImportMapData(array $entrypointNames): array
    {
        $rawImportMapData = $this->getRawImportMapData();
        $finalImportMapData = [];
        foreach ($entrypointNames as $entrypointName) {
            $entrypointImports = $this->findEagerEntrypointImports($entrypointName);
            // Entrypoint modules must be preloaded before their dependencies
            foreach ([$entrypointName, ...$entrypointImports] as $import) {
                if (isset($finalImportMapData[$import])) {
                    continue;
                }

                // Missing dependency - rely on browser or compilers to warn
                if (!isset($rawImportMapData[$import])) {
                    continue;
                }

                $finalImportMapData[$import] = $rawImportMapData[$import];
                $finalImportMapData[$import]['preload'] = true;
                unset($rawImportMapData[$import]);
            }
        }

        return array_merge($finalImportMapData, $rawImportMapData);
    }

    /**
     * @internal
     *
     * @return array<string, array{path: string, type: string}>
     */
    public function getRawImportMapData(): array
    {
        if ($this->compiledConfigReader->configExists(self::IMPORT_MAP_CACHE_FILENAME)) {
            return $this->compiledConfigReader->loadConfig(self::IMPORT_MAP_CACHE_FILENAME);
        }

        $allEntries = [];
        foreach ($this->importMapConfigReader->getEntries() as $rootEntry) {
            $allEntries[$rootEntry->importName] = $rootEntry;
            $allEntries = $this->addImplicitEntries($rootEntry, $allEntries);
        }

        $rawImportMapData = [];
        foreach ($allEntries as $entry) {
            $asset = $this->findAsset($entry->path);
            if (!$asset) {
                throw $this->createMissingImportMapAssetException($entry);
            }

            $path = $asset->publicPath;
            $data = ['path' => $path, 'type' => $entry->type->value];
            $rawImportMapData[$entry->importName] = $data;
        }

        return $rawImportMapData;
    }

    /**
     * Given an importmap entry name, finds all the non-lazy module imports in its chain.
     *
     * @internal
     *
     * @return array<string> The array of import names
     */
    public function findEagerEntrypointImports(string $entryName): array
    {
        if ($this->compiledConfigReader->configExists(sprintf(self::ENTRYPOINT_CACHE_FILENAME_PATTERN, $entryName))) {
            return $this->compiledConfigReader->loadConfig(sprintf(self::ENTRYPOINT_CACHE_FILENAME_PATTERN, $entryName));
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
    private function addImplicitEntries(ImportMapEntry $entry, array $currentImportEntries): array
    {
        // only process import dependencies for JS files
        if (ImportMapType::JS !== $entry->type) {
            return $currentImportEntries;
        }

        if (!$asset = $this->findAsset($entry->path)) {
            // should only be possible at this point for root importmap.php entries
            throw $this->createMissingImportMapAssetException($entry);
        }

        foreach ($asset->getJavaScriptImports() as $javaScriptImport) {
            $importName = $javaScriptImport->importName;

            if (isset($currentImportEntries[$importName])) {
                // entry already exists
                continue;
            }

            // check if this import requires an automatic importmap entry
            if ($javaScriptImport->addImplicitlyToImportMap) {
                if (!$importedAsset = $this->assetMapper->getAsset($javaScriptImport->assetLogicalPath)) {
                    // should not happen at this point, unless something added a bogus JavaScriptImport to this asset
                    throw new LogicException(sprintf('Cannot find imported JavaScript asset "%s" in asset mapper.', $javaScriptImport->assetLogicalPath));
                }

                $nextEntry = ImportMapEntry::createLocal(
                    $importName,
                    ImportMapType::tryFrom($importedAsset->publicExtension) ?: ImportMapType::JS,
                    $importedAsset->logicalPath,
                    false,
                );

                $currentImportEntries[$importName] = $nextEntry;
            } else {
                $nextEntry = $this->importMapConfigReader->findRootImportMapEntry($importName);
            }

            // unless there was some missing importmap entry, recurse
            if ($nextEntry) {
                $currentImportEntries = $this->addImplicitEntries($nextEntry, $currentImportEntries);
            }
        }

        return $currentImportEntries;
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

    /**
     * Finds recursively all the non-lazy modules imported by an asset.
     *
     * @return array<string> The array of deduplicated import names
     */
    private function findEagerImports(MappedAsset $asset): array
    {
        $dependencies = [];
        $queue = [$asset];

        while ($asset = array_shift($queue)) {
            foreach ($asset->getJavaScriptImports() as $javaScriptImport) {
                if ($javaScriptImport->isLazy) {
                    continue;
                }
                if (isset($dependencies[$javaScriptImport->importName])) {
                    continue;
                }
                $dependencies[$javaScriptImport->importName] = true;

                // Follow its imports!
                if (!$javaScriptAsset = $this->assetMapper->getAsset($javaScriptImport->assetLogicalPath)) {
                    // should not happen at this point, unless something added a bogus JavaScriptImport to this asset
                    throw new LogicException(sprintf('Cannot find JavaScript asset "%s" (imported in "%s") in asset mapper.', $javaScriptImport->assetLogicalPath, $asset->logicalPath));
                }
                $queue[] = $javaScriptAsset;
            }
        }

        return array_keys($dependencies);
    }

    private function createMissingImportMapAssetException(ImportMapEntry $entry): \InvalidArgumentException
    {
        if ($entry->isRemotePackage()) {
            if (!is_file($entry->path)) {
                throw new LogicException(sprintf('The "%s" vendor asset is missing. Try running the "importmap:install" command.', $entry->importName));
            }

            throw new LogicException(sprintf('The "%s" vendor file exists locally (%s), but cannot be found in any asset map paths. Be sure the assets vendor directory is an asset mapper path.', $entry->importName, $entry->path));
        }

        throw new LogicException(sprintf('The asset "%s" cannot be found in any asset map paths.', $entry->path));
    }
}
