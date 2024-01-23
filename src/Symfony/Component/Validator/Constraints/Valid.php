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

/**
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Valid extends Constraint
{
    public $traverse = true;

    public function __construct(?array $options = null, ?array $groups = null, $payload = null, ?bool $traverse = null)
    {
        parent::__construct($options ?? [], $groups, $payload);

        $this->traverse = $traverse ?? $this->traverse;
    }

    public function __get(string $option): mixed
    {
        if ('groups' === $option) {
            // when this is reached, no groups have been configured
            return null;
        }

        return parent::__get($option);
    }

    /**
     * @return void
     */
    public function addImplicitGroupName(string $group)
    {
        if (null !== $this->groups) {
            parent::addImplicitGroupName($group);
        }
    }
}
