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
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class DiscriminatorMap
{
    /**
     * @param string                      $typeProperty The property holding the type discriminator
     * @param array<string, class-string> $mapping      The mapping between types and classes (i.e. ['admin_user' => AdminUser::class])
     * @param ?string                     $defaultType  The fallback value if nothing specified by $typeProperty
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        public readonly string $typeProperty,
        public readonly array $mapping = [],
        public readonly ?string $defaultType = null,
    ) {
        if (!$typeProperty) {
            throw new InvalidArgumentException(\sprintf('Parameter "typeProperty" given to "%s" cannot be empty.', static::class));
        }
    }
}
