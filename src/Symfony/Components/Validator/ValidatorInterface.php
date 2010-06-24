<?php

namespace Symfony\Components\Validator;

use Symfony\Components\Validator\Constraint;

/**
 * Validates a given value.
 *
 * @author     Bernhard Schussek <bernhard.schussek@symfony-project.com>
 * @version    SVN: $Id: ValidatorInterface.php 138 2010-01-18 22:05:14Z flo $
 */
interface ValidatorInterface
{
    public function validate($object, $groups = null);

    public function validateProperty($object, $property, $groups = null);

    public function validatePropertyValue($class, $property, $value, $groups = null);

    public function validateValue($value, Constraint $constraint, $groups = null);
}