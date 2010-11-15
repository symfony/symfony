<?php

namespace Symfony\Component\Form;

use Symfony\Component\Form\ValueTransformer\MoneyToLocalizedStringTransformer;

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * A localized field for entering money values
 *
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
        $this->addOption('currency');

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

        if (!isset(self::$patterns[$this->locale])) {
            self::$patterns[$this->locale] = array();
        }

        if (!isset(self::$patterns[$this->locale][$this->getOption('currency')])) {
            $format = new \NumberFormatter($this->locale, \NumberFormatter::CURRENCY);
            $pattern = $format->formatCurrency('123', $this->getOption('currency'));

            // the spacings between currency symbol and number are ignored, because
            // a single space leads to better readability in combination with input
            // fields

            // the regex also considers non-break spaces (0xC2 or 0xA0 in UTF-8)

            preg_match('/^([^\s\xc2\xa0]*)[\s\xc2\xa0]*123[,.]00[\s\xc2\xa0]*([^\s\xc2\xa0]*)$/', $pattern, $matches);

            if (!empty($matches[1])) {
                self::$patterns[$this->locale] = $matches[1].' {{ widget }}';
            } else if (!empty($matches[2])) {
                self::$patterns[$this->locale] = '{{ widget }} '.$matches[2];
            } else {
                self::$patterns[$this->locale] = '{{ widget }}';
            }
        }

        return self::$patterns[$this->locale];
    }
}
