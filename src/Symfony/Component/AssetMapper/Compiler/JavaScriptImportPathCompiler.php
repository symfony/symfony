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
use Symfony\Component\AssetMapper\ImportMap\ImportMapManager;
use Symfony\Component\AssetMapper\ImportMap\JavaScriptImport;
use Symfony\Component\AssetMapper\MappedAsset;

/**
 * Resolves import paths in JS files.
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
final class JavaScriptImportPathCompiler implements AssetCompilerInterface
{
    use AssetCompilerPathResolverTrait;

    // https://regex101.com/r/5Q38tj/1
    private const IMPORT_PATTERN = '/(?:import\s+(?:(?:\*\s+as\s+\w+|[\w\s{},*]+)\s+from\s+)?|\bimport\()\s*[\'"`](\.\/[^\'"`]+|(\.\.\/)*[^\'"`]+)[\'"`]\s*[;\)]?/m';

    public function __construct(
        private readonly ImportMapManager $importMapManager,
        private readonly string $missingImportMode = self::MISSING_IMPORT_WARN,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function compile(string $content, MappedAsset $asset, AssetMapperInterface $assetMapper): string
    {
        return preg_replace_callback(self::IMPORT_PATTERN, function ($matches) use ($asset, $assetMapper, $content) {
            $fullImportString = $matches[0][0];

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

            // List as a JavaScript import.
            // This will cause the asset to be included in the importmap (for relative imports)
            // and will be used to generate the preloads in the importmap.
            $isLazy = str_contains($fullImportString, 'import(');
            $addToImportMap = $isRelativeImport && $dependentAsset;
            $asset->addJavaScriptImport(new JavaScriptImport(
                $addToImportMap ? $dependentAsset->publicPathWithoutDigest : $importedModule,
                $isLazy,
                $dependentAsset,
                $addToImportMap,
            ));

            if (!$addToImportMap) {
                // only (potentially) adjust for automatic relative imports
                return $fullImportString;
            }

            // support possibility where the final public files have moved relative to each other
            $relativeImportPath = $this->createRelativePath($asset->publicPathWithoutDigest, $dependentAsset->publicPathWithoutDigest);
            $relativeImportPath = $this->makeRelativeForJavaScript($relativeImportPath);

            return str_replace($importedModule, $relativeImportPath, $fullImportString);
        }, $content, -1, $count, \PREG_OFFSET_CAPTURE);
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

    private function handleMissingImport(string $message, \Throwable $e = null): void
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
        if (!$importMapEntry = $this->importMapManager->findRootImportMapEntry($importedModule)) {
            // don't warn on missing non-relative (bare) imports: these could be valid URLs

            return null;
        }

        // remote entries have no MappedAsset
        if ($importMapEntry->isRemote()) {
            return null;
        }

        return $assetMapper->getAsset($importMapEntry->path);
    }

    private function findAssetForRelativeImport(string $importedModule, MappedAsset $asset, AssetMapperInterface $assetMapper): ?MappedAsset
    {
        try {
            $resolvedPath = $this->resolvePath(\dirname($asset->logicalPath), $importedModule);
        } catch (RuntimeException $e) {
            $this->handleMissingImport(sprintf('Error processing import in "%s": ', $asset->sourcePath).$e->getMessage(), $e);

            return null;
        }

        $dependentAsset = $assetMapper->getAsset($resolvedPath);

        if ($dependentAsset) {
            return $dependentAsset;
        }

        $message = sprintf('Unable to find asset "%s" imported from "%s".', $importedModule, $asset->sourcePath);

        try {
            if (null !== $assetMapper->getAsset(sprintf('%s.js', $resolvedPath))) {
                $message .= sprintf(' Try adding ".js" to the end of the import - i.e. "%s.js".', $importedModule);
            }
        } catch (CircularAssetsException) {
            // avoid circular error if there is self-referencing import comments
        }

        $this->handleMissingImport($message);

        return null;
    }
}
