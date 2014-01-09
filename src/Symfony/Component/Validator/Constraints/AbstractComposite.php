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
 * @author Marc Morera Merino <hyuhu@mmoreram.com>
 */
abstract class AbstractComposite extends Constraint
{

    /**
     * @var array
     *
     * Set of constraints
     */
    public $constraints = array();


    /**
     * {@inheritDoc}
     */
    public function __construct($options = null)
    {
        parent::__construct($options);

        if (!is_array($this->constraints)) {
            $this->constraints = array($this->constraints);
        }

        /**
         * We consider explicid groups are defined if are not default one
         */
        $areExplicitGroupsDefined = ( $this->groups != array(self::DEFAULT_GROUP) );

        /**
         * Each constraint contained
         */
        foreach ($this->constraints as $constraint) {
            if (!$constraint instanceof Constraint) {
                throw new ConstraintDefinitionException(sprintf('The value %s is not an instance of Constraint in constraint %s', $constraint, __CLASS__));
            }

            if ($constraint instanceof Valid) {
                throw new ConstraintDefinitionException(sprintf('The constraint Valid cannot be nested inside constraint %s. You can only declare the Valid constraint directly on a field or method.', __CLASS__));
            }

            /**
             * If explicid groups are defined
             */
            if ($areExplicitGroupsDefined) {
                /**
                 * If constraint has explicid groups defined
                 *
                 * In that case, the groups of the nested constraint need to be
                 * a subset of the groups of the outer constraint.
                 */
                if ($constraint->groups != array(self::DEFAULT_GROUP)) {
                    /**
                     * If are not a subset
                     */
                    if ($constraint->groups != array_intersect($constraint->groups, $this->groups)) {
                        throw new ConstraintDefinitionException(sprintf('The groups defined in Constraint %s must be a subset of the groups defined in the Constraint %s', $constraint, __CLASS__));
                    }

                /**
                 * Otherwise, we add all defined groups here
                 */
                } else {
                    foreach ($this->groups as $group) {
                        $constraint->addImplicitGroupName($group);
                    }
                }

            /**
             * Otherwise, we merge current groups with constraint
             */
            } else {
                $this->groups = array_unique(array_merge($this->groups, $constraint->groups));
            }
        }
    }


    /**
     * Adds the given group if this constraint is in the Default group
     *
     * Also propagate same method to nested Constraints
     *
     * @param string $group
     *
     * @api
     */
    public function addImplicitGroupName($group)
    {
        parent::addImplicitGroupName($group);

        foreach ($this->constraints as $constraint) {
            $constraint->addImplicitGroupName($group);
        }
    }


    /**
     * {@inheritDoc}
     */
    public function getDefaultOption()
    {
        return 'constraints';
    }


    /**
     * {@inheritDoc}
     */
    public function getRequiredOptions()
    {
        return array('constraints');
    }
}
