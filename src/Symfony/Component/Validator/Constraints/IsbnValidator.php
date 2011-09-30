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

class IsbnValidator extends ConstraintValidator
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
        $valid = false;

        if (preg_match('#(978|979)?[\-\ ]?[0-9]{1,5}[\-\ ]?[0-9]{2,7}[\-\ ]?[0-9]{1,6}[\-\ ]?[0-9Xx]#', $value, $matches)) {
            $valueClean = str_replace(array('-', ' '), '', $value);
            if (isset($matches[1])) {
                $valid = $this->check13($valueClean);
            } else {
                $valid = $this->check10($valueClean);
            }
        }

        if (!$valid) {
            $this->setMessage($constraint->message, array('{{ value }}' => $value));

            return false;
        }

        return true;
    }

    /**
     * Checks if the given isbn10 number is correct
     *
     * @param string $value the cleaned isbn number without "-"
     * @return boolean
     */
    private function check10($value)
    {
        $checkDigit = $value[9];
        if ('x' == strtolower($checkDigit)) {
            $checkDigit = 10;
        }

        $checkSum = 0;
        for ($i=1; $i < 10; $i++) {
            $checkSum += $i * $value[$i - 1];
        }

        // check check digit
        if ($checkDigit != ($checkSum % 11)) {
            return false;
        }

        // check isbn
        $checkIsbn = $checkSum + (10 * $checkDigit);
        if (0 < ($checkIsbn % 11)) {
            return false;
        }

        return true;
    }

    /**
     * Checks if the given isbn13 number is correct
     *
     * @param string $value the cleaned isbn number without "-"
     * @return boolean
     */
    private function check13($value)
    {
        $checkDigit = $value[12];

        $checkSum = 0;
        for ($i=0; $i < 12; $i+=2) {
            $checkSum += $value[$i];
        }
        for ($i=1; $i < 12; $i+=2) {
            $checkSum += 3 * $value[$i];
        }

        // check check digit
        $checkDigitNumber = (int) substr($checkSum, -1, 1);
        if (0 < $checkDigitNumber && $checkDigit != (10 - $checkDigitNumber)) {
            return false;
        }

        // check isbn
        $checkIsbn = $checkSum + $checkDigit;
        if (0 < ($checkIsbn % 10)) {
            return false;
        }

        return true;
    }
}