<?php

namespace Symfony\Component\Validator;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

interface ConstraintValidatorInterface
{
    /**
     * Initialize the constraint validator.
     *
     * @param ValidationContext $context The current validation context
     */
    function initialize(ValidationContext $context);

    /**
     * @param  mixed $value The value that should be validated
     * @param Constraint $constraint The constrain for the validation
     * @return boolean Whether or not the value is valid
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
