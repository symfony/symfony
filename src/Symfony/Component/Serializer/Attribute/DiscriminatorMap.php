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
    public function __construct(
        private readonly string $typeProperty,
        private readonly array $mapping,
    ) {
        if (empty($typeProperty)) {
            throw new InvalidArgumentException(sprintf('Parameter "typeProperty" given to "%s" cannot be empty.', static::class));
        }

        if (empty($mapping)) {
            throw new InvalidArgumentException(sprintf('Parameter "mapping" given to "%s" cannot be empty.', static::class));
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
