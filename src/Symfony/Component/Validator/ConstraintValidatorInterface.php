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

interface ConstraintValidatorInterface
{
    /**
     * Initialize the constraint validator.
     *
     * @param ExecutionContext $context The current validation context
     */
    function initialize(ExecutionContext $context);

    /**
     * @param  mixed $value The value that should be validated
     * @param Constraint $constraint The constrain for the validation
     * @return Boolean Whether or not the value is valid
     */
    function isValid($value, Constraint $constraint);

    /**
     * @return string
     */
    function getMessageTemplate();

    /**
     * @return array
     */
    function getMessageParameters();
}
