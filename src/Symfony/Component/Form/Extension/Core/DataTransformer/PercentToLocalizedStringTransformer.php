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

    protected static $types = array(
        self::FRACTIONAL,
        self::INTEGER,
    );

    private $type;

    private $precision;

    /**
     * Constructor.
     *
     * @see self::$types for a list of supported types
     *
     * @param int    $precision The precision
     * @param string $type      One of the supported types
     *
     * @throws UnexpectedTypeException if the given value of type is unknown
     */
    public function __construct($precision = null, $type = null)
    {
        if (null === $precision) {
            $precision = 0;
        }

        if (null === $type) {
            $type = self::FRACTIONAL;
        }

        if (!in_array($type, self::$types, true)) {
            throw new UnexpectedTypeException($type, implode('", "', self::$types));
        }

        $this->type = $type;
        $this->precision = $precision;
    }

    /**
     * Transforms between a normalized format (integer or float) into a percentage value.
     *
     * @param int|float $value Normalized value
     *
     * @return string Percentage value
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
     * @param string $value Percentage value.
     *
     * @return int|float Normalized value.
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
            return;
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
