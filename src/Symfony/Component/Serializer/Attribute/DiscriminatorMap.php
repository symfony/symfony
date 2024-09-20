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
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        private readonly string $typeProperty,
        private readonly array $mapping,
    ) {
        if (!$typeProperty) {
            throw new InvalidArgumentException(\sprintf('Parameter "typeProperty" given to "%s" cannot be empty.', static::class));
        }

        if (!$mapping) {
            throw new InvalidArgumentException(\sprintf('Parameter "mapping" given to "%s" cannot be empty.', static::class));
        }
    }

    public function getTypeProperty(): string
    {
        return $this->typeProperty;
    }

    public function getMapping(): array
    {
        return $this->mapping;
    }
}

if (!class_exists(\Symfony\Component\Serializer\Annotation\DiscriminatorMap::class, false)) {
    class_alias(DiscriminatorMap::class, \Symfony\Component\Serializer\Annotation\DiscriminatorMap::class);
}
