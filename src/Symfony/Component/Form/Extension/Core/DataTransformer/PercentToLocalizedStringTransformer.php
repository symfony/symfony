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
    const FRACTIONAL = 'fractional';
    const INTEGER = 'integer';

    protected static $types = [
        self::FRACTIONAL,
        self::INTEGER,
    ];

    private $type;
    private $scale;

    /**
     * @see self::$types for a list of supported types
     *
     * @param int    $scale The scale
     * @param string $type  One of the supported types
     *
     * @throws UnexpectedTypeException if the given value of type is unknown
     */
    public function __construct($scale = null, $type = null)
    {
        if (null === $scale) {
            $scale = 0;
        }

        if (null === $type) {
            $type = self::FRACTIONAL;
        }

        if (!\in_array($type, self::$types, true)) {
            throw new UnexpectedTypeException($type, implode('", "', self::$types));
        }

        $this->type = $type;
        $this->scale = $scale;
    }

    /**
     * Transforms between a normalized format (integer or float) into a percentage value.
     *
     * @param int|float $value Normalized value
     *
     * @return string Percentage value
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
     * @return int|float Normalized value
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

        if (false !== strpos($value, $decSep)) {
            $type = \NumberFormatter::TYPE_DOUBLE;
        } else {
            $type = \PHP_INT_SIZE === 8 ? \NumberFormatter::TYPE_INT64 : \NumberFormatter::TYPE_INT32;
        }

        // replace normal spaces so that the formatter can read them
        $result = $formatter->parse(str_replace(' ', "\xc2\xa0", $value), $type, $position);

        if (intl_is_failure($formatter->getErrorCode())) {
            throw new TransformationFailedException($formatter->getErrorMessage());
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

        return $result;
    }

    /**
     * Returns a preconfigured \NumberFormatter instance.
     *
     * @return \NumberFormatter
     */
    protected function getNumberFormatter()
    {
        $formatter = new \NumberFormatter(\Locale::getDefault(), \NumberFormatter::DECIMAL);

        $formatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, $this->scale);

        return $formatter;
    }
}
