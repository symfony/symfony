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
    public readonly array $groups;

    /**
     * @param string       $serializedName The name of the property as it will be serialized
     * @param string|array $groups         The groups to use when serializing or deserializing
     */
    public function __construct(
        public readonly string $serializedName,
        string|array $groups = ['*'],
    ) {
        if ('' === $serializedName) {
            throw new InvalidArgumentException(\sprintf('Parameter given to "%s" must be a non-empty string.', self::class));
        }

        $this->groups = (array) $groups;

        if (!$this->groups) {
            throw new InvalidArgumentException(\sprintf('Parameter "groups" given to "%s" must not be empty, omit it to apply to every group.', static::class));
        }

        foreach ($this->groups as $group) {
            if (!\is_string($group) || '' === $group) {
                throw new InvalidArgumentException(\sprintf('Parameter "groups" given to "%s" must be a string or an array of non-empty strings, "%s" given.', static::class, get_debug_type($group)));
            }
        }
    }
}
