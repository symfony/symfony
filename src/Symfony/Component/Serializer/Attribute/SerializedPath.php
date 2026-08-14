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

use Symfony\Component\PropertyAccess\Exception\InvalidPropertyPathException;
use Symfony\Component\PropertyAccess\PropertyPath;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;

/**
 * @author Tobias Bönner <tobi@boenner.family>
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_PROPERTY | \Attribute::IS_REPEATABLE)]
class SerializedPath
{
    public readonly PropertyPath $serializedPath;

    public readonly array $groups;

    /**
     * @param string       $serializedPath A path using a valid PropertyAccess syntax where the value is stored in a normalized representation
     * @param string|array $groups         The groups to use when serializing or deserializing
     */
    public function __construct(string $serializedPath, string|array $groups = ['*'])
    {
        try {
            $this->serializedPath = new PropertyPath($serializedPath);
        } catch (InvalidPropertyPathException) {
            throw new InvalidArgumentException(\sprintf('Parameter given to "%s" must be a valid property path.', self::class));
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
