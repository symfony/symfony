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
 * Validates whether the value is a valid ISSN.
 *
 * @author Antonio J. García Lagar <aj@garcialagar.es>
 *
 * @see https://en.wikipedia.org/wiki/Issn
 */
class IssnValidator extends ConstraintValidator
{
    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof Issn) {
            throw new UnexpectedTypeException($constraint, __NAMESPACE__.'\Issn');
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!is_scalar($value) && !(is_object($value) && method_exists($value, '__toString'))) {
            throw new UnexpectedTypeException($value, 'string');
        }

        // Compose regex pattern
        $digitsPattern = $constraint->requireHyphen ? '\d{4}-\d{3}' : '\d{4}-?\d{3}';
        $checksumPattern = $constraint->caseSensitive ? '[\d|X]' : '[\d|X|x]';
        $pattern = "/^".$digitsPattern.$checksumPattern."$/";

        if (!preg_match($pattern, $value)) {
            $this->context->addViolation($constraint->message);
        } else {
            $digits = str_split(strtoupper(str_replace('-', '', $value)));

            $sum = 0;
            for ($i = 8; $i > 1; $i--) {
                $sum += $i * (int) array_shift($digits);
            }

            $checksum = 'X' == reset($digits) ? 10 : (int) reset($digits);

            if (0 != ($sum + $checksum) % 11) {
                $this->context->addViolation($constraint->message);
            }
        }
    }
}
