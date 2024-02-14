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
use Symfony\Component\Filesystem\Path;
use Symfony\Component\VarExporter\VarExporter;

/**
 * Reads/Writes the importmap.php file and returns the list of entries.
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
class ImportMapConfigReader
{
    private ImportMapEntries $rootImportMapEntries;

    public function __construct(
        private readonly string $importMapConfigPath,
        private readonly RemotePackageStorage $remotePackageStorage,
    ) {
    }

    public function getEntries(): ImportMapEntries
    {
        if (isset($this->rootImportMapEntries)) {
            return $this->rootImportMapEntries;
        }

        $configPath = $this->importMapConfigPath;
        $importMapConfig = is_file($this->importMapConfigPath) ? (static fn () => include $configPath)() : [];

        $entries = new ImportMapEntries();
        foreach ($importMapConfig ?? [] as $importName => $data) {
            $validKeys = ['path', 'version', 'type', 'entrypoint', 'url', 'package_specifier', 'downloaded_to', 'preload'];
            if ($invalidKeys = array_diff(array_keys($data), $validKeys)) {
                throw new \InvalidArgumentException(sprintf('The following keys are not valid for the importmap entry "%s": "%s". Valid keys are: "%s".', $importName, implode('", "', $invalidKeys), implode('", "', $validKeys)));
            }

            // should solve itself when the config is written again
            if (isset($data['url'])) {
                trigger_deprecation('symfony/asset-mapper', '6.4', 'The "url" option is deprecated, use "version" instead.');
            }

            // should solve itself when the config is written again
            if (isset($data['downloaded_to'])) {
                trigger_deprecation('symfony/asset-mapper', '6.4', 'The "downloaded_to" option is deprecated and will be removed.');
                // remove deprecated downloaded_to
                unset($data['downloaded_to']);
            }

            // should solve itself when the config is written again
            if (isset($data['preload'])) {
                trigger_deprecation('symfony/asset-mapper', '6.4', 'The "preload" option is deprecated, preloading is automatically done.');
                // remove deprecated preload
                unset($data['preload']);
            }

            $type = isset($data['type']) ? ImportMapType::tryFrom($data['type']) : ImportMapType::JS;
            $isEntrypoint = $data['entrypoint'] ?? false;

            if (isset($data['path'])) {
                if (isset($data['version'])) {
                    throw new RuntimeException(sprintf('The importmap entry "%s" cannot have both a "path" and "version" option.', $importName));
                }
                if (isset($data['package_specifier'])) {
                    throw new RuntimeException(sprintf('The importmap entry "%s" cannot have both a "path" and "package_specifier" option.', $importName));
                }

                $entries->add(ImportMapEntry::createLocal($importName, $type, $data['path'], $isEntrypoint));

                continue;
            }

            $version = $data['version'] ?? null;
            if (null === $version && ($data['url'] ?? null)) {
                // BC layer for 6.3->6.4
                $version = $this->extractVersionFromLegacyUrl($data['url']);
            }

            if (null === $version) {
                throw new RuntimeException(sprintf('The importmap entry "%s" must have either a "path" or "version" option.', $importName));
            }

            $packageModuleSpecifier = $data['package_specifier'] ?? $importName;
            $entries->add($this->createRemoteEntry($importName, $type, $version, $packageModuleSpecifier, $isEntrypoint));
        }

        return $this->rootImportMapEntries = $entries;
    }

    public function writeEntries(ImportMapEntries $entries): void
    {
        $this->rootImportMapEntries = $entries;

        $importMapConfig = [];
        foreach ($entries as $entry) {
            $config = [];
            if ($entry->isRemotePackage()) {
                $config['version'] = $entry->version;
                if ($entry->packageModuleSpecifier !== $entry->importName) {
                    $config['package_specifier'] = $entry->packageModuleSpecifier;
                }
            } else {
                $config['path'] = $entry->path;
            }
            if (ImportMapType::JS !== $entry->type) {
                $config['type'] = $entry->type->value;
            }
            if ($entry->isEntrypoint) {
                $config['entrypoint'] = true;
            }

            $importMapConfig[$entry->importName] = $config;
        }

        $map = class_exists(VarExporter::class) ? VarExporter::export($importMapConfig) : var_export($importMapConfig, true);
        file_put_contents($this->importMapConfigPath, <<<EOF
        <?php

        /**
         * Returns the importmap for this application.
         *
         * - "path" is a path inside the asset mapper system. Use the
         *     "debug:asset-map" command to see the full list of paths.
         *
         * - "entrypoint" (JavaScript only) set to true for any module that will
         *     be used as an "entrypoint" (and passed to the importmap() Twig function).
         *
         * The "importmap:require" command can be used to add new entries to this file.
         */
        return $map;

        EOF);
    }

    public function findRootImportMapEntry(string $moduleName): ?ImportMapEntry
    {
        $entries = $this->getEntries();

        return $entries->has($moduleName) ? $entries->get($moduleName) : null;
    }

    public function createRemoteEntry(string $importName, ImportMapType $type, string $version, string $packageModuleSpecifier, bool $isEntrypoint): ImportMapEntry
    {
        $path = $this->remotePackageStorage->getDownloadPath($packageModuleSpecifier, $type);

        return ImportMapEntry::createRemote($importName, $type, $path, $version, $packageModuleSpecifier, $isEntrypoint);
    }

    /**
     * Converts the "path" string from an importmap entry to the filesystem path.
     *
     * The path may already be a filesystem path. But if it starts with ".",
     * then the path is relative and the root directory is prepended.
     */
    public function convertPathToFilesystemPath(string $path): string
    {
        if (!str_starts_with($path, '.')) {
            return $path;
        }

        return Path::join($this->getRootDirectory(), $path);
    }

    /**
     * Converts a filesystem path to a relative path that can be used in the importmap.
     *
     * If no relative path could be created - e.g. because the path is not in
     * the same directory/subdirectory as the root importmap.php file - null is returned.
     */
    public function convertFilesystemPathToPath(string $filesystemPath): ?string
    {
        $rootImportMapDir = realpath($this->getRootDirectory());
        $filesystemPath = realpath($filesystemPath);
        if (!str_starts_with($filesystemPath, $rootImportMapDir)) {
            return null;
        }

        // remove the root directory, prepend "./" & normalize slashes
        return './'.str_replace('\\', '/', substr($filesystemPath, \strlen($rootImportMapDir) + 1));
    }

    private function getRootDirectory(): string
    {
        return \dirname($this->importMapConfigPath);
    }

    private function extractVersionFromLegacyUrl(string $url): ?string
    {
        // URL pattern https://ga.jspm.io/npm:bootstrap@5.3.2/dist/js/bootstrap.esm.js
        if (false === $lastAt = strrpos($url, '@')) {
            return null;
        }

        $nextSlash = strpos($url, '/', $lastAt);
        if (false === $nextSlash) {
            return null;
        }

        return substr($url, $lastAt + 1, $nextSlash - $lastAt - 1);
    }
}
