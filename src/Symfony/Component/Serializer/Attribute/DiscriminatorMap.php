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
        private readonly string $typeProperty,
        private readonly array $mapping,
        private readonly ?string $defaultType = null,
    ) {
        if (!$typeProperty) {
            throw new InvalidArgumentException(\sprintf('Parameter "typeProperty" given to "%s" cannot be empty.', static::class));
        }

        if (!$mapping) {
            throw new InvalidArgumentException(\sprintf('Parameter "mapping" given to "%s" cannot be empty.', static::class));
        }

        if (null !== $this->defaultType && !\array_key_exists($this->defaultType, $this->mapping)) {
            throw new InvalidArgumentException(\sprintf('Default type "%s" given to "%s" must be present in "mapping" types.', $this->defaultType, static::class));
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

    public function getDefaultType(): ?string
    {
        return $this->defaultType;
    }
}

if (!class_exists(\Symfony\Component\Serializer\Annotation\DiscriminatorMap::class, false)) {
    class_alias(DiscriminatorMap::class, \Symfony\Component\Serializer\Annotation\DiscriminatorMap::class);
}
