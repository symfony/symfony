<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Annotation;

use Symfony\Component\Serializer\Exception\InvalidArgumentException;

/**
 * Annotation class for @Groups().
 *
 * @Annotation
 * @Target({"PROPERTY", "METHOD"})
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_PROPERTY)]
class Groups
{
    /**
     * @var string[]
     */
    private $groups;

    /**
     * @throws InvalidArgumentException
     */
    public function __construct(array $groups)
    {
        if (isset($groups['value'])) {
            $groups = (array) $groups['value'];
        }
        if (empty($groups)) {
            throw new InvalidArgumentException(sprintf('Parameter of annotation "%s" cannot be empty.', static::class));
        }

        foreach ($groups as $group) {
            if (!\is_string($group)) {
                throw new InvalidArgumentException(sprintf('Parameter of annotation "%s" must be a string or an array of strings.', static::class));
            }
        }

        $this->groups = $groups;
    }

    /**
     * Gets groups.
     *
     * @return string[]
     */
    public function getGroups()
    {
        return $this->groups;
    }
}
