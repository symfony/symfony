<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\InvalidArgumentException;

/**
 * Validates an object embedded in an object's property.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Valid extends Constraint
{
    public bool $traverse = true;

    /**
     * Groups to use when validating the nested object.
     *
     * If null (default), the current group from the context is used (existing behavior).
     * If an array is provided, these groups are used instead; an empty array
     * skips the nested validation entirely.
     *
     * @var string[]|null
     */
    public ?array $cascadedGroups = null;

    /**
     * @param string[]|null $groups
     * @param bool|null     $traverse       Whether to validate {@see \Traversable} objects (defaults to true)
     * @param string[]|null $cascadedGroups Groups to use when validating the nested object
     */
    public function __construct(?array $options = null, ?array $groups = null, $payload = null, ?bool $traverse = null, ?array $cascadedGroups = null)
    {
        if (null !== $options) {
            throw new InvalidArgumentException(\sprintf('Passing an array of options to configure the "%s" constraint is no longer supported.', static::class));
        }

        parent::__construct(null, $groups, $payload);

        $this->traverse = $traverse ?? $this->traverse;
        $this->cascadedGroups = $cascadedGroups ?? $this->cascadedGroups;
    }

    public function __get(string $option): mixed
    {
        if ('groups' === $option) {
            // when this is reached, no groups have been configured
            return null;
        }

        return parent::__get($option);
    }

    public function addImplicitGroupName(string $group): void
    {
        if (null !== $this->groups) {
            parent::addImplicitGroupName($group);

            return;
        }

        // with cascaded groups but no explicit groups, the constraint must join the
        // default group to be executed at all; without either, cascading is handled
        // by the metadata (see GenericMetadata::addConstraint()) and it stays group-less
        if (null !== $this->cascadedGroups) {
            $this->groups = [self::DEFAULT_GROUP];
            parent::addImplicitGroupName($group);
        }
    }
}
