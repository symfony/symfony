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
use Symfony\Component\Form\Exception\UnexpectedTypeException;

/**
 * Transforms between a normalized format (integer or float) and a percentage value.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Florian Eckerstorfer <florian@eckerstorfer.org>
 */
class PercentToLocalizedStringTransformer implements DataTransformerInterface
{
    public const FRACTIONAL = 'fractional';
    public const INTEGER = 'integer';

    protected static $types = [
        self::FRACTIONAL,
        self::INTEGER,
    ];

    private $roundingMode;
    private $type;
    private $scale;
    private $html5Format;

    /**
     * @see self::$types for a list of supported types
     *
     * @param int|null $roundingMode A value from \NumberFormatter, such as \NumberFormatter::ROUND_HALFUP
     * @param bool     $html5Format  Use an HTML5 specific format, see https://www.w3.org/TR/html51/sec-forms.html#date-time-and-number-formats
     *
     * @throws UnexpectedTypeException if the given value of type is unknown
     */
    public function __construct(?int $scale = null, ?string $type = null, ?int $roundingMode = null, bool $html5Format = false)
    {
        if (null === $type) {
            $type = self::FRACTIONAL;
        }

        if (null === $roundingMode && (\func_num_args() < 4 || func_get_arg(3))) {
            trigger_deprecation('symfony/form', '5.1', 'Not passing a rounding mode to "%s()" is deprecated. Starting with Symfony 6.0 it will default to "\NumberFormatter::ROUND_HALFUP".', __METHOD__);
        }

        if (!\in_array($type, self::$types, true)) {
            throw new UnexpectedTypeException($type, implode('", "', self::$types));
        }

        $this->type = $type;
        $this->scale = $scale ?? 0;
        $this->roundingMode = $roundingMode;
        $this->html5Format = $html5Format;
    }

    /**
     * Transforms between a normalized format (integer or float) into a percentage value.
     *
     * @param int|float $value Normalized value
     *
     * @return string
     *
     * @throws TransformationFailedException if the given value is not numeric or
     *                                       if the value could not be transformed
     */
    public function transform($value)
    {
        if (null === $value) {
            return '';
        }

        if (!is_numeric($value)) {
            throw new TransformationFailedException('Expected a numeric.');
        }

        if (self::FRACTIONAL == $this->type) {
            $value *= 100;
        }

        $formatter = $this->getNumberFormatter();
        $value = $formatter->format($value);

        if (intl_is_failure($formatter->getErrorCode())) {
            throw new TransformationFailedException($formatter->getErrorMessage());
        }

        // replace the UTF-8 non break spaces
        return $value;
    }

    /**
     * Transforms between a percentage value into a normalized format (integer or float).
     *
     * @param string $value Percentage value
     *
     * @return int|float|null
     *
     * @throws TransformationFailedException if the given value is not a string or
     *                                       if the value could not be transformed
     */
    public function reverseTransform($value)
    {
        if (!\is_string($value)) {
            throw new TransformationFailedException('Expected a string.');
        }

        if ('' === $value) {
            return null;
        }

        $position = 0;
        $formatter = $this->getNumberFormatter();
        $groupSep = $formatter->getSymbol(\NumberFormatter::GROUPING_SEPARATOR_SYMBOL);
        $decSep = $formatter->getSymbol(\NumberFormatter::DECIMAL_SEPARATOR_SYMBOL);
        $grouping = $formatter->getAttribute(\NumberFormatter::GROUPING_USED);

        if ('.' !== $decSep && (!$grouping || '.' !== $groupSep)) {
            $value = str_replace('.', $decSep, $value);
        }

        if (',' !== $decSep && (!$grouping || ',' !== $groupSep)) {
            $value = str_replace(',', $decSep, $value);
        }

        if (str_contains($value, $decSep)) {
            $type = \NumberFormatter::TYPE_DOUBLE;
        } else {
            $type = \PHP_INT_SIZE === 8 ? \NumberFormatter::TYPE_INT64 : \NumberFormatter::TYPE_INT32;
        }

        try {
            // replace normal spaces so that the formatter can read them
            $result = @$formatter->parse(str_replace(' ', "\xc2\xa0", $value), $type, $position);
        } catch (\IntlException $e) {
            throw new TransformationFailedException($e->getMessage(), 0, $e);
        }

        if (intl_is_failure($formatter->getErrorCode())) {
            throw new TransformationFailedException($formatter->getErrorMessage(), $formatter->getErrorCode());
        }

        if (self::FRACTIONAL == $this->type) {
            $result /= 100;
        }

        if (\function_exists('mb_detect_encoding') && false !== $encoding = mb_detect_encoding($value, null, true)) {
            $length = mb_strlen($value, $encoding);
            $remainder = mb_substr($value, $position, $length, $encoding);
        } else {
            $length = \strlen($value);
            $remainder = substr($value, $position, $length);
        }

        // After parsing, position holds the index of the character where the
        // parsing stopped
        if ($position < $length) {
            // Check if there are unrecognized characters at the end of the
            // number (excluding whitespace characters)
            $remainder = trim($remainder, " \t\n\r\0\x0b\xc2\xa0");

            if ('' !== $remainder) {
                throw new TransformationFailedException(sprintf('The number contains unrecognized characters: "%s".', $remainder));
            }
        }

        return $this->round($result);
    }

    /**
     * Returns a preconfigured \NumberFormatter instance.
     *
     * @return \NumberFormatter
     */
    protected function getNumberFormatter()
    {
        // Values used in HTML5 number inputs should be formatted as in "1234.5", ie. 'en' format without grouping,
        // according to https://www.w3.org/TR/html51/sec-forms.html#date-time-and-number-formats
        $formatter = new \NumberFormatter($this->html5Format ? 'en' : \Locale::getDefault(), \NumberFormatter::DECIMAL);

        if ($this->html5Format) {
            $formatter->setAttribute(\NumberFormatter::GROUPING_USED, 0);
        }

        $formatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, $this->scale);

        if (null !== $this->roundingMode) {
            $formatter->setAttribute(\NumberFormatter::ROUNDING_MODE, $this->roundingMode);
        }

        return $formatter;
    }

    /**
     * Rounds a number according to the configured scale and rounding mode.
     *
     * @param int|float $number A number
     *
     * @return int|float
     */
    private function round($number)
    {
        if (null !== $this->scale && null !== $this->roundingMode) {
            // shift number to maintain the correct scale during rounding
            $roundingCoef = 10 ** $this->scale;

            if (self::FRACTIONAL == $this->type) {
                $roundingCoef *= 100;
            }

            // string representation to avoid rounding errors, similar to bcmul()
            $number = (string) ($number * $roundingCoef);

            switch ($this->roundingMode) {
                case \NumberFormatter::ROUND_CEILING:
                    $number = ceil($number);
                    break;
                case \NumberFormatter::ROUND_FLOOR:
                    $number = floor($number);
                    break;
                case \NumberFormatter::ROUND_UP:
                    $number = $number > 0 ? ceil($number) : floor($number);
                    break;
                case \NumberFormatter::ROUND_DOWN:
                    $number = $number > 0 ? floor($number) : ceil($number);
                    break;
                case \NumberFormatter::ROUND_HALFEVEN:
                    $number = round($number, 0, \PHP_ROUND_HALF_EVEN);
                    break;
                case \NumberFormatter::ROUND_HALFUP:
                    $number = round($number, 0, \PHP_ROUND_HALF_UP);
                    break;
                case \NumberFormatter::ROUND_HALFDOWN:
                    $number = round($number, 0, \PHP_ROUND_HALF_DOWN);
                    break;
            }

            $number = 1 === $roundingCoef ? (int) $number : $number / $roundingCoef;
        }

        return $number;
    }
}
