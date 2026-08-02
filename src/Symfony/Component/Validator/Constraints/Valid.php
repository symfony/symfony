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
    public bool $restrictGroups = true;

    /**
     * @param string[]|null $groups
     * @param bool|null     $traverse       Whether to validate {@see \Traversable} objects (defaults to true)
     * @param bool|null     $restrictGroups Whether $groups also restrict the groups the nested object is validated
     *                                      against; when false they only decide when to cascade, and the nested
     *                                      object is validated against the groups being validated (defaults to true)
     */
    public function __construct(?array $options = null, ?array $groups = null, $payload = null, ?bool $traverse = null, ?bool $restrictGroups = null)
    {
        if (null !== $options) {
            throw new InvalidArgumentException(\sprintf('Passing an array of options to configure the "%s" constraint is no longer supported.', static::class));
        }

        parent::__construct(null, $groups, $payload);

        $this->traverse = $traverse ?? $this->traverse;
        $this->restrictGroups = $restrictGroups ?? $this->restrictGroups;
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
