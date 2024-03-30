<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper;

use Symfony\Component\AssetMapper\Compiler\AssetCompilerInterface;

/**
 * Runs a chain of compiles intended to adjust the source of assets.
 *
 * @final
 */
class AssetMapperCompiler
{
    private AssetMapperInterface $assetMapper;

    /**
     * @param iterable<AssetCompilerInterface> $assetCompilers
     * @param \Closure(): AssetMapperInterface $assetMapperFactory
     */
    public function __construct(private readonly iterable $assetCompilers, private readonly \Closure $assetMapperFactory)
    {
    }

    public function compile(string $content, MappedAsset $asset): string
    {
        foreach ($this->assetCompilers as $compiler) {
            if (!$compiler->supports($asset)) {
                continue;
            }

            $content = $compiler->compile($content, $asset, $this->assetMapper ??= ($this->assetMapperFactory)());
        }

        return $content;
    }

    public function supports(MappedAsset $asset): bool
    {
        foreach ($this->assetCompilers as $compiler) {
            if ($compiler->supports($asset)) {
                return true;
            }
        }

        return false;
    }
}
