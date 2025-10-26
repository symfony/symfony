<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Attribute;

/**
 * Applies a transformer to all properties of a class.
 *
 * @author Antoine Bluchet <soyuka@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final class TransformAllProperties
{
    /**
     * @param (string|callable(mixed, object): mixed)|(string|callable(mixed, object): mixed)[]|null $transform A service id or a callable that transforms the value during mapping
     */
    public function __construct(
        public mixed $transform,
    ) {
    }
}
