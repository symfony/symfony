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
use Symfony\Component\AssetMapper\Exception\RuntimeException;
use Symfony\Component\AssetMapper\MappedAsset;
use Symfony\Component\Filesystem\Path;

/**
 * Resolves url() paths in CSS files.
 *
 * Originally sourced from https://github.com/rails/propshaft/blob/main/lib/propshaft/compilers/css_asset_urls.rb
 */
final class CssAssetUrlCompiler implements AssetCompilerInterface
{
    // https://regex101.com/r/BOJ3vG/1
    public const ASSET_URL_PATTERN = '/url\(\s*["\']?(?!(?:\/|\#|%23|data|http|\/\/))([^"\'\s?#)]+)([#?][^"\')]+)?\s*["\']?\)/';

    public function __construct(
        private readonly string $missingImportMode = self::MISSING_IMPORT_WARN,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function compile(string $content, MappedAsset $asset, AssetMapperInterface $assetMapper): string
    {
        return preg_replace_callback(self::ASSET_URL_PATTERN, function ($matches) use ($asset, $assetMapper) {
            try {
                $resolvedSourcePath = Path::join(\dirname($asset->sourcePath), $matches[1]);
            } catch (RuntimeException $e) {
                $this->handleMissingImport(\sprintf('Error processing import in "%s": ', $asset->sourcePath).$e->getMessage(), $e);

                return $matches[0];
            }
            $dependentAsset = $assetMapper->getAssetFromSourcePath($resolvedSourcePath);

            if (null === $dependentAsset) {
                $message = \sprintf('Unable to find asset "%s" referenced in "%s". The file "%s" ', $matches[1], $asset->sourcePath, $resolvedSourcePath);
                if (is_file($resolvedSourcePath)) {
                    $message .= 'exists, but it is not in a mapped asset path. Add it to the "paths" config.';
                } else {
                    $message .= 'does not exist.';
                }
                $this->handleMissingImport($message);

                // return original, unchanged path
                return $matches[0];
            }

            $asset->addDependency($dependentAsset);
            $relativePath = Path::makeRelative($dependentAsset->publicPath, \dirname($asset->publicPathWithoutDigest));

            return 'url("'.$relativePath.'")';
        }, $content);
    }

    public function supports(MappedAsset $asset): bool
    {
        return 'css' === $asset->publicExtension;
    }

    private function handleMissingImport(string $message, ?\Throwable $e = null): void
    {
        match ($this->missingImportMode) {
            AssetCompilerInterface::MISSING_IMPORT_IGNORE => null,
            AssetCompilerInterface::MISSING_IMPORT_WARN => $this->logger?->warning($message),
            AssetCompilerInterface::MISSING_IMPORT_STRICT => throw new RuntimeException($message, 0, $e),
        };
    }
}
