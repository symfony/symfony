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

use Symfony\Component\Form\ValueTransformer\PercentToLocalizedStringTransformer;

/**
 * A localized field for entering percentage values.
 *
 * The percentage is always rendered in its large format (e.g. 75, not .75).
 *
 * Available options:
 *
 *  * percent_type:     How the source number is stored on the object
 *                       * self::FRACTIONAL (e.g. stored as .75)
 *                       * self::INTEGER (e.g. stored as 75)
 *
 * By default, the precision option is set to 0, meaning that decimal integer
 * values will be rounded using the method specified in the rounding-mode
 * option.
 *
 * @see Symfony\Component\Form\NumberField
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
class PercentField extends NumberField
{
    const FRACTIONAL = 'fractional';
    const INTEGER = 'integer';

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this->addOption('precision', 0);
        $this->addOption('percent_type', self::FRACTIONAL);

        parent::configure();

        $this->setValueTransformer(new PercentToLocalizedStringTransformer(array(
            'precision' => $this->getOption('precision'),
            'type' => $this->getOption('percent_type'),
        )));
    }
}
