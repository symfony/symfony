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
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @see https://en.wikipedia.org/wiki/Isbn
 */
class IsbnValidator extends ConstraintValidator
{
    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint)
    {
        if (null === $value || '' === $value) {
            return;
        }

        if (!is_scalar($value) && !(is_object($value) && method_exists($value, '__toString'))) {
            throw new UnexpectedTypeException($value, 'string');
        }

        $value = (string) $value;
        $canonical = strtoupper(str_replace('-', '', $value));

        if ($constraint->isbn10 && $this->isValidIsbn10($canonical)) {
            return;
        }

        if ($constraint->isbn13 && $this->isValidIsbn13($canonical)) {
            return;
        }

        if ($constraint->isbn10 && $constraint->isbn13) {
            $this->context->addViolation($constraint->bothIsbnMessage, array(
                '{{ value }}' => $this->formatValue($value),
            ));
        } elseif ($constraint->isbn10) {
            $this->context->addViolation($constraint->isbn10Message, array(
                '{{ value }}' => $this->formatValue($value),
            ));
        } else {
            $this->context->addViolation($constraint->isbn13Message, array(
                '{{ value }}' => $this->formatValue($value),
            ));
        }
    }

    private function isValidIsbn10($isbn)
    {
        if (10 !== strlen($isbn)) {
            return false;
        }

        $checkSum = 0;

        for ($i = 0; $i < 10; ++$i) {
            if ('X' === $isbn{$i}) {
                $digit = 10;
            } elseif (ctype_digit($isbn{$i})) {
                $digit = $isbn{$i};
            } else {
                return false;
            }

            $checkSum += $digit * (10 - $i);
        }

        return 0 === $checkSum % 11;
    }

    private function isValidIsbn13($isbn)
    {
        if (13 !== strlen($isbn) || !ctype_digit($isbn)) {
            return false;
        }

        $checkSum = 0;

        for ($i = 0; $i < 13; $i += 2) {
            $checkSum += $isbn{$i};
        }

        for ($i = 1; $i < 12; $i += 2) {
            $checkSum += $isbn{$i}
            * 3;
        }

        return 0 === $checkSum % 10;
    }
}
