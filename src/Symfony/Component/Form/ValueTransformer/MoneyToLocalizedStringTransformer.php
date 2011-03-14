<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\ValueTransformer;

use Symfony\Component\Form\Exception\UnexpectedTypeException;

/**
 * Transforms between a normalized format and a localized money string.
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
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
        if (null !== $value) {
            if (!is_numeric($value)) {
                throw new UnexpectedTypeException($value, 'numeric');
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
    public function reverseTransform($value)
    {
        $value = parent::reverseTransform($value);

        if (null !== $value) {
            $value *= $this->getOption('divisor');
        }

        return $value;
    }

}