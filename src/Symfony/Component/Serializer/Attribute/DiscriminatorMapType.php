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
 * Adds the attributed class to a {@see DiscriminatorMap} declared by a parent class or implemented interface.
 *
 * @author Matthias Schmidt <matthias@mttsch.dev>
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final class DiscriminatorMapType
{
    /**
     * @param string       $type  The value that identifies the attributed class in the discriminator map
     * @param class-string $class The class or interface declaring the discriminator map
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        public readonly string $type,
        public readonly string $class,
    ) {
        if (!$type) {
            throw new InvalidArgumentException(\sprintf('Parameter "type" given to "%s" cannot be empty.', static::class));
        }

        if (!$class) {
            throw new InvalidArgumentException(\sprintf('Parameter "class" given to "%s" cannot be empty.', static::class));
        }
    }
}
