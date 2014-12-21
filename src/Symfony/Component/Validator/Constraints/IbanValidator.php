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
 * @author Manuel Reinhard <manu@sprain.ch>
 * @author Michael Schummel
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @link http://www.michael-schummel.de/2007/10/05/iban-prufung-mit-php/
 */
class IbanValidator extends ConstraintValidator
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

        // Remove spaces
        $canonicalized = str_replace(' ', '', $value);

        if (strlen($canonicalized) < 4) {
            $this->context->addViolation($constraint->message, array(
                '{{ value }}' => $this->formatValue($value),
            ));

            return;
        }

        // The IBAN must have at least 4 characters, start with a country
        // code and contain only digits and (uppercase) characters
        if (strlen($canonicalized) < 4 || !ctype_upper($canonicalized{0})
            || !ctype_upper($canonicalized{1}) || !ctype_alnum($canonicalized)) {
            $this->context->addViolation($constraint->message, array(
                '{{ value }}' => $this->formatValue($value),
            ));

            return;
        }

        // Move the first four characters to the end
        // e.g. CH93 0076 2011 6238 5295 7
        //   -> 0076 2011 6238 5295 7 CH93
        $canonicalized = substr($canonicalized, 4).substr($canonicalized, 0, 4);

        // Convert all remaining letters to their ordinals
        // The result is an integer, which is too large for PHP's int
        // data type, so we store it in a string instead.
        // e.g. 0076 2011 6238 5295 7 CH93
        //   -> 0076 2011 6238 5295 7 121893
        $checkSum = $this->toBigInt($canonicalized);

        if (false === $checkSum) {
            $this->context->addViolation($constraint->message, array(
                '{{ value }}' => $this->formatValue($value),
            ));

            return;
        }

        // Do a modulo-97 operation on the large integer
        // We cannot use PHP's modulo operator, so we calculate the
        // modulo step-wisely instead
        if (1 !== $this->bigModulo97($checkSum)) {
            $this->context->addViolation($constraint->message, array(
                '{{ value }}' => $this->formatValue($value),
            ));

            return;
        }
    }

    private function toBigInt($string)
    {
        $chars = str_split($string);
        $bigInt = '';

        foreach ($chars as $char) {
            // Convert uppercase characters to ordinals, starting with 10 for "A"
            if (ctype_upper($char)) {
                $bigInt .= (ord($char) - 55);

                continue;
            }

            // Disallow lowercase characters
            if (ctype_lower($char)) {
                return false;
            }

            // Simply append digits
            $bigInt .= $char;
        }

        return $bigInt;
    }

    private function bigModulo97($bigInt)
    {
        $parts = str_split($bigInt, 7);
        $rest = 0;

        foreach ($parts as $part) {
            $rest = ($rest.$part) % 97;
        }

        return $rest;
    }
}
