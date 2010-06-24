<?php

namespace Symfony\Components\Validator\Constraints;

use Symfony\Components\Validator\Constraint;
use Symfony\Components\Validator\ConstraintValidator;
use Symfony\Components\Validator\Exception\UnexpectedTypeException;

class MaxValidator extends ConstraintValidator
{
    public function isValid($value, Constraint $constraint)
    {
        if ($value === null) {
            return true;
        }

        if (!is_numeric($value)) {
            throw new UnexpectedTypeException($value, 'numeric');
        }

        if ($value > $constraint->limit) {
            $this->setMessage($constraint->message, array(
                'value' => $value,
                'limit' => $constraint->limit,
            ));

            return false;
        }

        return true;
    }
}