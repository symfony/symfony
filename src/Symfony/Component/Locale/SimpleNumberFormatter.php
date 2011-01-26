<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Locale;

use Symfony\Component\Locale\Locale;
use Symfony\Component\Locale\NumberFormatterInterface;

/**
 * Provides a simple NumberFormatter for the 'en' locale.
 */
class SimpleNumberFormatter implements NumberFormatterInterface
{
    private $formatter = null;

    /**
     * @see http://source.icu-project.org/repos/icu/icu/trunk/source/data/curr/en.txt
     */
    private $currencies = array(
        'ALL' => array('0x410x4c0x4c', '%.0f'),
        'BRL' => array('0x520x24', '%.2f'),
        'CRC' => array('0xe20x820xa1', '%.0f')
    );

    /**
     * @{inheritDoc}
     */
    public function __construct($locale = 'en', $style = null, $pattern = null)
    {
    }

    /**
     * @{inheritDoc}
     */
    public function formatCurrency($value, $currency)
    {
        $symbol = '';
        $hexSymbol = $this->currencies[$currency][0];
        $format    = $this->currencies[$currency][1];

        $hex = explode('0x', $hexSymbol);
        unset($hex[0]);

        foreach ($hex as $h) {
            $symbol .= chr(hexdec($h));
        }

        return sprintf('%s'.$format, $symbol, $value);
    }

    /**
     * @{inheritDoc}
     */
    public function format($value, $type = null)
    {

    }

    /**
     * @{inheritDoc}
     */
    public function getAttribute($attr)
    {

    }

    /**
     * @{inheritDoc}
     */
    public function getErrorCode()
    {

    }

    /**
     * @{inheritDoc}
     */
    public function getErrorMessage()
    {

    }

    /**
     * @{inheritDoc}
     */
    public function getLocale($type = Locale::ACTUAL_LOCALE)
    {

    }

    /**
     * @{inheritDoc}
     */
    public function getPattern()
    {

    }

    /**
     * @{inheritDoc}
     */
    public function getSymbol($attr)
    {

    }

    /**
     * @{inheritDoc}
     */
    public function getTextAttribute($attr)
    {

    }

    /**
     * @{inheritDoc}
     */
    public function parseCurrency($value, &$currency, &$position = null)
    {

    }

    /**
     * @{inheritDoc}
     */
    public function parse($value, $type = self::TYPE_DOUBLE, &$position = null)
    {

    }

    /**
     * @{inheritDoc}
     */
    public function setAttribute($attr, $value)
    {

    }

    /**
     * @{inheritDoc}
     */
    public function setPattern($pattern)
    {

    }

    /**
     * @{inheritDoc}
     */
    public function setSymbol($attr, $value)
    {

    }

    /**
     * @{inheritDoc}
     */
    public function setTextAttribute($attr, $value)
    {

    }
}
