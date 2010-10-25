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

use Symfony\Component\Validator\Constraint;

/**
 * Validates a given value.
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
interface ValidatorInterface
{
    function validate($object, $groups = null);

    function validateProperty($object, $property, $groups = null);

    function validatePropertyValue($class, $property, $value, $groups = null);

    function validateValue($value, Constraint $constraint, $groups = null);
}