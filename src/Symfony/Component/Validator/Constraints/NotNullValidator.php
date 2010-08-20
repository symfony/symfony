<?php

namespace Symfony\Component\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class NotNullValidator extends ConstraintValidator
{
    public function isValid($value, Constraint $constraint)
    {
        if (is_null($value)) {
            $this->setMessage($constraint->message);

            return false;
        }

        return true;
    }
}