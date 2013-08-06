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
 * Validates whether the value is a valid ISBN-10 or ISBN-13.
 *
 * @author The Whole Life To Learn <thewholelifetolearn@gmail.com>
 *
 * @see https://en.wikipedia.org/wiki/Isbn
 */
class IsbnValidator extends ConstraintValidator
{
    /**
     * {@inheritDoc}
     */
    public function validate($value, Constraint $constraint)
    {
        if (null === $value || '' === $value) {
            return;
        }

        if (!is_scalar($value) && !(is_object($value) && method_exists($value, '__toString'))) {
            throw new UnexpectedTypeException($value, 'string');
        }

        if (!is_numeric($value)) {
            $value = str_replace('-', '', $value);
        }

        $validation = 0;
        $value = strtoupper($value);
        $valueLength = strlen($value);

        if (10 === $valueLength && null !== $constraint->isbn10) {
            for ($i = 0; $i < 10; $i++) {
                if ($value[$i] == 'X') {
                    $validation += 10 * intval(10 - $i);
                } else {
                    $validation += intval($value[$i]) * intval(10 - $i);
                }
            }

            if ($validation % 11 != 0) {
                if (null !== $constraint->isbn13) {
                    $this->context->addViolation($constraint->bothIsbnMessage);
                } else {
                    $this->context->addViolation($constraint->isbn10Message);
                }
            }
        } elseif (13 === $valueLength && null !== $constraint->isbn13) {
            for ($i = 0; $i < 13; $i += 2) {
                $validation += intval($value[$i]);
            }
            for ($i = 1; $i < 12; $i += 2) {
                $validation += intval($value[$i]) * 3;
            }

            if ($validation % 10 != 0) {
                if (null !== $constraint->isbn10) {
                    $this->context->addViolation($constraint->bothIsbnMessage);
                } else {
                    $this->context->addViolation($constraint->isbn13Message);
                }
            }
        } else {
            if (null !== $constraint->isbn10 && null !== $constraint->isbn13) {
                $this->context->addViolation($constraint->bothIsbnMessage);
            } elseif (null !== $constraint->isbn10) {
                $this->context->addViolation($constraint->isbn10Message);
            } else {
                $this->context->addViolation($constraint->isbn13Message);
            }
        }
    }
}
