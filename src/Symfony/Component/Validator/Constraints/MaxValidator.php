<?php

namespace Symfony\Component\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

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