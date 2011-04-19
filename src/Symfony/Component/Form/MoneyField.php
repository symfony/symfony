<?php

/*
 * This file is part of the Symfony package.
 * (c) Fabien Potencier <fabien@symfony.com>
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
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
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
        $this->addRequiredOption('currency');
        $this->addOption('precision', 2);
        $this->addOption('divisor', 1);

        parent::configure();

        $this->setValueTransformer(new MoneyToLocalizedStringTransformer(array(
            'precision' => $this->getOption('precision'),
            'grouping' => $this->getOption('grouping'),
            'divisor' => $this->getOption('divisor'),
        )));
    }

    /**
     * Returns the pattern for this locale
     *
     * The pattern contains the placeholder "{{ widget }}" where the HTML tag should
     * be inserted
     */
    public function getPattern()
    {
        if (!$this->getOption('currency')) {
            return '{{ widget }}';
        }

        if (!isset(self::$patterns[\Locale::getDefault()])) {
            self::$patterns[\Locale::getDefault()] = array();
        }

        if (!isset(self::$patterns[\Locale::getDefault()][$this->getOption('currency')])) {
            $format = new \NumberFormatter(\Locale::getDefault(), \NumberFormatter::CURRENCY);
            $pattern = $format->formatCurrency('123', $this->getOption('currency'));

            // the spacings between currency symbol and number are ignored, because
            // a single space leads to better readability in combination with input
            // fields

            // the regex also considers non-break spaces (0xC2 or 0xA0 in UTF-8)

            preg_match('/^([^\s\xc2\xa0]*)[\s\xc2\xa0]*123[,.]00[\s\xc2\xa0]*([^\s\xc2\xa0]*)$/', $pattern, $matches);

            if (!empty($matches[1])) {
                self::$patterns[\Locale::getDefault()] = $matches[1].' {{ widget }}';
            } else if (!empty($matches[2])) {
                self::$patterns[\Locale::getDefault()] = '{{ widget }} '.$matches[2];
            } else {
                self::$patterns[\Locale::getDefault()] = '{{ widget }}';
            }
        }

        return self::$patterns[\Locale::getDefault()];
    }
}
