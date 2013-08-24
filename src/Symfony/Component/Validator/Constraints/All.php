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
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @api
 *
 * @since v2.0.0
 */
class All extends Constraint
{
    public $constraints = array();

    /**
     * {@inheritDoc}
     *
     * @since v2.1.0
     */
    public function __construct($options = null)
    {
        parent::__construct($options);

        if (!is_array($this->constraints)) {
            $this->constraints = array($this->constraints);
        }

        foreach ($this->constraints as $constraint) {
            if (!$constraint instanceof Constraint) {
                throw new ConstraintDefinitionException(sprintf('The value %s is not an instance of Constraint in constraint %s', $constraint, __CLASS__));
            }

            if ($constraint instanceof Valid) {
                throw new ConstraintDefinitionException(sprintf('The constraint Valid cannot be nested inside constraint %s. You can only declare the Valid constraint directly on a field or method.', __CLASS__));
            }
        }
    }

    /**
     * @since v2.0.0
     */
    public function getDefaultOption()
    {
        return 'constraints';
    }

    /**
     * @since v2.0.0
     */
    public function getRequiredOptions()
    {
        return array('constraints');
    }
}
