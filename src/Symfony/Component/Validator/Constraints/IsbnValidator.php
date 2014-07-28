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
 * Validates whether the value is a valid ISBN-10 or ISBN-13
 *
 * @author The Whole Life To Learn <thewholelifetolearn@gmail.com>
 * @author Manuel Reinhard <manu@sprain.ch>
 * @see https://en.wikipedia.org/wiki/Isbn
 */
class IsbnValidator extends ConstraintValidator
{
    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof Isbn) {
            throw new UnexpectedTypeException($constraint, __NAMESPACE__.'\Isbn');
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!is_scalar($value) && !(is_object($value) && method_exists($value, '__toString'))) {
            throw new UnexpectedTypeException($value, 'string');
        }

        if (!is_numeric($value)) {
            $value = str_replace('-', '', $value);
        }

        if (null == $constraint->type) {
            if ($constraint->isbn10 && !$constraint->isbn13) {
                $constraint->type = 'isbn10';
                $value = strtoupper($value);
            } elseif ($constraint->isbn13 && !$constraint->isbn10) {
                $constraint->type = 'isbn13';
                $value = strtoupper($value);
            }
        }

        if ('isbn10' === $constraint->type) {
            if (!$this->validateIsbn10($value)) {
                $this->context->addViolation($this->getMessage($constraint, 'isbn10'));

                return;
            }
        } elseif ('isbn13' === $constraint->type) {
            if (!$this->validateIsbn13($value)) {
                $this->context->addViolation($this->getMessage($constraint, 'isbn13'));

                return;
            }
        } else {
            if (!$this->validateIsbn10($value) && !$this->validateIsbn13($value)) {
                $this->context->addViolation($this->getMessage($constraint));

                return;
            }
        }
    }

    protected function validateIsbn10($value)
    {
        $validation  = 0;
        $valueLength = strlen($value);

        if (10 === $valueLength) {
            for ($i = 0; $i < 10; $i++) {
                if ($value[$i] == 'X') {
                    $validation += 10 * intval(10 - $i);
                } else {
                    $validation += intval($value[$i]) * intval(10 - $i);
                }
            }

            if ($validation % 11 != 0) {
                return false;
            } else {
                return true;
            }
        }

        return false;
    }

    protected function validateIsbn13($value)
    {
        $validation  = 0;
        $valueLength = strlen($value);

        if (13 === $valueLength) {
            for ($i = 0; $i < 13; $i += 2) {
                $validation += intval($value[$i]);
            }
            for ($i = 1; $i < 12; $i += 2) {
                $validation += intval($value[$i]) * 3;
            }

            if ($validation % 10 != 0) {
                return false;
            } else {
                return true;
            }
        }

        return false;
    }

    protected function getMessage($constraint, $type=null)
    {
        if (null !== $constraint->message) {
            return $constraint->message;
        } elseif ($type == 'isbn10') {
            return $constraint->isbn10Message;
        } elseif ($type == 'isbn13') {
            return $constraint->isbn13Message;
        } else {
            return $constraint->bothIsbnMessage;
        }
    }
}
