<?php

namespace Symfony\Component\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class NotBlankValidator extends ConstraintValidator
{
    public function isValid($value, Constraint $constraint)
    {
        if ($value === '' || $value === null) {
            $this->setMessage($constraint->message);

            return false;
        }

        return true;
    }
}