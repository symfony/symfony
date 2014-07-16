<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator;

use Symfony\Component\Validator\Context\ExecutionContextInterface as NewExecutionContextInterface;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @api
 */
interface ConstraintValidatorInterface
{
    /**
     * Initializes the constraint validator.
     *
     * @param ExecutionContextInterface|NewExecutionContextInterface $context The current validation context
     */
    public function initialize($context);

    /**
     * Checks if the passed value is valid.
     *
     * @param mixed      $value      The value that should be validated
     * @param Constraint $constraint The constraint for the validation
     *
     * @api
     */
    public function validate($value, Constraint $constraint);
}
