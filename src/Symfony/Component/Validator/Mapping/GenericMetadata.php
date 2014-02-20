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
use Symfony\Component\Validator\Constraints\Valid;

/**
 * @since  %%NextVersion%%
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class GenericMetadata  implements MetadataInterface
{
    /**
     * @var Constraint[]
     */
    public $constraints = array();

    /**
     * @var array
     */
    public $constraintsByGroup = array();

    public $cascadingStrategy = CascadingStrategy::NONE;

    public $traversalStrategy = TraversalStrategy::IMPLICIT;

    /**
     * Returns the names of the properties that should be serialized.
     *
     * @return array
     */
    public function __sleep()
    {
        return array(
            'constraints',
            'constraintsByGroup',
            'cascadingStrategy',
            'traversalStrategy',
        );
    }

    /**
     * Clones this object.
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
     */
    public function addConstraint(Constraint $constraint)
    {
        if ($constraint instanceof Valid) {
            $this->cascadingStrategy = CascadingStrategy::CASCADE;

            if ($constraint->traverse) {
                // Traverse unless the value is not traversable
                $this->traversalStrategy = TraversalStrategy::IMPLICIT;

                if (!$constraint->deep) {
                    $this->traversalStrategy |= TraversalStrategy::STOP_RECURSION;
                }
            } else {
                $this->traversalStrategy = TraversalStrategy::NONE;
            }

            return $this;
        }

        $this->constraints[] = $constraint;

        foreach ($constraint->groups as $group) {
            $this->constraintsByGroup[$group][] = $constraint;
        }

        return $this;
    }

    public function addConstraints(array $constraints)
    {
        foreach ($constraints as $constraint) {
            $this->addConstraint($constraint);
        }
    }

    /**
     * Returns all constraints of this element.
     *
     * @return Constraint[] An array of Constraint instances
     */
    public function getConstraints()
    {
        return $this->constraints;
    }

    /**
     * Returns whether this element has any constraints.
     *
     * @return Boolean
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
     */
    public function findConstraints($group)
    {
        return isset($this->constraintsByGroup[$group])
                ? $this->constraintsByGroup[$group]
                : array();
    }

    public function getCascadingStrategy()
    {
        return $this->cascadingStrategy;
    }

    public function getTraversalStrategy()
    {
        return $this->traversalStrategy;
    }
}
