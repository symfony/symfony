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

use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\AssetMapper\MappedAsset;

/**
 * Resolves url() paths in CSS files.
 *
 * Originally sourced from https://github.com/rails/propshaft/blob/main/lib/propshaft/compilers/css_asset_urls.rb
 *
 * @experimental
 */
final class CssAssetUrlCompiler implements AssetCompilerInterface
{
    use AssetCompilerPathResolverTrait;

    // https://regex101.com/r/BOJ3vG/1
    public const ASSET_URL_PATTERN = '/url\(\s*["\']?(?!(?:\/|\#|%23|data|http|\/\/))([^"\'\s?#)]+)([#?][^"\')]+)?\s*["\']?\)/';

    public function __construct(private readonly bool $strictMode = true)
    {
    }

    public function compile(string $content, MappedAsset $asset, AssetMapperInterface $assetMapper): string
    {
        return preg_replace_callback(self::ASSET_URL_PATTERN, function ($matches) use ($asset, $assetMapper) {
            $resolvedPath = $this->resolvePath(\dirname($asset->logicalPath), $matches[1]);
            $dependentAsset = $assetMapper->getAsset($resolvedPath);

            if (null === $dependentAsset) {
                if ($this->strictMode) {
                    throw new \RuntimeException(sprintf('Unable to find asset "%s" referenced in "%s".', $resolvedPath, $asset->getSourcePath()));
                }

                // return original, unchanged path
                return $matches[0];
            }

            $asset->addDependency($dependentAsset);

            return 'url("'.$dependentAsset->getPublicPath().'")';
        }, $content);
    }

    public function supports(MappedAsset $asset): bool
    {
        return 'text/css' === $asset->getMimeType();
    }
}
