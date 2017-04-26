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
 * Transforms between a normalized format (integer or float) and a telephone.
 *
 * @author Luis Cordova <cordoval@gmail.com>
 */
class TelToLocalizedStringTransformer implements DataTransformerInterface
{
    const SPACED = 'spaced';
    const DOTTED = 'dotted';

    protected static $formats = array(
        self::SPACED,
        self::DOTTED,
    );

    private $format;

    /**
     * Constructor.
     *
     * @see self::$formats for a list of supported formats
     *
     * @param string  $format      One of the supported formats
     *
     * @throws UnexpectedTypeException if the given value of format is unknown
     */
    public function __construct($format = null)
    {
        if (null === $format) {
            $format = self::SPACED;
        }

        if (!in_array($format, self::$formats, true)) {
            throw new UnexpectedTypeException($format, implode('", "', self::$formats));
        }

        $this->format = $format;
    }

    /**
     * Transforms between a normalized format (integer or float) into a tel value.
     *
     * @param number $value Normalized value
     *
     * @return string Tel value
     *
     * @throws TransformationFailedException If the given value is not numeric or
     *                                       if the value could not be transformed.
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

        // replace the UTF-8 non break spaces
        return $value;
    }

    /**
     * Transforms between a percentage value into a normalized format (integer or float).
     *
     * @param number $value Percentage value.
     *
     * @return number Normalized value.
     *
     * @throws TransformationFailedException If the given value is not a string or
     *                                       if the value could not be transformed.
     */
    public function reverseTransform($value)
    {
        if (!is_string($value)) {
            throw new TransformationFailedException('Expected a string.');
        }

        if ('' === $value) {
            return null;
        }

        $formatter = $this->getNumberFormatter();
        // replace normal spaces so that the formatter can read them
        $value = $formatter->parse(str_replace(' ', ' ', $value));

        if (intl_is_failure($formatter->getErrorCode())) {
            throw new TransformationFailedException($formatter->getErrorMessage());
        }

        if (self::FRACTIONAL == $this->type) {
            $value /= 100;
        }

        return $value;
    }

    /**
     * Returns a preconfigured \NumberFormatter instance
     *
     * @return \NumberFormatter
     */
    protected function getNumberFormatter()
    {
        $formatter = new \NumberFormatter(\Locale::getDefault(), \NumberFormatter::DECIMAL);

        $formatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, $this->precision);

        return $formatter;
    }
}
