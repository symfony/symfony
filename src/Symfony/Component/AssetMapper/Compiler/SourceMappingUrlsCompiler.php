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

use Symfony\Component\AssetMapper\AssetDependency;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\AssetMapper\MappedAsset;

/**
 * Rewrites already-existing source map URLs to their final digested path.
 *
 * Originally sourced from https://github.com/rails/propshaft/blob/main/lib/propshaft/compilers/source_mapping_urls.rb
 *
 * @experimental
 */
final class SourceMappingUrlsCompiler implements AssetCompilerInterface
{
    use AssetCompilerPathResolverTrait;

    private const SOURCE_MAPPING_PATTERN = '/^(\/\/|\/\*)# sourceMappingURL=(.+\.map)/m';

    public function supports(MappedAsset $asset): bool
    {
        return \in_array($asset->publicExtension, ['css', 'js'], true);
    }

    public function compile(string $content, MappedAsset $asset, AssetMapperInterface $assetMapper): string
    {
        return preg_replace_callback(self::SOURCE_MAPPING_PATTERN, function ($matches) use ($asset, $assetMapper) {
            $resolvedPath = $this->resolvePath(\dirname($asset->logicalPath), $matches[2]);

            $dependentAsset = $assetMapper->getAsset($resolvedPath);
            if (!$dependentAsset) {
                // return original, unchanged path
                return $matches[0];
            }

            $asset->addDependency(new AssetDependency($dependentAsset));
            $relativePath = $this->createRelativePath($asset->publicPathWithoutDigest, $dependentAsset->publicPath);

            return $matches[1].'# sourceMappingURL='.$relativePath;
        }, $content);
    }
}
