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
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;

/**
 * @Annotation
 *
 * @author Marc Morales Valldepérez <marcmorales83@gmail.com>
 * @author Marc Morera Merino <yuhu@mmoreram.com>
 */
abstract class AbstractComposite extends Composite
{
    /**
     * @var array
     *
     * Set of constraints
     */
    public $constraints = [];

    /**
     * {@inheritdoc}
     */
    public function __construct($options = null)
    {
        parent::__construct($options);

        $this->constraints = (array) $this->constraints;

        // Each constraint contained
        foreach ($this->constraints as $constraint) {
            if (!$constraint instanceof Constraint) {
                throw new ConstraintDefinitionException(sprintf('The value %s is not an instance of Constraint in constraint %s', $constraint, \get_class($this)));
            }

            if ($constraint instanceof Valid) {
                throw new ConstraintDefinitionException(sprintf('The constraint Valid cannot be nested inside constraint %s. You can only declare the Valid constraint directly on a field or method.', \get_class($this)));
            }

            // If explicit groups are defined
            if ($this->groups != [self::DEFAULT_GROUP]) {
                /*
                 * If constraint has explicit groups defined
                 *
                 * In that case, the groups of the nested constraint need to be
                 * a subset of the groups of the outer constraint.
                 */
                if ($constraint->groups !== [self::DEFAULT_GROUP]) {
                    // If are not a subset
                    if ($constraint->groups != array_intersect($constraint->groups, $this->groups)) {
                        throw new ConstraintDefinitionException(sprintf('The groups defined in Constraint %s must be a subset of the groups defined in the Constraint %s', $constraint, \get_class($this)));
                    }

                    // Otherwise, we add all defined groups here
                } else {
                    foreach ($this->groups as $group) {
                        $constraint->addImplicitGroupName($group);
                    }
                }

                /*
                 * Otherwise, we merge current groups with constraint
                 */
            } else {
                $this->groups = array_unique(array_merge($this->groups, $constraint->groups));
            }
        }
    }

    /**
     * Adds the given group if this constraint is in the Default group.
     *
     * Also propagate same method to nested Constraints.
     *
     * @param string $group
     */
    public function addImplicitGroupName($group)
    {
        parent::addImplicitGroupName($group);

        foreach ($this->constraints as $constraint) {
            $constraint->addImplicitGroupName($group);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultOption()
    {
        return 'constraints';
    }

    /**
     * {@inheritdoc}
     */
    public function getRequiredOptions()
    {
        return ['constraints'];
    }

    /**
     * {@inheritdoc}
     */
    protected function getCompositeOption()
    {
        return 'constraints';
    }
}
