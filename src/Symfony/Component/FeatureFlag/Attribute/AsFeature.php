<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\FeatureFlag\Attribute;

/**
 * Service tag to autoconfigure feature flags.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class AsFeature
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $method = null,
    ) {
    }
}
