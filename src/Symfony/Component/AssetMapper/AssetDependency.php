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
     * @param bool $isLazy whether this dependency is immediately needed
     */
    public function __construct(
        public readonly MappedAsset $asset,
        public readonly bool $isLazy,
    ) {
    }
}
