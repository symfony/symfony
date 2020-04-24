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
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * @author Laurent Masforné <l.masforne@gmail.com>
 *
 * @see https://en.wikipedia.org/wiki/International_Securities_Identification_Number
 */
class IsinValidator extends ConstraintValidator
{
    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof Isin) {
            throw new UnexpectedTypeException($constraint, Isin::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!is_scalar($value) && !(\is_object($value) && method_exists($value, '__toString'))) {
            throw new UnexpectedValueException($value, 'string');
        }

        $value = strtoupper($value);

        if (Isin::VALIDATION_LENGTH !== \strlen($value)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setCode(Isin::INVALID_LENGTH_ERROR)
                ->addViolation();

            return;
        }

        if (!preg_match(Isin::VALIDATION_PATTERN, $value)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setCode(Isin::INVALID_PATTERN_ERROR)
                ->addViolation();

            return;
        }

        if (!$this->isCorrectChecksum($value)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setCode(Isin::INVALID_CHECKSUM_ERROR)
                ->addViolation();

            return;
        }

        return $value;
    }

    private function isCorrectChecksum($input)
    {
        $characters = str_split($input);
        foreach ($characters as $i => $char) {
            $characters[$i] = \intval($char, 36);
        }
        $checkDigit = array_pop($characters);
        $number = implode('', $characters);
        $expectedCheckDigit = $this->getCheckDigit($number);

        return $checkDigit === $expectedCheckDigit;
    }

    /**
     * This method performs the luhn algorithm
     * to obtain a check digit.
     */
    private function getCheckDigit($input)
    {
        // first split up the string
        $numbers = str_split($input);

        // calculate the positional value.
        // when there is an even number of digits the second group will be multiplied, so p starts on 0
        // when there is an odd number of digits the first group will be multiplied, so p starts on 1
        $p = \count($numbers) % 2;
        // run through each number
        foreach ($numbers as $i => $num) {
            $num = (int) $num;
            // every positional number needs to be multiplied by 2
            if ($p % 2) {
                $num = $num * 2;
                // if the result was more than 9
                // add the individual digits
                $num = array_sum(str_split($num));
            }
            $numbers[$i] = $num;
            ++$p;
        }

        // get the total value of all the digits
        $sum = array_sum($numbers);

        // get the remainder when dividing by 10
        $mod = $sum % 10;

        // subtract from 10
        $rem = 10 - $mod;

        // mod from 10 to catch if the result was 0
        $digit = $rem % 10;

        return $digit;
    }
}
