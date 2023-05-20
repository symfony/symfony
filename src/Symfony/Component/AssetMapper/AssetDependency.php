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
 * Represents a dependency that a MappedAsset has.
 *
 * @experimental
 */
final class AssetDependency
{
    /**
     * @param bool $isLazy              Whether the dependent asset will need to be loaded eagerly
     *                                  by the parent asset (e.g. a CSS file that imports another
     *                                  CSS file) or if it will be loaded lazily (e.g. an async
     *                                  JavaScript import).
     * @param bool $isContentDependency Whether the parent asset's content depends
     *                                  on the child asset's content - e.g. if a CSS
     *                                  file imports another CSS file, then the parent's
     *                                  content depends on the child CSS asset, because
     *                                  the child's digested filename will be included.
     */
    public function __construct(
        public readonly MappedAsset $asset,
        public readonly bool $isLazy = false,
        public readonly bool $isContentDependency = true,
    ) {
    }
}
