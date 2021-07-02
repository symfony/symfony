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
 * @NamedArgumentConstructor
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
     * @param string|string[] $groups
     */
    public function __construct($groups)
    {
        if (\is_string($groups)) {
            $groups = (array) $groups;
        } elseif (!\is_array($groups)) {
            throw new \TypeError(sprintf('"%s": Parameter $groups is expected to be a string or an array of strings, got "%s".', __METHOD__, get_debug_type($groups)));
        } elseif (isset($groups['value'])) {
            trigger_deprecation('symfony/serializer', '5.3', 'Passing an array of properties as first argument to "%s" is deprecated. Use named arguments instead.', __METHOD__);

            $groups = (array) $groups['value'];
        }
        if (empty($groups)) {
            throw new InvalidArgumentException(sprintf('Parameter of annotation "%s" cannot be empty.', static::class));
        }

        foreach ($groups as $group) {
            if (!\is_string($group) || '' === $group) {
                throw new InvalidArgumentException(sprintf('Parameter of annotation "%s" must be a string or an array of non-empty strings.', static::class));
            }
        }

        $this->groups = $groups;
    }

    /**
     * @return string[]
     */
    public function getGroups()
    {
        return $this->groups;
    }
}
