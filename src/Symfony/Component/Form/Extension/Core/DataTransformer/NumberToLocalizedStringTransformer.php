<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Core\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * Transforms between a number type and a localized number with grouping
 * (each thousand) and comma separators.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Florian Eckerstorfer <florian@eckerstorfer.org>
 */
class NumberToLocalizedStringTransformer implements DataTransformerInterface
{
    const ROUND_FLOOR    = \NumberFormatter::ROUND_FLOOR;
    const ROUND_DOWN     = \NumberFormatter::ROUND_DOWN;
    const ROUND_HALFDOWN = \NumberFormatter::ROUND_HALFDOWN;
    const ROUND_HALFEVEN = \NumberFormatter::ROUND_HALFEVEN;
    const ROUND_HALFUP   = \NumberFormatter::ROUND_HALFUP;
    const ROUND_UP       = \NumberFormatter::ROUND_UP;
    const ROUND_CEILING  = \NumberFormatter::ROUND_CEILING;

    protected $precision;

    protected $grouping;

    protected $roundingMode;

    public function __construct($precision = null, $grouping = null, $roundingMode = null)
    {
        if (null === $grouping) {
            $grouping = false;
        }

        if (null === $roundingMode) {
            $roundingMode = self::ROUND_HALFUP;
        }

        $this->precision = $precision;
        $this->grouping = $grouping;
        $this->roundingMode = $roundingMode;
    }

    /**
     * Transforms a number type into localized number.
     *
     * @param int|float     $value Number value.
     *
     * @return string Localized value.
     *
     * @throws TransformationFailedException If the given value is not numeric
     *                                       or if the value can not be transformed.
     */
    public function transform($value)
    {
        if (null === $value) {
            return '';
        }

        if (!is_numeric($value)) {
            throw new TransformationFailedException('Expected a numeric.');
        }

        $formatter = $this->getNumberFormatter();
        $value = $formatter->format($value);

        if (intl_is_failure($formatter->getErrorCode())) {
            throw new TransformationFailedException($formatter->getErrorMessage());
        }

        // Convert fixed spaces to normal ones
        $value = str_replace("\xc2\xa0", ' ', $value);

        return $value;
    }

    /**
     * Transforms a localized number into an integer or float
     *
     * @param string $value The localized value
     *
     * @return int|float     The numeric value
     *
     * @throws TransformationFailedException If the given value is not a string
     *                                       or if the value can not be transformed.
     */
    public function reverseTransform($value)
    {
        if (!is_string($value)) {
            throw new TransformationFailedException('Expected a string.');
        }

        if ('' === $value) {
            return;
        }

        if ('NaN' === $value) {
            throw new TransformationFailedException('"NaN" is not a valid number');
        }

        $position = 0;
        $formatter = $this->getNumberFormatter();
        $groupSep = $formatter->getSymbol(\NumberFormatter::GROUPING_SEPARATOR_SYMBOL);
        $decSep = $formatter->getSymbol(\NumberFormatter::DECIMAL_SEPARATOR_SYMBOL);

        if ('.' !== $decSep && (!$this->grouping || '.' !== $groupSep)) {
            $value = str_replace('.', $decSep, $value);
        }

        if (',' !== $decSep && (!$this->grouping || ',' !== $groupSep)) {
            $value = str_replace(',', $decSep, $value);
        }

        $result = $formatter->parse($value, \NumberFormatter::TYPE_DOUBLE, $position);

        if (intl_is_failure($formatter->getErrorCode())) {
            throw new TransformationFailedException($formatter->getErrorMessage());
        }

        if ($result >= PHP_INT_MAX || $result <= -PHP_INT_MAX) {
            throw new TransformationFailedException('I don\'t have a clear idea what infinity looks like');
        }

        if (function_exists('mb_detect_encoding') && false !== $encoding = mb_detect_encoding($value)) {
            $length = mb_strlen($value, $encoding);
            $remainder = mb_substr($value, $position, $length, $encoding);
        } else {
            $length = strlen($value);
            $remainder = substr($value, $position, $length);
        }

        // After parsing, position holds the index of the character where the
        // parsing stopped
        if ($position < $length) {
            // Check if there are unrecognized characters at the end of the
            // number (excluding whitespace characters)
            $remainder = trim($remainder, " \t\n\r\0\x0b\xc2\xa0");

            if ('' !== $remainder) {
                throw new TransformationFailedException(
                    sprintf('The number contains unrecognized characters: "%s"', $remainder)
                );
            }
        }

        return $result;
    }

    /**
     * Returns a preconfigured \NumberFormatter instance
     *
     * @return \NumberFormatter
     */
    protected function getNumberFormatter()
    {
        $formatter = new \NumberFormatter(\Locale::getDefault(), \NumberFormatter::DECIMAL);

        if (null !== $this->precision) {
            $formatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, $this->precision);
            $formatter->setAttribute(\NumberFormatter::ROUNDING_MODE, $this->roundingMode);
        }

        $formatter->setAttribute(\NumberFormatter::GROUPING_USED, $this->grouping);

        return $formatter;
    }
}
