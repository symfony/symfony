<?php

namespace Symfony\Component\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class DateTimeValidator extends ConstraintValidator
{
    const PATTERN = '/^(\d{4})-(\d{2})-(\d{2}) (0[0-9]|1[0-9]|2[0-3]):([0-5][0-9]):([0-5][0-9])$/';

    public function isValid($value, Constraint $constraint)
    {
        if ($value === null) {
            return true;
        }

        if ($value instanceof \DateTime) {
            return true;
        }

        if (!is_scalar($value) && !(is_object($value) && method_exists($value, '__toString()'))) {
            throw new UnexpectedTypeException($value, 'string');
        }

        $value = (string)$value;

        if (!preg_match(self::PATTERN, $value, $matches)) {
            $this->setMessage($constraint->message, array('value' => $value));

            return false;
        }

        return checkdate($matches[2], $matches[3], $matches[1]);
    }
}