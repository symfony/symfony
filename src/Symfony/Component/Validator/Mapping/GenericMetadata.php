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
use Symfony\Component\Validator\Constraints\Traverse;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\Exception\BadMethodCallException;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\ValidationVisitorInterface;

/**
 * A generic container of {@link Constraint} objects.
 *
 * This class supports serialization and cloning.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class GenericMetadata implements MetadataInterface
{
    /**
     * @var Constraint[]
     *
     * @internal This property is public in order to reduce the size of the
     *           class' serialized representation. Do not access it. Use
     *           {@link getConstraints()} and {@link findConstraints()} instead.
     */
    public $constraints = array();

    /**
     * @var array
     *
     * @internal This property is public in order to reduce the size of the
     *           class' serialized representation. Do not access it. Use
     *           {@link findConstraints()} instead.
     */
    public $constraintsByGroup = array();

    /**
     * The strategy for cascading objects.
     *
     * By default, objects are not cascaded.
     *
     * @var int
     *
     * @see CascadingStrategy
     *
     * @internal This property is public in order to reduce the size of the
     *           class' serialized representation. Do not access it. Use
     *           {@link getCascadingStrategy()} instead.
     */
    public $cascadingStrategy = CascadingStrategy::NONE;

    /**
     * The strategy for traversing traversable objects.
     *
     * By default, traversable objects are not traversed.
     *
     * @var int
     *
     * @see TraversalStrategy
     *
     * @internal This property is public in order to reduce the size of the
     *           class' serialized representation. Do not access it. Use
     *           {@link getTraversalStrategy()} instead.
     */
    public $traversalStrategy = TraversalStrategy::NONE;

    /**
     * Returns the names of the properties that should be serialized.
     *
     * @return string[]
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
     * Adds a constraint.
     *
     * If the constraint {@link Valid} is added, the cascading strategy will be
     * changed to {@link CascadingStrategy::CASCADE}. Depending on the
     * properties $traverse and $deep of that constraint, the traversal strategy
     * will be set to one of the following:
     *
     *  - {@link TraversalStrategy::IMPLICIT} if $traverse is enabled and $deep
     *    is enabled
     *  - {@link TraversalStrategy::IMPLICIT} | {@link TraversalStrategy::STOP_RECURSION}
     *    if $traverse is enabled, but $deep is disabled
     *  - {@link TraversalStrategy::NONE} if $traverse is disabled
     *
     * @return $this
     *
     * @throws ConstraintDefinitionException When trying to add the
     *                                       {@link Traverse} constraint
     */
    public function addConstraint(Constraint $constraint)
    {
        if ($constraint instanceof Traverse) {
            throw new ConstraintDefinitionException(sprintf(
                'The constraint "%s" can only be put on classes. Please use '.
                '"Symfony\Component\Validator\Constraints\Valid" instead.',
                \get_class($constraint)
            ));
        }

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

    /**
     * Adds an list of constraints.
     *
     * @param Constraint[] $constraints The constraints to add
     *
     * @return $this
     */
    public function addConstraints(array $constraints)
    {
        foreach ($constraints as $constraint) {
            $this->addConstraint($constraint);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getConstraints()
    {
        return $this->constraints;
    }

    /**
     * Returns whether this element has any constraints.
     *
     * @return bool
     */
    public function hasConstraints()
    {
        return \count($this->constraints) > 0;
    }

    /**
     * {@inheritdoc}
     *
     * Aware of the global group (* group).
     */
    public function findConstraints($group)
    {
        return isset($this->constraintsByGroup[$group])
            ? $this->constraintsByGroup[$group]
            : array();
    }

    /**
     * {@inheritdoc}
     */
    public function getCascadingStrategy()
    {
        return $this->cascadingStrategy;
    }

    /**
     * {@inheritdoc}
     */
    public function getTraversalStrategy()
    {
        return $this->traversalStrategy;
    }

    /**
     * Exists for compatibility with the deprecated
     * {@link Symfony\Component\Validator\MetadataInterface}.
     *
     * Should not be used.
     *
     * Implemented for backward compatibility with Symfony < 2.5.
     *
     * @throws BadMethodCallException
     *
     * @deprecated since version 2.5, to be removed in 3.0.
     */
    public function accept(ValidationVisitorInterface $visitor, $value, $group, $propertyPath)
    {
        throw new BadMethodCallException('Not supported.');
    }
}
