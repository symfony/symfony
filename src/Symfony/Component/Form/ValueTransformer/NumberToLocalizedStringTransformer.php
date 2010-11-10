<?php

namespace Symfony\Component\Form\ValueTransformer;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

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
    const ROUND_FLOOR    = \NumberFormatter::ROUND_FLOOR;
    const ROUND_DOWN     = \NumberFormatter::ROUND_DOWN;
    const ROUND_HALFDOWN = \NumberFormatter::ROUND_HALFDOWN;
    const ROUND_HALFEVEN = \NumberFormatter::ROUND_HALFEVEN;
    const ROUND_HALFUP   = \NumberFormatter::ROUND_HALFUP;
    const ROUND_UP       = \NumberFormatter::ROUND_UP;
    const ROUND_CEILING  = \NumberFormatter::ROUND_CEILING;

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this->addOption('precision', null);
        $this->addOption('grouping', false);
        $this->addOption('rounding-mode', self::ROUND_HALFUP);

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
        if ($value === null) {
            return '';
        }

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
    public function reverseTransform($value, $originalValue)
    {
        if (!is_string($value)) {
            throw new \InvalidArgumentException(sprintf('Expected argument of type string, %s given', gettype($value)));
        }

        if ($value === '') {
            return null;
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
            $formatter->setAttribute(\NumberFormatter::ROUNDING_MODE, $this->getOption('rounding-mode'));
        }

        $formatter->setAttribute(\NumberFormatter::GROUPING_USED, $this->getOption('grouping'));

        return $formatter;
    }
}