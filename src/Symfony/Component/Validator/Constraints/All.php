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
 * @api
 */
class All extends Constraint
{
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

        foreach ($this->constraints as $constraint) {
            if (!$constraint instanceof Constraint) {
                throw new ConstraintDefinitionException('The value ' . $constraint . ' is not an instance of Constraint in constraint ' . __CLASS__);
            }

            if ($constraint instanceof Valid) {
                throw new ConstraintDefinitionException('The constraint Valid cannot be nested inside constraint ' . __CLASS__ . '. You can only declare the Valid constraint directly on a field or method.');
            }
        }
    }

    public function getDefaultOption()
    {
        return 'constraints';
    }

    public function getRequiredOptions()
    {
        return array('constraints');
    }
}
