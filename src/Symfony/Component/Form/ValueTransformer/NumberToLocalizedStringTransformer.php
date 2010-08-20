<?php

namespace Symfony\Component\Form\ValueTransformer;

use \Symfony\Component\Form\ValueTransformer\ValueTransformerException;

/**
 * Transforms between a number type and a localized number with grouping
 * (each thousand) and comma separators.
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 * @author Florian Eckerstorfer <florian@eckerstorfer.org>
 */
class NumberToLocalizedStringTransformer extends BaseValueTransformer
{
    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this->addOption('precision', null);
        $this->addOption('grouping', false);

        parent::configure();
    }

    /**
     * Transforms a number type into localized number.
     *
     * @param  number $value  Number value.
     * @return string         Localized value.
     */
    public function transform($value)
    {
        if (!is_numeric($value)) {
            throw new \InvalidArgumentException(sprintf('Numeric argument expected, %s given', gettype($value)));
        }

        $formatter = $this->getNumberFormatter();
        $value = $formatter->format($value);

        if (intl_is_failure($formatter->getErrorCode())) {
            throw new TransformationFailedException($formatter->getErrorMessage());
        }

        return $value;
    }

    /**
     * Transforms a localized number into an integer or float
     *
     * @param string $value
     */
    public function reverseTransform($value)
    {
        if (!is_string($value)) {
            throw new \InvalidArgumentException(sprintf('Expected argument of type string, %s given', gettype($value)));
        }

        $formatter = $this->getNumberFormatter();
        $value = $formatter->parse($value);

        if (intl_is_failure($formatter->getErrorCode())) {
            throw new TransformationFailedException($formatter->getErrorMessage());
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

        if ($this->getOption('precision') !== null) {
            $formatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, $this->getOption('precision'));
        }

        $formatter->setAttribute(\NumberFormatter::GROUPING_USED, $this->getOption('grouping'));

        return $formatter;
    }
}