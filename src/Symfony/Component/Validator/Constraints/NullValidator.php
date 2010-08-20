<?php

namespace Symfony\Component\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class NullValidator extends ConstraintValidator
{
    public function isValid($value, Constraint $constraint)
    {
        if (!is_null($value)) {
            $this->setMessage($constraint->message, array('value' => $value));

            return false;
        }

        return true;
    }
}