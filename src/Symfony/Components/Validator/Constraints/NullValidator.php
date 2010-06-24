<?php

namespace Symfony\Components\Validator\Constraints;

use Symfony\Components\Validator\Constraint;
use Symfony\Components\Validator\ConstraintValidator;

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