<?php

namespace Symfony\Components\Validator\Constraints;

use Symfony\Components\Validator\Constraint;
use Symfony\Components\Validator\ConstraintValidator;
use Symfony\Components\Validator\Exception\UnexpectedTypeException;

class MinLengthValidator extends ConstraintValidator
{
    public function isValid($value, Constraint $constraint)
    {
        if ($value === null) {
            return true;
        }

        if (!is_scalar($value) && !(is_object($value) && method_exists($value, '__toString()'))) {
            throw new UnexpectedTypeException($value, 'string');
        }

        $value = (string)$value;

        $length = function_exists('mb_strlen') ? mb_strlen($value, $constraint->charset) : strlen($value);

        if ($length < $constraint->limit) {
            $this->setMessage($constraint->message, array(
                'value' => $value,
                'limit' => $constraint->limit,
            ));

            return false;
        }

        return true;
    }
}