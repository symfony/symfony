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
 */
class Group extends Constraint
{
    /**
     * @var array An array of Constraint instances
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

        foreach ($this->constraints as $key => $constraint) {
            if (!$constraint instanceof Constraint) {
                throw new ConstraintDefinitionException(sprintf('The "constraints" option of the Group constraint class only accepts a list of Constraint instances. Item given at position %u is of type %s.', $key, gettype($constraint)));
            }

            if ($constraint instanceof Group) {
                throw new ConstraintDefinitionException('The Group constraint cannot be nested inside another Group constraint.');
            }

            if ($constraint instanceof Valid) {
                throw new ConstraintDefinitionException('The Valid constraint cannot be nested inside a Group constraint as the Valid constraint does not support validation groups.');
            }
        }
    }

    public function getTargets()
    {
        return array(self::PROPERTY_CONSTRAINT, self::CLASS_CONSTRAINT);
    }

    public function getRequiredOptions()
    {
        return array('groups', 'constraints');
    }
}
