<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Attribute;

/**
 * Indicates that a controller argument should receive an Entity.
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
class MapEntity
{
    public function __construct(
        public readonly ?string $class = null,
        public readonly ?string $objectManager = null,
        public readonly ?string $expr = null,
        public readonly array $mapping = [],
        public readonly array $exclude = [],
        public readonly bool $stripNull = false,
        public readonly array|string|null $id = null,
        public readonly bool $evictCache = false,
    ) {
    }
}
