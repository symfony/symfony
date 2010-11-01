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
        if ($value !== null) {
            if (!is_numeric($value)) {
                throw new \InvalidArgumentException(sprintf('Numeric argument expected, %s given', gettype($value)));
            }

            $value /= $this->getOption('divisor');
        }

        return parent::transform($value);
    }

    /**
     * Transforms a localized money string into a normalized format.
     *
     * @param string $value Localized money string
     * @return number Normalized number
     */
    public function reverseTransform($value, $originalValue)
    {
        $value = parent::reverseTransform($value, $originalValue);

        if ($value !== null) {
            $value *= $this->getOption('divisor');
        }

        return $value;
    }

}