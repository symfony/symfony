<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form;

use Symfony\Component\Form\ValueTransformer\NumberToLocalizedStringTransformer;

/**
 * A localized field for entering numbers.
 *
 * Available options:
 *
 *  * precision:        The number of digits to allow when rounding. Default
 *                      is locale-specific.
 *  * grouping:
 *  * rounding-mode:    The method to use to round to get to the needed level
 *                      of precision. Options include:
 *                       * NumberToLocalizedStringTransformer::ROUND_FLOOR
 *                       * NumberToLocalizedStringTransformer::ROUND_DOWN
 *                       * NumberToLocalizedStringTransformer::ROUND_HALFDOWN
 *                       * NumberToLocalizedStringTransformer::ROUND_HALFUP (default)
 *                       * NumberToLocalizedStringTransformer::ROUND_UP
 *                       * NumberToLocalizedStringTransformer::ROUND_CEILING
 *
 * @see \NumberFormatter
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
class NumberField extends Field
{
    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        // default precision is locale specific (usually around 3)
        $this->addOption('precision');
        $this->addOption('grouping', false);
        $this->addOption('rounding-mode', NumberToLocalizedStringTransformer::ROUND_HALFUP);

        parent::configure();

        $this->setValueTransformer(new NumberToLocalizedStringTransformer(array(
            'precision' => $this->getOption('precision'),
            'grouping' => $this->getOption('grouping'),
            'rounding-mode' => $this->getOption('rounding-mode'),
        )));
    }
}
