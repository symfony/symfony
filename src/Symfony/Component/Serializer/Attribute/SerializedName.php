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
 * @author Fabien Bourigault <bourigaultfabien@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_PROPERTY | \Attribute::IS_REPEATABLE)]
class SerializedName
{
    private array $groups;

    /**
     * @param string          $serializedName The name of the property as it will be serialized
     * @param string|string[] $groups         The groups to use when serializing or deserializing
     */
    public function __construct(
        private readonly string $serializedName,
        string|array $groups = ['*'],
    ) {
        if ('' === $serializedName) {
            throw new InvalidArgumentException(\sprintf('Parameter given to "%s" must be a non-empty string.', self::class));
        }

        $this->groups = ((array) $groups) ?: ['*'];

        foreach ($this->groups as $group) {
            if (!\is_string($group)) {
                throw new InvalidArgumentException(\sprintf('Parameter "groups" given to "%s" must be a string or an array of strings, "%s" given.', static::class, get_debug_type($group)));
            }
        }
    }

    public function getSerializedName(): string
    {
        return $this->serializedName;
    }

    public function getGroups(): array
    {
        return $this->groups;
    }
}

if (!class_exists(\Symfony\Component\Serializer\Annotation\SerializedName::class, false)) {
    class_alias(SerializedName::class, \Symfony\Component\Serializer\Annotation\SerializedName::class);
}
