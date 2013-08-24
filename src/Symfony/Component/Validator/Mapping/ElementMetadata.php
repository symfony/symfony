<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Mapping;

use Symfony\Component\Validator\Constraint;

/**
 * @since v2.0.0
 */
abstract class ElementMetadata
{
    /**
     * @var Constraint[]
     */
    public $constraints = array();

    /**
     * @var array
     */
    public $constraintsByGroup = array();

    /**
     * Returns the names of the properties that should be serialized.
     *
     * @return array
     *
     * @since v2.0.0
     */
    public function __sleep()
    {
        return array(
            'constraints',
            'constraintsByGroup',
        );
    }

    /**
     * Clones this object.
     *
     * @since v2.0.0
     */
    public function __clone()
    {
        $constraints = $this->constraints;

        $this->constraints = array();
        $this->constraintsByGroup = array();

        foreach ($constraints as $constraint) {
            $this->addConstraint(clone $constraint);
        }
    }

    /**
     * Adds a constraint to this element.
     *
     * @param Constraint $constraint
     *
     * @return ElementMetadata
     *
     * @since v2.0.0
     */
    public function addConstraint(Constraint $constraint)
    {
        $this->constraints[] = $constraint;

        foreach ($constraint->groups as $group) {
            $this->constraintsByGroup[$group][] = $constraint;
        }

        return $this;
    }

    /**
     * Returns all constraints of this element.
     *
     * @return Constraint[] An array of Constraint instances
     *
     * @since v2.0.0
     */
    public function getConstraints()
    {
        return $this->constraints;
    }

    /**
     * Returns whether this element has any constraints.
     *
     * @return Boolean
     *
     * @since v2.0.0
     */
    public function hasConstraints()
    {
        return count($this->constraints) > 0;
    }

    /**
     * Returns the constraints of the given group and global ones (* group).
     *
     * @param string $group The group name
     *
     * @return array An array with all Constraint instances belonging to the group
     *
     * @since v2.0.0
     */
    public function findConstraints($group)
    {
        return isset($this->constraintsByGroup[$group])
                ? $this->constraintsByGroup[$group]
                : array();
    }
}
