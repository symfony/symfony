<?php

namespace Symfony\Components\Validator\Constraints;

use Symfony\Components\Validator\Constraint;
use Symfony\Components\Validator\ConstraintValidator;

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