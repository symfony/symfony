<?php

namespace Symfony\Component\Form\ValueTransformer;

use \Symfony\Component\Form\ValueTransformer\ValueTransformerException;

/**
 * Transforms between a normalized format and a localized money string.
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 * @author Florian Eckerstorfer <florian@eckerstorfer.org>
 */
class MoneyToLocalizedStringTransformer extends NumberToLocalizedStringTransformer
{
    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this->addOption('grouping', true);
        $this->addOption('precision', 2);
        $this->addOption('divisor', 1);

        parent::configure();
    }

    /**
     * Transforms a normalized format into a localized money string.
     *
     * @param  number $value  Normalized number
     * @return string         Localized money string.
     */
    public function transform($value)
    {
        if (!is_numeric($value)) {
            throw new \InvalidArgumentException(sprintf('Numeric argument expected, %s given', gettype($value)));
        }

        return parent::transform($value / $this->getOption('divisor'));
    }

    /**
     * Transforms a localized money string into a normalized format.
     *
     * @param string $value Localized money string
     * @return number Normalized number
     */
    public function reverseTransform($value)
    {
        return parent::reverseTransform($value) * $this->getOption('divisor');
    }

}