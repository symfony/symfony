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
use Psr\Log\NullLogger;
use Symfony\Component\AssetMapper\AssetDependency;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\AssetMapper\Exception\CircularAssetsException;
use Symfony\Component\AssetMapper\Exception\RuntimeException;
use Symfony\Component\AssetMapper\MappedAsset;

/**
 * Resolves import paths in JS files.
 *
 * @experimental
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
final class JavaScriptImportPathCompiler implements AssetCompilerInterface
{
    use AssetCompilerPathResolverTrait;

    private readonly LoggerInterface $logger;

    // https://regex101.com/r/VFdR4H/1
    private const IMPORT_PATTERN = '/(?:import\s+(?:(?:\*\s+as\s+\w+|[\w\s{},*]+)\s+from\s+)?|\bimport\()\s*[\'"`](\.\/[^\'"`]+|(\.\.\/)+[^\'"`]+)[\'"`]\s*[;\)]?/m';

    public function __construct(
        private readonly string $missingImportMode = self::MISSING_IMPORT_WARN,
        LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function compile(string $content, MappedAsset $asset, AssetMapperInterface $assetMapper): string
    {
        return preg_replace_callback(self::IMPORT_PATTERN, function ($matches) use ($asset, $assetMapper) {
            try {
                $resolvedPath = $this->resolvePath(\dirname($asset->logicalPath), $matches[1]);
            } catch (RuntimeException $e) {
                $this->handleMissingImport(sprintf('Error processing import in "%s": ', $asset->sourcePath).$e->getMessage(), $e);

                return $matches[0];
            }

            $dependentAsset = $assetMapper->getAsset($resolvedPath);

            if (!$dependentAsset) {
                $message = sprintf('Unable to find asset "%s" imported from "%s".', $matches[1], $asset->sourcePath);

                try {
                    if (null !== $assetMapper->getAsset(sprintf('%s.js', $resolvedPath))) {
                        $message .= sprintf(' Try adding ".js" to the end of the import - i.e. "%s.js".', $matches[1]);
                    }
                } catch (CircularAssetsException $e) {
                    // avoid circular error if there is self-referencing import comments
                }

                $this->handleMissingImport($message);

                return $matches[0];
            }

            if ($this->supports($dependentAsset)) {
                // If we found the path and it's a JavaScript file, list it as a dependency.
                // This will cause the asset to be included in the importmap.
                $isLazy = str_contains($matches[0], 'import(');

                $asset->addDependency(new AssetDependency($dependentAsset, $isLazy, false));

                $relativeImportPath = $this->createRelativePath($asset->publicPathWithoutDigest, $dependentAsset->publicPathWithoutDigest);
                $relativeImportPath = $this->makeRelativeForJavaScript($relativeImportPath);

                return str_replace($matches[1], $relativeImportPath, $matches[0]);
            }

            return $matches[0];
        }, $content);
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
            AssetCompilerInterface::MISSING_IMPORT_WARN => $this->logger->warning($message),
            AssetCompilerInterface::MISSING_IMPORT_STRICT => throw new RuntimeException($message, 0, $e),
        };
    }
}
