<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Attribute;

use Symfony\Component\Serializer\Exception\InvalidArgumentException;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_PROPERTY)]
class MaxDepth
{
    /**
     * @param int $maxDepth The maximum serialization depth
     */
    public function __construct(
        public readonly int $maxDepth,
    ) {
        if ($maxDepth <= 0) {
            throw new InvalidArgumentException(\sprintf('Parameter given to "%s" must be a positive integer.', static::class));
        }
    }
}
