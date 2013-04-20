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

/**
 * Validates a PAN using the LUHN Algorithm
 *
 * For a list of example card numbers that are used to test this
 * class, please see the LuhnValidatorTest class.
 *
 * @see    http://en.wikipedia.org/wiki/Luhn_algorithm
 * @author Tim Nagel <t.nagel@infinite.net.au>
 * @author Greg Knapp http://gregk.me/2011/php-implementation-of-bank-card-luhn-algorithm/
 */
class LuhnValidator extends ConstraintValidator
{
    /**
     * Validates a creditcard number with the Luhn algorithm.
     *
     * @param mixed      $value
     * @param Constraint $constraint
     */
    public function validate($value, Constraint $constraint)
    {
        if (null === $value || '' === $value) {
            return;
        }

        if (!is_numeric($value)) {
            $this->context->addViolation($constraint->message);

            return;
        }

        $length = strlen($value);
        $oddLength = $length % 2;
        for ($sum = 0, $i = $length - 1; $i >= 0; $i--) {
            $digit = (int) $value[$i];
            $sum += (($i % 2) === $oddLength) ? array_sum(str_split($digit * 2)) : $digit;
        }

        if ($sum === 0 || ($sum % 10) !== 0) {
            $this->context->addViolation($constraint->message);
        }
    }
}
