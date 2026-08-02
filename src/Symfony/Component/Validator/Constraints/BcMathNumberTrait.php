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

/**
 * @internal
 */
trait BcMathNumberTrait
{
    /**
     * Converts a numeric value to a BcMath\Number, so that comparing it with another
     * BcMath\Number keeps the arbitrary precision. PHP truncates the other operand to
     * an integer instead, which makes Number('10.2') greater than 10.5.
     *
     * Values that cannot be represented, such as INF and NAN, are returned unchanged.
     */
    private static function toBcMathNumber(mixed $value): mixed
    {
        if (!\is_int($value) && !(\is_float($value) && is_finite($value)) && !(\is_string($value) && is_numeric($value))) {
            return $value;
        }

        try {
            return new \BcMath\Number(self::toPlainDecimalNotation((string) $value));
        } catch (\ValueError) {
            return $value;
        }
    }

    /**
     * Rewrites the exponent notation BcMath\Number cannot parse, e.g. "1.0E-7" to "0.0000001".
     */
    private static function toPlainDecimalNotation(string $number): string
    {
        if (!preg_match('/^([+-]?)(\d*)(?:\.(\d*))?[eE]([+-]?\d+)$/', $number, $matches)) {
            return $number;
        }

        [, $sign, $integerPart, $fractionPart, $exponent] = $matches;

        $digits = $integerPart.$fractionPart;
        $point = \strlen($integerPart) + (int) $exponent;

        if ($point >= \strlen($digits)) {
            return $sign.$digits.str_repeat('0', $point - \strlen($digits));
        }

        if ($point <= 0) {
            $plain = '0.'.str_repeat('0', -$point).$digits;
        } else {
            $plain = substr($digits, 0, $point).'.'.substr($digits, $point);
        }

        // the trailing zeros of the mantissa would show up in violation messages
        return $sign.rtrim(rtrim($plain, '0'), '.');
    }
}
