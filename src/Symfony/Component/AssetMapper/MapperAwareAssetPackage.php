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

use Symfony\Component\Asset\PackageInterface;

/**
 * Decorates asset packages to support resolving assets from the asset mapper.
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
final class MapperAwareAssetPackage implements PackageInterface
{
    public function __construct(
        private readonly PackageInterface $innerPackage,
        private readonly AssetMapperInterface $assetMapper,
    ) {
    }

    public function getVersion(string $path): string
    {
        return $this->innerPackage->getVersion($path);
    }

    public function getUrl(string $path): string
    {
        $publicPath = $this->assetMapper->getPublicPath($path);
        if ($publicPath) {
            $path = ltrim($publicPath, '/');
        }

        return $this->innerPackage->getUrl($path);
    }
}
