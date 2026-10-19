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
     * The validation groups to use when validating the embedded object.
     * When null, the current validation group is used (default behavior).
     *
     * @var string[]|null
     */
    public ?array $embeddedGroups = null;

    /**
     * @param string[]|null $groups         The groups this constraint belongs to
     * @param string[]|null $embeddedGroups The groups to use when validating the embedded object
     * @param bool|null     $traverse       Whether to validate {@see \Traversable} objects (defaults to true)
     */
    public function __construct(?array $options = null, ?array $groups = null, $payload = null, ?bool $traverse = null, ?array $embeddedGroups = null)
    {
        if (null !== $options) {
            throw new InvalidArgumentException(\sprintf('Passing an array of options to configure the "%s" constraint is no longer supported.', static::class));
        }

        parent::__construct(null, $groups, $payload);

        $this->traverse = $traverse ?? $this->traverse;
        $this->embeddedGroups = $embeddedGroups;
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
        }
    }
}