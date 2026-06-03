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

/**
 * Finds and returns assets in the pipeline.
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
interface AssetMapperInterface
{
    /**
     * Given the logical path (e.g. path relative to a mapped directory), return the asset.
     */
    public function getAsset(string $logicalPath): ?MappedAsset;

    /**
     * Returns all mapped assets.
     *
     * @return iterable<MappedAsset>
     */
    public function allAssets(): iterable;

    /**
     * Fetches the asset given its source path (i.e. filesystem path).
     */
    public function getAssetFromSourcePath(string $sourcePath): ?MappedAsset;

    /**
     * Returns the public path for this asset, if it can be found.
     */
    public function getPublicPath(string $logicalPath): ?string;
}
