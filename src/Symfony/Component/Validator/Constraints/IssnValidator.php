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
        if (null === $value || '' === $value) {
            return;
        }

        if (!is_scalar($value) && !(is_object($value) && method_exists($value, '__toString'))) {
            throw new UnexpectedTypeException($value, 'string');
        }

        $value = (string) $value;

        // Compose regex pattern
        $digitsPattern = $constraint->requireHyphen ? '\d{4}-\d{3}' : '\d{4}-?\d{3}';
        $checkSumPattern = $constraint->caseSensitive ? '[\d|X]' : '[\d|X|x]';
        $pattern = '/^'.$digitsPattern.$checkSumPattern.'$/';

        if (!preg_match($pattern, $value)) {
            $this->context->addViolation($constraint->message, array(
                '{{ value }}' => $this->formatValue($value),
            ));

            return;
        }

        $canonical = strtoupper(str_replace('-', '', $value));

        // Calculate a checksum. "X" equals 10.
        $checkSum = 'X' === $canonical{7}
        ? 10 : $canonical{7};

        for ($i = 0; $i < 7; ++$i) {
            // Multiply the first digit by 8, the second by 7, etc.
            $checkSum += (8 - $i) * $canonical{$i};
        }

        if (0 !== $checkSum % 11) {
            $this->context->addViolation($constraint->message, array(
                '{{ value }}' => $this->formatValue($value),
            ));
        }
    }
}
