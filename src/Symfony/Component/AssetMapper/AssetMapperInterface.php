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
 * @experimental
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
interface AssetMapperInterface
{
    /**
     * The path that should be prefixed on all asset paths to point to the output location.
     */
    public function getPublicPrefix(): string;

    /**
     * Given the logical path (e.g. path relative to a mapped directory), return the asset.
     */
    public function getAsset(string $logicalPath): ?MappedAsset;

    /**
     * Returns all mapped assets.
     *
     * @return MappedAsset[]
     */
    public function allAssets(): array;

    /**
     * Fetches the asset given its source path (i.e. filesystem path).
     */
    public function getAssetFromSourcePath(string $sourcePath): ?MappedAsset;

    /**
     * Returns the public path for this asset, if it can be found.
     */
    public function getPublicPath(string $logicalPath): ?string;

    /**
     * Returns the filesystem path to where assets are stored when compiled.
     */
    public function getPublicAssetsFilesystemPath(): string;
}
