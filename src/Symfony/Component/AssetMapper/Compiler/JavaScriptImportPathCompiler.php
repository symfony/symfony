<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper\Compiler;

use Psr\Log\LoggerInterface;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\AssetMapper\Compiler\Parser\JavaScriptImportScanner;
use Symfony\Component\AssetMapper\Exception\CircularAssetsException;
use Symfony\Component\AssetMapper\Exception\RuntimeException;
use Symfony\Component\AssetMapper\ImportMap\ImportMapConfigReader;
use Symfony\Component\AssetMapper\ImportMap\JavaScriptImport;
use Symfony\Component\AssetMapper\MappedAsset;
use Symfony\Component\Filesystem\Path;

/**
 * Resolves import paths in JS files.
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
final class JavaScriptImportPathCompiler implements AssetCompilerInterface
{
    public function __construct(
        private readonly ImportMapConfigReader $importMapConfigReader,
        private readonly string $missingImportMode = self::MISSING_IMPORT_WARN,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function compile(string $content, MappedAsset $asset, AssetMapperInterface $assetMapper): string
    {
        $shift = 0;

        foreach (JavaScriptImportScanner::scan($content) as [$offset, $importedModule, $isLazy]) {
            // we don't support absolute paths, so ignore completely
            if ('' === $importedModule || str_starts_with($importedModule, '/')) {
                continue;
            }

            $isRelativeImport = str_starts_with($importedModule, '.');
            if (!$isRelativeImport) {
                // URL or /absolute imports will also go here, but will be ignored
                $dependentAsset = $this->findAssetForBareImport($importedModule, $asset, $assetMapper);
            } else {
                $dependentAsset = $this->findAssetForRelativeImport($importedModule, $asset, $assetMapper);
            }

            if (!$dependentAsset) {
                continue;
            }

            // Ignore self-referencing import
            if ($dependentAsset->logicalPath === $asset->logicalPath) {
                continue;
            }

            // List as a JavaScript import.
            // This will cause the asset to be included in the importmap (for relative imports)
            // and will be used to generate the preloads in the importmap.
            $addToImportMap = $isRelativeImport;
            $asset->addJavaScriptImport(new JavaScriptImport(
                $addToImportMap ? $dependentAsset->publicPathWithoutDigest : $importedModule,
                $dependentAsset->logicalPath,
                $dependentAsset->sourcePath,
                $isLazy,
                $addToImportMap,
            ));

            if (!$addToImportMap) {
                // only (potentially) adjust for automatic relative imports
                continue;
            }

            // support possibility where the final public files have moved relative to each other
            $relativeImportPath = Path::makeRelative($dependentAsset->publicPathWithoutDigest, \dirname($asset->publicPathWithoutDigest));
            $relativeImportPath = $this->makeRelativeForJavaScript($relativeImportPath);

            if ($relativeImportPath !== $importedModule) {
                $content = substr_replace($content, $relativeImportPath, $offset + $shift, \strlen($importedModule));
                $shift += \strlen($relativeImportPath) - \strlen($importedModule);
            }
        }

        return $content;
    }

    public function supports(MappedAsset $asset): bool
    {
        return 'js' === $asset->publicExtension;
    }

    private function makeRelativeForJavaScript(string $path): string
    {
        if (str_starts_with($path, '../')) {
            return $path;
        }

        return './'.$path;
    }

    private function handleMissingImport(string $message, ?\Throwable $e = null): void
    {
        match ($this->missingImportMode) {
            AssetCompilerInterface::MISSING_IMPORT_IGNORE => null,
            AssetCompilerInterface::MISSING_IMPORT_WARN => $this->logger?->warning($message),
            AssetCompilerInterface::MISSING_IMPORT_STRICT => throw new RuntimeException($message, 0, $e),
        };
    }

    private function findAssetForBareImport(string $importedModule, MappedAsset $asset, AssetMapperInterface $assetMapper): ?MappedAsset
    {
        if (!$importMapEntry = $this->importMapConfigReader->findRootImportMapEntry($importedModule)) {
            // Bare names may resolve to a valid URL at runtime, so we don't warn about them in general.
            // But the browser can only load a CSS or JSON module by bare name through an importmap entry
            // (the experimental import-attributes `with { type: '...' }` syntax aside), so a missing bare
            // `.css`/`.json` import silently does nothing - warn the user at compile time.
            if (!$asset->isVendor
                && !str_contains($importedModule, '://')
                && (str_ends_with($lowerModule = strtolower($importedModule), '.css') || str_ends_with($lowerModule, '.json'))
            ) {
                $this->handleMissingImport(\sprintf('Unable to find asset "%s" imported from "%s". Add it to "importmap.php", e.g. via the "importmap:require" command.', $importedModule, $asset->sourcePath));
            }

            return null;
        }

        try {
            if ($dependentAsset = $assetMapper->getAsset($importMapEntry->path)) {
                return $dependentAsset;
            }

            return $assetMapper->getAssetFromSourcePath($this->importMapConfigReader->convertPathToFilesystemPath($importMapEntry->path));
        } catch (CircularAssetsException $exception) {
            return $exception->getIncompleteMappedAsset();
        }
    }

    private function findAssetForRelativeImport(string $importedModule, MappedAsset $asset, AssetMapperInterface $assetMapper): ?MappedAsset
    {
        try {
            $resolvedSourcePath = Path::join(\dirname($asset->sourcePath), $importedModule);
        } catch (RuntimeException $e) {
            // avoid warning about vendor imports - these are often comments
            if (!$asset->isVendor) {
                $this->handleMissingImport(\sprintf('Error processing import in "%s": ', $asset->sourcePath).$e->getMessage(), $e);
            }

            return null;
        }

        try {
            $dependentAsset = $assetMapper->getAssetFromSourcePath($resolvedSourcePath);
        } catch (CircularAssetsException $exception) {
            $dependentAsset = $exception->getIncompleteMappedAsset();
        }

        if ($dependentAsset) {
            return $dependentAsset;
        }

        // avoid warning about vendor imports - these are often comments
        if ($asset->isVendor) {
            return null;
        }

        $message = \sprintf('Unable to find asset "%s" imported from "%s".', $importedModule, $asset->sourcePath);

        if (is_file($resolvedSourcePath)) {
            $message .= \sprintf('The file "%s" exists, but it is not in a mapped asset path. Add it to the "paths" config.', $resolvedSourcePath);
        } else {
            try {
                if (null !== $assetMapper->getAssetFromSourcePath(\sprintf('%s.js', $resolvedSourcePath))) {
                    $message .= \sprintf(' Try adding ".js" to the end of the import - i.e. "%s.js".', $importedModule);
                }
            } catch (CircularAssetsException) {
                // avoid circular error if there is self-referencing import comments
            }
        }

        $this->handleMissingImport($message);

        return null;
    }
}
