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
 * Validates whether a value is a valid locale number
 *
 * @author stealth35
 *
 * @api
 */
class NumberValidator extends ConstraintValidator
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

        $value     = (string) $value;
        $locale    = $constraint->locale ?: \Locale::getDefault();
        $formatter = new \NumberFormatter($locale, \NumberFormatter::DECIMAL);

        $authchars = array(
            $formatter->getSymbol(\NumberFormatter::DECIMAL_SEPARATOR_SYMBOL),
            $formatter->getSymbol(\NumberFormatter::GROUPING_SEPARATOR_SYMBOL),
            $formatter->getSymbol(\NumberFormatter::PLUS_SIGN_SYMBOL),
            $formatter->getSymbol(\NumberFormatter::MINUS_SIGN_SYMBOL),
        );

        $range = '0-9';
        $zero  = $formatter->format(0);
        $nine  = $formatter->format(9);

        if ($zero !== '0' && $nine !== '9') {
             $range .= $zero . '-' . $nine;
        }

        $pattern = '/[^' . $range . implode(array_map('preg_quote', $authchars)) . ']/';

        if (preg_match($pattern, $value) || false === $formatter->parse($value)) {
            $this->setMessage($constraint->message, array(
                '{{ value }}'    => $value,
                '{{ language }}' => \Locale::getDisplayLanguage($locale),
                '{{ format }}'   => $formatter->format(1234.56),
            ));

            return false;
        }

        return true;
    }
}
