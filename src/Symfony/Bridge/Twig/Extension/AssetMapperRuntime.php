<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Extension;

use Symfony\Component\AssetMapper\AssetMapperInterface;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class AssetMapperRuntime
{
    public function __construct(
        private readonly AssetMapperInterface $assetMapper,
    ) {
    }

    public function source(string $logicalPath): string
    {
        if (!$asset = $this->assetMapper->getAsset($logicalPath)) {
            throw new \LogicException(\sprintf('The asset "%s" was not found.', $logicalPath));
        }

        if ($asset->content) {
            return $asset->content;
        }

        if (false === $content = file_get_contents($asset->sourcePath)) {
            throw new \RuntimeException(\sprintf('The asset "%s" could not be read.', $asset->sourcePath));
        }

        return $content;
    }
}
