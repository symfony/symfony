<?php

/*
 * This file is part of the Symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form;

use Symfony\Component\Form\ValueTransformer\MoneyToLocalizedStringTransformer;

/**
 * A localized field for entering money values.
 *
 * This field will output the money with the correct comma, period or spacing
 * (e.g. 10,000) as well as the correct currency symbol in the correct location
 * (i.e. before or after the field).
 *
 * Available options:
 *
 *  * currency:     The currency to display the money with. This is the 3-letter
 *                  ISO 4217 currency code.
 *  * divisor:      A number to divide the money by before displaying. Default 1.
 *
 * @see Symfony\Component\Form\NumberField
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
class MoneyField extends NumberField
{
    /**
     * Stores patterns for different locales and cultures
     *
     * A pattern decides which currency symbol is displayed and where it is in
     * relation to the number.
     *
     * @var array
     */
    protected static $patterns = array();

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this->addOption('precision', 2);
        $this->addOption('divisor', 1);

        parent::configure();

        $this->setValueTransformer(new MoneyToLocalizedStringTransformer(array(
            'precision' => $this->getOption('precision'),
            'grouping' => $this->getOption('grouping'),
            'divisor' => $this->getOption('divisor'),
        )));
    }
}
