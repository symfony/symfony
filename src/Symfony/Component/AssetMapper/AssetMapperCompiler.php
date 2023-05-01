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
 * @experimental
 *
 * @final
 */
class AssetMapperCompiler
{
    /**
     * @param iterable<AssetCompilerInterface> $assetCompilers
     */
    public function __construct(private iterable $assetCompilers)
    {
    }

    public function compile(string $content, MappedAsset $mappedAsset, AssetMapperInterface $assetMapper): string
    {
        foreach ($this->assetCompilers as $compiler) {
            if (!$compiler->supports($mappedAsset)) {
                continue;
            }

            $content = $compiler->compile($content, $mappedAsset, $assetMapper);
        }

        return $content;
    }
}
