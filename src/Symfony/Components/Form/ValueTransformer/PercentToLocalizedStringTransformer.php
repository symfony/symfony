<?php

namespace Symfony\Components\Form\ValueTransformer;

use \Symfony\Components\Form\ValueTransformer\ValueTransformerException;

/**
 * Transforms between a normalized format (integer or float) and a percentage value.
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 * @author Florian Eckerstorfer <florian@eckerstorfer.org>
 */
class PercentToLocalizedStringTransformer extends BaseValueTransformer
{
    const FRACTIONAL = 'fractional';
    const INTEGER = 'integer';

    protected static $types = array(
        self::FRACTIONAL,
        self::INTEGER,
    );

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this->addOption('type', self::FRACTIONAL);
        $this->addOption('precision', 0);

        if (!in_array($this->getOption('type'), self::$types, true)) {
            throw new \InvalidArgumentException(sprintf('The option "type" is expected to be one of "%s"', implode('", "', self::$types)));
        }

        parent::configure();
    }

    /**
     * Transforms between a normalized format (integer or float) into a percentage value.
     *
     * @param  number $value  Normalized value.
     * @return number         Percentage value.
     */
    public function transform($value)
    {
        if (!is_numeric($value)) {
            throw new \InvalidArgumentException(sprintf('Numeric argument expected, %s given', gettype($value)));
        }

        if (self::FRACTIONAL == $this->getOption('type')) {
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
     * @param  number $value  Percentage value.
     * @return number         Normalized value.
     */
    public function reverseTransform($value)
    {
        if (!is_string($value)) {
            throw new \InvalidArgumentException(sprintf('Expected argument of type string, %s given', gettype($value)));
        }

        $formatter = $this->getNumberFormatter();
        // replace normal spaces so that the formatter can read them
        $value = $formatter->parse(str_replace(' ', ' ', $value));

        if (intl_is_failure($formatter->getErrorCode())) {
            throw new TransformationFailedException($formatter->getErrorMessage());
        }

        if (self::FRACTIONAL == $this->getOption('type')) {
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
        $formatter = new \NumberFormatter($this->locale, \NumberFormatter::DECIMAL);

        $formatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, $this->getOption('precision'));

        return $formatter;
    }
}