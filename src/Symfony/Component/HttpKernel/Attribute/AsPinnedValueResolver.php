<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Attribute;

/**
 * Service tag to autoconfigure pinned value resolvers.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class AsPinnedValueResolver
{
    public function __construct(
        public readonly ?string $name = null,
    ) {
    }
}
