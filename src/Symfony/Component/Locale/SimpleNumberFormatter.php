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
    /**
     * Default values for the en locale.
     */
    private $attributes = array(
        self::FRACTION_DIGITS => 0,
        self::GROUPING_USED   => 1,
        self::ROUNDING_MODE   => self::ROUND_HALFEVEN
    );

    /**
     * The supported styles to the constructor $styles argument.
     */
    private static $supportedStyles = array(
        'CURRENCY' => self::CURRENCY,
        'DECIMAL'  => self::DECIMAL
    );

    /**
     * Supported attributes to the setAttribute() $attr argument.
     */
    private static $supportedAttributes = array(
        'FRACTION_DIGITS' => self::FRACTION_DIGITS,
        'GROUPING_USED'   => self::GROUPING_USED,
        'ROUNDING_MODE'   => self::ROUNDING_MODE
    );

    /**
     * The available rounding modes for setAttribute() usage with
     * SimpleNumberFormatter::ROUNDING_MODE. SimpleNumberFormatter::ROUND_DOWN
     * and SimpleNumberFormatter::ROUND_UP does not have a PHP only equivalent.
     */
    private static $roundingModes = array(
        'ROUND_HALFEVEN' => self::ROUND_HALFEVEN,
        'ROUND_HALFDOWN' => self::ROUND_HALFDOWN,
        'ROUND_HALFUP'   => self::ROUND_HALFUP
    );

    /**
     * The available values for setAttribute() usage with
     * SimpleNumberFormatter::GROUPING_USED.
     */
    private static $groupingUsedValues = array(0, 1);

    /**
     * The mapping between \NumberFormatter rounding modes to the available
     * modes in PHP's round() function.
     *
     * @see http://www.php.net/manual/en/function.round.php
     */
    private static $phpRoundingMap = array(
        self::ROUND_HALFDOWN => \PHP_ROUND_HALF_DOWN,
        self::ROUND_HALFEVEN => \PHP_ROUND_HALF_EVEN,
        self::ROUND_HALFUP   => \PHP_ROUND_HALF_UP
    );

    /**
     * The currencies symbols. Each array have the symbol definition in
     * hexadecimal and the decimal digits.
     *
     * @see  http://source.icu-project.org/repos/icu/icu/trunk/source/data/curr/en.txt
     * @see  SimpleNumberFormatter::getCurrencySymbol()
     * @see  SimpleNumberFormatter::formatCurrency()
     * @todo Move this to Resources/data and use \ResourceBundle to load the data.
     * @todo Search in the icu data where the currency subunits (usage of cents) are defined
     */
    private $currencies = array(
        'ALL' => array('0x410x4c0x4c', 0),
        'BRL' => array('0x520x24', 2),
        'CRC' => array('0xe20x820xa1', 0)
    );

    /**
     * The maximum values of the integer type in 32 and 64 bit platforms.
     */
    private static $intValues = array(
        self::TYPE_INT32 => array(
            'positive' => 2147483647,
            'negative' => -2147483648
        ),
        self::TYPE_INT64 => array(
            'positive' => 9223372036854775807,
            'negative' => -9223372036854775808
        )
    );

    /**
     * @{inheritDoc}
     */
    public function __construct($locale = 'en', $style = null, $pattern = null)
    {
        if ('en' != $locale) {
            throw new \InvalidArgumentException('Unsupported $locale value. Only the \'en\' locale is supported. Install the intl extension for full localization capabilities.');
        }

        if (!in_array($style, self::$supportedStyles)) {
            throw new \InvalidArgumentException(sprintf(
                'Unsupported $style value. The available styles are: %s. Install the intl extension for full localization capabilities.',
                implode(', ', array_keys(self::$supportedStyles))
            ));
        }

        if (!is_null($pattern)) {
            throw new \InvalidArgumentException('The $pattern value must be null. Install the intl extension for full localization capabilities.');
        }
    }

    /**
     * @{inheritDoc}
     */
    public function formatCurrency($value, $currency)
    {
        $symbol = $this->getCurrencySymbol($currency);
        $value  = $this->round($value, $this->currencies[$currency][1]);

        $negative = false;
        if (0 > $value) {
            $negative = true;
            $value *= -1;
        }

        $value = $this->formatNumber($value, $this->currencies[$currency][1]);

        $ret = $symbol.$value;
        return $negative ? '('.$ret.')' : $ret;
    }

    /**
     * @{inheritDoc}
     */
    public function format($value, $type = self::TYPE_DEFAULT)
    {
        if (0 > ($fractionDigits = $this->getAttribute(self::FRACTION_DIGITS))) {
            $fractionDigits = 0;
        }

        $value = $this->round($value, $fractionDigits);
        return $this->formatNumber($value, $fractionDigits);
    }

    /**
     * @{inheritDoc}
     */
    public function getAttribute($attr)
    {
        if (isset($this->attributes[$attr])) {
            return $this->attributes[$attr];
        }
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
        if (!is_null($position)) {
            throw new \RuntimeException('$position should be null. Processing based on the $position value is not supported. Install the intl extension for full localization capabilities.');
        }

        preg_match('/^([^0-9\-]{0,})(.*)/', $value, $matches);

        // Any string before the numeric value causes error in the parsing
        if (isset($matches[1]) && !empty($matches[1])) {
            return false;
        }

        // Remove everything that is not number or dot (.)
        $value = preg_replace('/[^0-9\.\-]/', '', $value);

        return $this->getNumericValue($value, $type);
    }

    /**
     * @{inheritDoc}
     * @todo Decide between throwing an exception if ROUDING_MODE or GROUPING_USED are invalid.
     *       In \NumberFormatter, a true is returned and the format/parse() methods have undefined values
     *       in these cases.
     * @throws InvalidArgumentException  When the $attr is not supported
     */
    public function setAttribute($attr, $value)
    {
        if (!in_array($attr, self::$supportedAttributes)) {
            throw new \InvalidArgumentException(sprintf(
                'Unsupported $attr value. The available attributes are: %s. Install the intl extension for full localization capabilities.',
                implode(', ', array_keys(self::$supportedAttributes))
            ));
        }

        if (self::$supportedAttributes['ROUNDING_MODE'] == $attr && $this->isInvalidRoundingMode($value)) {
            return false;
        }

        if (self::$supportedAttributes['GROUPING_USED'] == $attr && $this->isInvalidGroupingUsedValue($value)) {
            return false;
        }

        $this->attributes[$attr] = $value;
        return true;
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

    /**
     * Returns the currency symbol.
     *
     * @param  string   $currency   The 3-letter ISO 4217 currency code indicating the currency to use
     * @return string               The currency symbol
     */
    private function getCurrencySymbol($currency)
    {
        $symbol = '';
        $hexSymbol = $this->currencies[$currency][0];
        $hex = explode('0x', $hexSymbol);
        unset($hex[0]);

        foreach ($hex as $h) {
            $symbol .= chr(hexdec($h));
        }

        return $symbol;
    }

    /**
     * Rounds a value.
     *
     * @param  numeric   $value      The value to round
     * @param  int       $precision  The number of decimal digits to round to
     * @return numeric               The rounded value
     */
    private function round($value, $precision)
    {
        $roundingMode = self::$phpRoundingMap[$this->getAttribute(self::ROUNDING_MODE)];
        $value = round($value, $precision, $roundingMode);

        return $value;
    }

    /**
     * Formats a number.
     *
     * @param  numeric   $value      The numeric value to format
     * @param  int       $precision  The number of decimal digits to use
     * @return string                The formatted number
     */
    private function formatNumber($value, $precision)
    {
        return number_format($value, $precision, '.', $this->getAttribute(self::GROUPING_USED) ? ',' : '');
    }

    /**
     * Check if the rounding mode is invalid.
     *
     * @param  int    $value  The rounding mode value to check
     * @return bool           true if the rounding mode is invalid, false otherwise
     */
    private function isInvalidRoundingMode($value)
    {
        if (in_array($value, self::$roundingModes, true)) {
            return false;
        }

        return true;
    }

    /**
     * Check if the grouping value is invalid.
     *
     * @param  int    $value  The grouping value to check
     * @return bool           true if the grouping value is invalid, false otherwise
     */
    private function isInvalidGroupingUsedValue($value)
    {
        if (in_array($value, self::$groupingUsedValues, true)) {
            return false;
        }

        return true;
    }

    private function getNumericValue($value, $type)
    {
        $type = $this->detectNumberType($value, $type);

        if ($type == self::TYPE_DOUBLE) {
            $value = (float) $value;
        }
        elseif ($type == self::TYPE_INT32) {
            $value = $this->getIntValue($type, $value);
        }
        elseif ($type == self::TYPE_INT64) {
            $value = $this->getIntValue($type, $value);
        }

        return $value;
    }

    private function detectNumberType($value, $type)
    {
        if ($type != self::TYPE_DEFAULT) {
            return $type;
        }

        if ($this->isFloat($value)) {
            $type = self::TYPE_DOUBLE;
        }
        elseif ($this->isInt32($value)) {
            $type = self::TYPE_INT32;
        }
        elseif ($this->isInt64($value)) {
            $type = self::TYPE_INT64;
        }

        return $type;
    }

    private function isFloat($value)
    {
        return strstr($value, '.') || is_float($value + 0) || is_float($value - 0);
    }

    private function isInt32($value)
    {
        return $this->isIntType(self::TYPE_INT32, $value);
    }

    private function isInt64($value)
    {
        return $this->isIntType(self::TYPE_INT64, $value);
    }

    private function isIntType($type, $value)
    {
        return $value <= self::$intValues[$type]['positive'] && $value >= self::$intValues[$type]['negative'];
    }

    private function getIntValue($int, $value)
    {
        if ($value > self::$intValues[$int]['positive']) {
            $value = self::$intValues[$int]['positive'];
        }
        elseif ($value < self::$intValues[$int]['negative']) {
            $value = self::$intValues[$int]['negative'];
        }

        return (int) $value;
    }
}
