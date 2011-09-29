<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * @api
 */
class SizeLengthValidator extends ConstraintValidator
{
    /**
     * Checks if the passed value is valid.
     *
     * @param mixed      $value      The value that should be validated
     * @param Constraint $constraint The constraint for the validation
     *
     * @return Boolean Whether or not the value is valid
     *
     * @api
     */
    public function isValid($value, Constraint $constraint)
    {
        if (null === $value || '' === $value) {
            return true;
        }

        if (!is_scalar($value) && !(is_object($value) && method_exists($value, '__toString'))) {
            throw new UnexpectedTypeException($value, 'string');
        }

        $value = (string) $value;

        $length = function_exists('mb_strlen') ? mb_strlen($value, $constraint->charset) : strlen($value);

        if ($constraint->min == $constraint->max && $length != $constraint->max) {
            $this->setMessage($constraint->exactMessage, array(
                '{{ value }}' => $value,
                '{{ limit }}' => $constraint->max,
            ));

            return false;
        }

        if ($length > $constraint->max) {
            $this->setMessage($constraint->maxMessage, array(
                '{{ value }}' => $value,
                '{{ limit }}' => $constraint->max,
            ));

            return false;
        }

        if ($length < $constraint->min) {
            $this->setMessage($constraint->minMessage, array(
                '{{ value }}' => $value,
                '{{ limit }}' => $constraint->min,
            ));

            return false;
        }

        return true;
    }
}
