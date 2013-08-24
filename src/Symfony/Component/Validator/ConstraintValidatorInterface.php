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
     * @param ExecutionContextInterface $context The current validation context
     *
     * @since v2.2.0
     */
    public function initialize(ExecutionContextInterface $context);

    /**
     * Checks if the passed value is valid.
     *
     * @param mixed      $value      The value that should be validated
     * @param Constraint $constraint The constraint for the validation
     *
     * @api
     *
     * @since v2.1.0
     */
    public function validate($value, Constraint $constraint);
}
