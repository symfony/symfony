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
    /**
     * @see https://regex101.com/r/1iBAIb/2
     */
    private const IMPORT_PATTERN = '/
            ^(?:\/\/.*)                     # Lines that start with comments
        |
            (?:
                \'(?:[^\'\\\\\n]|\\\\.)*+\'   # Strings enclosed in single quotes
            |
                "(?:[^"\\\\\n]|\\\\.)*+"      # Strings enclosed in double quotes
            )
        |
            (?:                            # Import statements (script captured)
                import\s*
                    (?:
                        (?:\*\s*as\s+\w+|\s+[\w\s{},*]+)
                        \s*from\s*
                    )?
            |
                \bimport\(
            )
            \s*[\'"`](\.\/[^\'"`\n]++|(\.\.\/)*+[^\'"`\n]++)[\'"`]\s*[;\)]
        ?
    /mxu';

    public function __construct(
        private readonly ImportMapConfigReader $importMapConfigReader,
        private readonly string $missingImportMode = self::MISSING_IMPORT_WARN,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function compile(string $content, MappedAsset $asset, AssetMapperInterface $assetMapper): string
    {
        return preg_replace_callback(self::IMPORT_PATTERN, function ($matches) use ($asset, $assetMapper, $content) {
            $fullImportString = $matches[0][0];

            // Ignore matches that did not capture import statements
            if (!isset($matches[1][0])) {
                return $fullImportString;
            }

            if ($this->isCommentedOut($matches[0][1], $content)) {
                return $fullImportString;
            }

            $importedModule = $matches[1][0];

            // we don't support absolute paths, so ignore completely
            if (str_starts_with($importedModule, '/')) {
                return $fullImportString;
            }

            $isRelativeImport = str_starts_with($importedModule, '.');
            if (!$isRelativeImport) {
                // URL or /absolute imports will also go here, but will be ignored
                $dependentAsset = $this->findAssetForBareImport($importedModule, $assetMapper);
            } else {
                $dependentAsset = $this->findAssetForRelativeImport($importedModule, $asset, $assetMapper);
            }

            if (!$dependentAsset) {
                return $fullImportString;
            }

            // Ignore self-referencing import
            if ($dependentAsset->logicalPath === $asset->logicalPath) {
                return $fullImportString;
            }

            // List as a JavaScript import.
            // This will cause the asset to be included in the importmap (for relative imports)
            // and will be used to generate the preloads in the importmap.
            $isLazy = str_contains($fullImportString, 'import(');
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
                return $fullImportString;
            }

            // support possibility where the final public files have moved relative to each other
            $relativeImportPath = Path::makeRelative($dependentAsset->publicPathWithoutDigest, \dirname($asset->publicPathWithoutDigest));
            $relativeImportPath = $this->makeRelativeForJavaScript($relativeImportPath);

            return str_replace($importedModule, $relativeImportPath, $fullImportString);
        }, $content, -1, $count, \PREG_OFFSET_CAPTURE) ?? throw new RuntimeException(\sprintf('Failed to compile JavaScript import paths in "%s". Error: "%s".', $asset->sourcePath, preg_last_error_msg()));
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

    /**
     * Simple check for the most common types of comments.
     *
     * This is not a full parser, but should be good enough for most cases.
     */
    private function isCommentedOut(mixed $offsetStart, string $fullContent): bool
    {
        $lineStart = strrpos($fullContent, "\n", $offsetStart - \strlen($fullContent));
        $lineContentBeforeImport = substr($fullContent, $lineStart, $offsetStart - $lineStart);
        $firstTwoChars = substr(ltrim($lineContentBeforeImport), 0, 2);
        if ('//' === $firstTwoChars) {
            return true;
        }

        if ('/*' === $firstTwoChars) {
            $commentEnd = strpos($fullContent, '*/', $lineStart);
            // if we can't find the end comment, be cautious: assume this is not a comment
            if (false === $commentEnd) {
                return false;
            }

            return $offsetStart < $commentEnd;
        }

        return false;
    }

    private function findAssetForBareImport(string $importedModule, AssetMapperInterface $assetMapper): ?MappedAsset
    {
        if (!$importMapEntry = $this->importMapConfigReader->findRootImportMapEntry($importedModule)) {
            // don't warn on missing non-relative (bare) imports: these could be valid URLs

            return null;
        }

        try {
            if ($asset = $assetMapper->getAsset($importMapEntry->path)) {
                return $asset;
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
