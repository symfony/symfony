<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\NumberFormatter;

use Symfony\Component\Intl\Exception\MethodArgumentNotImplementedException;
use Symfony\Component\Intl\Exception\MethodArgumentValueNotImplementedException;
use Symfony\Component\Intl\Exception\MethodNotImplementedException;
use Symfony\Component\Intl\Exception\NotImplementedException;
use Symfony\Component\Intl\Globals\IntlGlobals;
use Symfony\Component\Intl\Intl;
use Symfony\Component\Intl\Locale\Locale;

/**
 * Replacement for PHP's native {@link \NumberFormatter} class.
 *
 * The only methods currently supported in this class are:
 *
 *  - {@link __construct}
 *  - {@link create}
 *  - {@link formatCurrency}
 *  - {@link format}
 *  - {@link getAttribute}
 *  - {@link getErrorCode}
 *  - {@link getErrorMessage}
 *  - {@link getLocale}
 *  - {@link parse}
 *  - {@link setAttribute}
 *
 * @author Eriksen Costa <eriksen.costa@infranology.com.br>
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @internal
 */
class NumberFormatter
{
    /* Format style constants */
    const PATTERN_DECIMAL = 0;
    const DECIMAL = 1;
    const CURRENCY = 2;
    const PERCENT = 3;
    const SCIENTIFIC = 4;
    const SPELLOUT = 5;
    const ORDINAL = 6;
    const DURATION = 7;
    const PATTERN_RULEBASED = 9;
    const IGNORE = 0;
    const DEFAULT_STYLE = 1;

    /* Format type constants */
    const TYPE_DEFAULT = 0;
    const TYPE_INT32 = 1;
    const TYPE_INT64 = 2;
    const TYPE_DOUBLE = 3;
    const TYPE_CURRENCY = 4;

    /* Numeric attribute constants */
    const PARSE_INT_ONLY = 0;
    const GROUPING_USED = 1;
    const DECIMAL_ALWAYS_SHOWN = 2;
    const MAX_INTEGER_DIGITS = 3;
    const MIN_INTEGER_DIGITS = 4;
    const INTEGER_DIGITS = 5;
    const MAX_FRACTION_DIGITS = 6;
    const MIN_FRACTION_DIGITS = 7;
    const FRACTION_DIGITS = 8;
    const MULTIPLIER = 9;
    const GROUPING_SIZE = 10;
    const ROUNDING_MODE = 11;
    const ROUNDING_INCREMENT = 12;
    const FORMAT_WIDTH = 13;
    const PADDING_POSITION = 14;
    const SECONDARY_GROUPING_SIZE = 15;
    const SIGNIFICANT_DIGITS_USED = 16;
    const MIN_SIGNIFICANT_DIGITS = 17;
    const MAX_SIGNIFICANT_DIGITS = 18;
    const LENIENT_PARSE = 19;

    /* Text attribute constants */
    const POSITIVE_PREFIX = 0;
    const POSITIVE_SUFFIX = 1;
    const NEGATIVE_PREFIX = 2;
    const NEGATIVE_SUFFIX = 3;
    const PADDING_CHARACTER = 4;
    const CURRENCY_CODE = 5;
    const DEFAULT_RULESET = 6;
    const PUBLIC_RULESETS = 7;

    /* Format symbol constants */
    const DECIMAL_SEPARATOR_SYMBOL = 0;
    const GROUPING_SEPARATOR_SYMBOL = 1;
    const PATTERN_SEPARATOR_SYMBOL = 2;
    const PERCENT_SYMBOL = 3;
    const ZERO_DIGIT_SYMBOL = 4;
    const DIGIT_SYMBOL = 5;
    const MINUS_SIGN_SYMBOL = 6;
    const PLUS_SIGN_SYMBOL = 7;
    const CURRENCY_SYMBOL = 8;
    const INTL_CURRENCY_SYMBOL = 9;
    const MONETARY_SEPARATOR_SYMBOL = 10;
    const EXPONENTIAL_SYMBOL = 11;
    const PERMILL_SYMBOL = 12;
    const PAD_ESCAPE_SYMBOL = 13;
    const INFINITY_SYMBOL = 14;
    const NAN_SYMBOL = 15;
    const SIGNIFICANT_DIGIT_SYMBOL = 16;
    const MONETARY_GROUPING_SEPARATOR_SYMBOL = 17;

    /* Rounding mode values used by NumberFormatter::setAttribute() with NumberFormatter::ROUNDING_MODE attribute */
    const ROUND_CEILING = 0;
    const ROUND_FLOOR = 1;
    const ROUND_DOWN = 2;
    const ROUND_UP = 3;
    const ROUND_HALFEVEN = 4;
    const ROUND_HALFDOWN = 5;
    const ROUND_HALFUP = 6;

    /* Pad position values used by NumberFormatter::setAttribute() with NumberFormatter::PADDING_POSITION attribute */
    const PAD_BEFORE_PREFIX = 0;
    const PAD_AFTER_PREFIX = 1;
    const PAD_BEFORE_SUFFIX = 2;
    const PAD_AFTER_SUFFIX = 3;

    /**
     * The error code from the last operation.
     *
     * @var int
     */
    protected $errorCode = IntlGlobals::U_ZERO_ERROR;

    /**
     * The error message from the last operation.
     *
     * @var string
     */
    protected $errorMessage = 'U_ZERO_ERROR';

    /**
     * @var int
     */
    private $style;

    /**
     * Default values for the en locale.
     */
    private $attributes = array(
        self::FRACTION_DIGITS => 0,
        self::GROUPING_USED => 1,
        self::ROUNDING_MODE => self::ROUND_HALFEVEN,
    );

    /**
     * Holds the initialized attributes code.
     */
    private $initializedAttributes = array();

    /**
     * The supported styles to the constructor $styles argument.
     */
    private static $supportedStyles = array(
        'CURRENCY' => self::CURRENCY,
        'DECIMAL' => self::DECIMAL,
    );

    /**
     * Supported attributes to the setAttribute() $attr argument.
     */
    private static $supportedAttributes = array(
        'FRACTION_DIGITS' => self::FRACTION_DIGITS,
        'GROUPING_USED' => self::GROUPING_USED,
        'ROUNDING_MODE' => self::ROUNDING_MODE,
    );

    /**
     * The available rounding modes for setAttribute() usage with
     * NumberFormatter::ROUNDING_MODE. NumberFormatter::ROUND_DOWN
     * and NumberFormatter::ROUND_UP does not have a PHP only equivalent.
     */
    private static $roundingModes = array(
        'ROUND_HALFEVEN' => self::ROUND_HALFEVEN,
        'ROUND_HALFDOWN' => self::ROUND_HALFDOWN,
        'ROUND_HALFUP' => self::ROUND_HALFUP,
        'ROUND_CEILING' => self::ROUND_CEILING,
        'ROUND_FLOOR' => self::ROUND_FLOOR,
        'ROUND_DOWN' => self::ROUND_DOWN,
        'ROUND_UP' => self::ROUND_UP,
    );

    /**
     * The mapping between NumberFormatter rounding modes to the available
     * modes in PHP's round() function.
     *
     * @see http://www.php.net/manual/en/function.round.php
     */
    private static $phpRoundingMap = array(
        self::ROUND_HALFDOWN => \PHP_ROUND_HALF_DOWN,
        self::ROUND_HALFEVEN => \PHP_ROUND_HALF_EVEN,
        self::ROUND_HALFUP => \PHP_ROUND_HALF_UP,
    );

    /**
     * The list of supported rounding modes which aren't available modes in
     * PHP's round() function, but there's an equivalent. Keys are rounding
     * modes, values does not matter.
     */
    private static $customRoundingList = array(
        self::ROUND_CEILING => true,
        self::ROUND_FLOOR => true,
        self::ROUND_DOWN => true,
        self::ROUND_UP => true,
    );

    /**
     * The maximum value of the integer type in 32 bit platforms.
     */
    private static $int32Max = 2147483647;

    /**
     * The maximum value of the integer type in 64 bit platforms.
     *
     * @var int|float
     */
    private static $int64Max = 9223372036854775807;

    private static $enSymbols = array(
        self::DECIMAL => array('.', ',', ';', '%', '0', '#', '-', '+', '¤', '¤¤', '.', 'E', '‰', '*', '∞', 'NaN', '@', ','),
        self::CURRENCY => array('.', ',', ';', '%', '0', '#', '-', '+', '¤', '¤¤', '.', 'E', '‰', '*', '∞', 'NaN', '@', ','),
    );

    private static $enTextAttributes = array(
        self::DECIMAL => array('', '', '-', '', ' ', 'XXX', ''),
        self::CURRENCY => array('¤', '', '-¤', '', ' ', 'XXX'),
    );

    /**
     * @param string $locale  The locale code. The only currently supported locale is "en" (or null using the default locale, i.e. "en")
     * @param int    $style   Style of the formatting, one of the format style constants.
     *                        The only supported styles are NumberFormatter::DECIMAL
     *                        and NumberFormatter::CURRENCY.
     * @param string $pattern Not supported. A pattern string in case $style is NumberFormat::PATTERN_DECIMAL or
     *                        NumberFormat::PATTERN_RULEBASED. It must conform to  the syntax
     *                        described in the ICU DecimalFormat or ICU RuleBasedNumberFormat documentation
     *
     * @see http://www.php.net/manual/en/numberformatter.create.php
     * @see http://www.icu-project.org/apiref/icu4c/classDecimalFormat.html#_details
     * @see http://www.icu-project.org/apiref/icu4c/classRuleBasedNumberFormat.html#_details
     *
     * @throws MethodArgumentValueNotImplementedException When $locale different than "en" or null is passed
     * @throws MethodArgumentValueNotImplementedException When the $style is not supported
     * @throws MethodArgumentNotImplementedException      When the pattern value is different than null
     */
    public function __construct($locale = 'en', $style = null, $pattern = null)
    {
        if ('en' !== $locale && null !== $locale) {
            throw new MethodArgumentValueNotImplementedException(__METHOD__, 'locale', $locale, 'Only the locale "en" is supported');
        }

        if (!\in_array($style, self::$supportedStyles)) {
            $message = sprintf('The available styles are: %s.', implode(', ', array_keys(self::$supportedStyles)));
            throw new MethodArgumentValueNotImplementedException(__METHOD__, 'style', $style, $message);
        }

        if (null !== $pattern) {
            throw new MethodArgumentNotImplementedException(__METHOD__, 'pattern');
        }

        $this->style = $style;
    }

    /**
     * Static constructor.
     *
     * @param string $locale  The locale code. The only supported locale is "en" (or null using the default locale, i.e. "en")
     * @param int    $style   Style of the formatting, one of the format style constants.
     *                        The only currently supported styles are NumberFormatter::DECIMAL
     *                        and NumberFormatter::CURRENCY.
     * @param string $pattern Not supported. A pattern string in case $style is NumberFormat::PATTERN_DECIMAL or
     *                        NumberFormat::PATTERN_RULEBASED. It must conform to  the syntax
     *                        described in the ICU DecimalFormat or ICU RuleBasedNumberFormat documentation
     *
     * @return self
     *
     * @see http://www.php.net/manual/en/numberformatter.create.php
     * @see http://www.icu-project.org/apiref/icu4c/classDecimalFormat.html#_details
     * @see http://www.icu-project.org/apiref/icu4c/classRuleBasedNumberFormat.html#_details
     *
     * @throws MethodArgumentValueNotImplementedException When $locale different than "en" or null is passed
     * @throws MethodArgumentValueNotImplementedException When the $style is not supported
     * @throws MethodArgumentNotImplementedException      When the pattern value is different than null
     */
    public static function create($locale = 'en', $style = null, $pattern = null)
    {
        return new self($locale, $style, $pattern);
    }

    /**
     * Format a currency value.
     *
     * @param float  $value    The numeric currency value
     * @param string $currency The 3-letter ISO 4217 currency code indicating the currency to use
     *
     * @return string The formatted currency value
     *
     * @see http://www.php.net/manual/en/numberformatter.formatcurrency.php
     * @see https://en.wikipedia.org/wiki/ISO_4217#Active_codes
     */
    public function formatCurrency($value, $currency)
    {
        if (self::DECIMAL == $this->style) {
            return $this->format($value);
        }

        $symbol = Intl::getCurrencyBundle()->getCurrencySymbol($currency, 'en');
        $fractionDigits = Intl::getCurrencyBundle()->getFractionDigits($currency);

        $value = $this->roundCurrency($value, $currency);

        $negative = false;
        if (0 > $value) {
            $negative = true;
            $value *= -1;
        }

        $value = $this->formatNumber($value, $fractionDigits);

        // There's a non-breaking space after the currency code (i.e. CRC 100), but not if the currency has a symbol (i.e. £100).
        $ret = $symbol.(mb_strlen($symbol, 'UTF-8') > 2 ? "\xc2\xa0" : '').$value;

        return $negative ? '-'.$ret : $ret;
    }

    /**
     * Format a number.
     *
     * @param int|float $value The value to format
     * @param int       $type  Type of the formatting, one of the format type constants.
     *                         Only type NumberFormatter::TYPE_DEFAULT is currently supported.
     *
     * @return bool|string The formatted value or false on error
     *
     * @see http://www.php.net/manual/en/numberformatter.format.php
     *
     * @throws NotImplementedException                    If the method is called with the class $style 'CURRENCY'
     * @throws MethodArgumentValueNotImplementedException If the $type is different than TYPE_DEFAULT
     */
    public function format($value, $type = self::TYPE_DEFAULT)
    {
        // The original NumberFormatter does not support this format type
        if (self::TYPE_CURRENCY == $type) {
            trigger_error(__METHOD__.'(): Unsupported format type '.$type, \E_USER_WARNING);

            return false;
        }

        if (self::CURRENCY == $this->style) {
            throw new NotImplementedException(sprintf('%s() method does not support the formatting of currencies (instance with CURRENCY style). %s', __METHOD__, NotImplementedException::INTL_INSTALL_MESSAGE));
        }

        // Only the default type is supported.
        if (self::TYPE_DEFAULT != $type) {
            throw new MethodArgumentValueNotImplementedException(__METHOD__, 'type', $type, 'Only TYPE_DEFAULT is supported');
        }

        $fractionDigits = $this->getAttribute(self::FRACTION_DIGITS);

        $value = $this->round($value, $fractionDigits);
        $value = $this->formatNumber($value, $fractionDigits);

        // behave like the intl extension
        $this->resetError();

        return $value;
    }

    /**
     * Returns an attribute value.
     *
     * @param int $attr An attribute specifier, one of the numeric attribute constants
     *
     * @return bool|int The attribute value on success or false on error
     *
     * @see http://www.php.net/manual/en/numberformatter.getattribute.php
     */
    public function getAttribute($attr)
    {
        return isset($this->attributes[$attr]) ? $this->attributes[$attr] : null;
    }

    /**
     * Returns formatter's last error code. Always returns the U_ZERO_ERROR class constant value.
     *
     * @return int The error code from last formatter call
     *
     * @see http://www.php.net/manual/en/numberformatter.geterrorcode.php
     */
    public function getErrorCode()
    {
        return $this->errorCode;
    }

    /**
     * Returns formatter's last error message. Always returns the U_ZERO_ERROR_MESSAGE class constant value.
     *
     * @return string The error message from last formatter call
     *
     * @see http://www.php.net/manual/en/numberformatter.geterrormessage.php
     */
    public function getErrorMessage()
    {
        return $this->errorMessage;
    }

    /**
     * Returns the formatter's locale.
     *
     * The parameter $type is currently ignored.
     *
     * @param int $type Not supported. The locale name type to return (Locale::VALID_LOCALE or Locale::ACTUAL_LOCALE)
     *
     * @return string The locale used to create the formatter. Currently always
     *                returns "en".
     *
     * @see http://www.php.net/manual/en/numberformatter.getlocale.php
     */
    public function getLocale($type = Locale::ACTUAL_LOCALE)
    {
        return 'en';
    }

    /**
     * Not supported. Returns the formatter's pattern.
     *
     * @return bool|string The pattern string used by the formatter or false on error
     *
     * @see http://www.php.net/manual/en/numberformatter.getpattern.php
     *
     * @throws MethodNotImplementedException
     */
    public function getPattern()
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    /**
     * Not supported. Returns a formatter symbol value.
     *
     * @param int $attr A symbol specifier, one of the format symbol constants
     *
     * @return bool|string The symbol value or false on error
     *
     * @see http://www.php.net/manual/en/numberformatter.getsymbol.php
     */
    public function getSymbol($attr)
    {
        return array_key_exists($this->style, self::$enSymbols) && array_key_exists($attr, self::$enSymbols[$this->style]) ? self::$enSymbols[$this->style][$attr] : false;
    }

    /**
     * Not supported. Returns a formatter text attribute value.
     *
     * @param int $attr An attribute specifier, one of the text attribute constants
     *
     * @return bool|string The attribute value or false on error
     *
     * @see http://www.php.net/manual/en/numberformatter.gettextattribute.php
     */
    public function getTextAttribute($attr)
    {
        return array_key_exists($this->style, self::$enTextAttributes) && array_key_exists($attr, self::$enTextAttributes[$this->style]) ? self::$enTextAttributes[$this->style][$attr] : false;
    }

    /**
     * Not supported. Parse a currency number.
     *
     * @param string $value    The value to parse
     * @param string $currency Parameter to receive the currency name (reference)
     * @param int    $position Offset to begin the parsing on return this value will hold the offset at which the parsing ended
     *
     * @return bool|string The parsed numeric value of false on error
     *
     * @see http://www.php.net/manual/en/numberformatter.parsecurrency.php
     *
     * @throws MethodNotImplementedException
     */
    public function parseCurrency($value, &$currency, &$position = null)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    /**
     * Parse a number.
     *
     * @param string $value    The value to parse
     * @param int    $type     Type of the formatting, one of the format type constants. NumberFormatter::TYPE_DOUBLE by default.
     * @param int    $position Offset to begin the parsing on return this value will hold the offset at which the parsing ended
     *
     * @return int|float|false The parsed value of false on error
     *
     * @see http://www.php.net/manual/en/numberformatter.parse.php
     */
    public function parse($value, $type = self::TYPE_DOUBLE, &$position = 0)
    {
        if (self::TYPE_DEFAULT == $type || self::TYPE_CURRENCY == $type) {
            trigger_error(__METHOD__.'(): Unsupported format type '.$type, \E_USER_WARNING);

            return false;
        }

        // Any invalid number at the end of the string is removed.
        // Only numbers and the fraction separator is expected in the string.
        // If grouping is used, grouping separator also becomes a valid character.
        $groupingMatch = $this->getAttribute(self::GROUPING_USED) ? '|(?P<grouping>\d++(,{1}\d+)++(\.\d*+)?)' : '';
        if (preg_match("/^-?(?:\.\d++{$groupingMatch}|\d++(\.\d*+)?)/", $value, $matches)) {
            $value = $matches[0];
            $position = \strlen($value);
            // value is not valid if grouping is used, but digits are not grouped in groups of three
            if ($error = isset($matches['grouping']) && !preg_match('/^-?(?:\d{1,3}+)?(?:(?:,\d{3})++|\d*+)(?:\.\d*+)?$/', $value)) {
                // the position on error is 0 for positive and 1 for negative numbers
                $position = 0 === strpos($value, '-') ? 1 : 0;
            }
        } else {
            $error = true;
            $position = 0;
        }

        if ($error) {
            IntlGlobals::setError(IntlGlobals::U_PARSE_ERROR, 'Number parsing failed');
            $this->errorCode = IntlGlobals::getErrorCode();
            $this->errorMessage = IntlGlobals::getErrorMessage();

            return false;
        }

        $value = str_replace(',', '', $value);
        $value = $this->convertValueDataType($value, $type);

        // behave like the intl extension
        $this->resetError();

        return $value;
    }

    /**
     * Set an attribute.
     *
     * @param int $attr  An attribute specifier, one of the numeric attribute constants.
     *                   The only currently supported attributes are NumberFormatter::FRACTION_DIGITS,
     *                   NumberFormatter::GROUPING_USED and NumberFormatter::ROUNDING_MODE.
     * @param int $value The attribute value
     *
     * @return bool true on success or false on failure
     *
     * @see http://www.php.net/manual/en/numberformatter.setattribute.php
     *
     * @throws MethodArgumentValueNotImplementedException When the $attr is not supported
     * @throws MethodArgumentValueNotImplementedException When the $value is not supported
     */
    public function setAttribute($attr, $value)
    {
        if (!\in_array($attr, self::$supportedAttributes)) {
            $message = sprintf(
                'The available attributes are: %s',
                implode(', ', array_keys(self::$supportedAttributes))
            );

            throw new MethodArgumentValueNotImplementedException(__METHOD__, 'attr', $value, $message);
        }

        if (self::$supportedAttributes['ROUNDING_MODE'] == $attr && $this->isInvalidRoundingMode($value)) {
            $message = sprintf(
                'The supported values for ROUNDING_MODE are: %s',
                implode(', ', array_keys(self::$roundingModes))
            );

            throw new MethodArgumentValueNotImplementedException(__METHOD__, 'attr', $value, $message);
        }

        if (self::$supportedAttributes['GROUPING_USED'] == $attr) {
            $value = $this->normalizeGroupingUsedValue($value);
        }

        if (self::$supportedAttributes['FRACTION_DIGITS'] == $attr) {
            $value = $this->normalizeFractionDigitsValue($value);
            if ($value < 0) {
                // ignore negative values but do not raise an error
                return true;
            }
        }

        $this->attributes[$attr] = $value;
        $this->initializedAttributes[$attr] = true;

        return true;
    }

    /**
     * Not supported. Set the formatter's pattern.
     *
     * @param string $pattern A pattern string in conformance with the ICU DecimalFormat documentation
     *
     * @return bool true on success or false on failure
     *
     * @see http://www.php.net/manual/en/numberformatter.setpattern.php
     * @see http://www.icu-project.org/apiref/icu4c/classDecimalFormat.html#_details
     *
     * @throws MethodNotImplementedException
     */
    public function setPattern($pattern)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    /**
     * Not supported. Set the formatter's symbol.
     *
     * @param int    $attr  A symbol specifier, one of the format symbol constants
     * @param string $value The value for the symbol
     *
     * @return bool true on success or false on failure
     *
     * @see http://www.php.net/manual/en/numberformatter.setsymbol.php
     *
     * @throws MethodNotImplementedException
     */
    public function setSymbol($attr, $value)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    /**
     * Not supported. Set a text attribute.
     *
     * @param int    $attr  An attribute specifier, one of the text attribute constants
     * @param string $value The attribute value
     *
     * @return bool true on success or false on failure
     *
     * @see http://www.php.net/manual/en/numberformatter.settextattribute.php
     *
     * @throws MethodNotImplementedException
     */
    public function setTextAttribute($attr, $value)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    /**
     * Set the error to the default U_ZERO_ERROR.
     */
    protected function resetError()
    {
        IntlGlobals::setError(IntlGlobals::U_ZERO_ERROR);
        $this->errorCode = IntlGlobals::getErrorCode();
        $this->errorMessage = IntlGlobals::getErrorMessage();
    }

    /**
     * Rounds a currency value, applying increment rounding if applicable.
     *
     * When a currency have a rounding increment, an extra round is made after the first one. The rounding factor is
     * determined in the ICU data and is explained as of:
     *
     * "the rounding increment is given in units of 10^(-fraction_digits)"
     *
     * The only actual rounding data as of this writing, is CHF.
     *
     * @param float  $value    The numeric currency value
     * @param string $currency The 3-letter ISO 4217 currency code indicating the currency to use
     *
     * @return float The rounded numeric currency value
     *
     * @see http://en.wikipedia.org/wiki/Swedish_rounding
     * @see http://www.docjar.com/html/api/com/ibm/icu/util/Currency.java.html#1007
     */
    private function roundCurrency($value, $currency)
    {
        $fractionDigits = Intl::getCurrencyBundle()->getFractionDigits($currency);
        $roundingIncrement = Intl::getCurrencyBundle()->getRoundingIncrement($currency);

        // Round with the formatter rounding mode
        $value = $this->round($value, $fractionDigits);

        // Swiss rounding
        if (0 < $roundingIncrement && 0 < $fractionDigits) {
            $roundingFactor = $roundingIncrement / pow(10, $fractionDigits);
            $value = round($value / $roundingFactor) * $roundingFactor;
        }

        return $value;
    }

    /**
     * Rounds a value.
     *
     * @param int|float $value     The value to round
     * @param int       $precision The number of decimal digits to round to
     *
     * @return int|float The rounded value
     */
    private function round($value, $precision)
    {
        $precision = $this->getUninitializedPrecision($value, $precision);

        $roundingModeAttribute = $this->getAttribute(self::ROUNDING_MODE);
        if (isset(self::$phpRoundingMap[$roundingModeAttribute])) {
            $value = round($value, $precision, self::$phpRoundingMap[$roundingModeAttribute]);
        } elseif (isset(self::$customRoundingList[$roundingModeAttribute])) {
            $roundingCoef = pow(10, $precision);
            $value *= $roundingCoef;
            $value = (float) (string) $value;

            switch ($roundingModeAttribute) {
                case self::ROUND_CEILING:
                    $value = ceil($value);
                    break;
                case self::ROUND_FLOOR:
                    $value = floor($value);
                    break;
                case self::ROUND_UP:
                    $value = $value > 0 ? ceil($value) : floor($value);
                    break;
                case self::ROUND_DOWN:
                    $value = $value > 0 ? floor($value) : ceil($value);
                    break;
            }

            $value /= $roundingCoef;
        }

        return $value;
    }

    /**
     * Formats a number.
     *
     * @param int|float $value     The numeric value to format
     * @param int       $precision The number of decimal digits to use
     *
     * @return string The formatted number
     */
    private function formatNumber($value, $precision)
    {
        $precision = $this->getUninitializedPrecision($value, $precision);

        return number_format($value, $precision, '.', $this->getAttribute(self::GROUPING_USED) ? ',' : '');
    }

    /**
     * Returns the precision value if the DECIMAL style is being used and the FRACTION_DIGITS attribute is uninitialized.
     *
     * @param int|float $value     The value to get the precision from if the FRACTION_DIGITS attribute is uninitialized
     * @param int       $precision The precision value to returns if the FRACTION_DIGITS attribute is initialized
     *
     * @return int The precision value
     */
    private function getUninitializedPrecision($value, $precision)
    {
        if (self::CURRENCY == $this->style) {
            return $precision;
        }

        if (!$this->isInitializedAttribute(self::FRACTION_DIGITS)) {
            preg_match('/.*\.(.*)/', (string) $value, $digits);
            if (isset($digits[1])) {
                $precision = \strlen($digits[1]);
            }
        }

        return $precision;
    }

    /**
     * Check if the attribute is initialized (value set by client code).
     *
     * @param string $attr The attribute name
     *
     * @return bool true if the value was set by client, false otherwise
     */
    private function isInitializedAttribute($attr)
    {
        return isset($this->initializedAttributes[$attr]);
    }

    /**
     * Returns the numeric value using the $type to convert to the right data type.
     *
     * @param mixed $value The value to be converted
     * @param int   $type  The type to convert. Can be TYPE_DOUBLE (float) or TYPE_INT32 (int)
     *
     * @return int|float|false The converted value
     */
    private function convertValueDataType($value, $type)
    {
        if (self::TYPE_DOUBLE == $type) {
            $value = (float) $value;
        } elseif (self::TYPE_INT32 == $type) {
            $value = $this->getInt32Value($value);
        } elseif (self::TYPE_INT64 == $type) {
            $value = $this->getInt64Value($value);
        }

        return $value;
    }

    /**
     * Convert the value data type to int or returns false if the value is out of the integer value range.
     *
     * @param mixed $value The value to be converted
     *
     * @return int|false The converted value
     */
    private function getInt32Value($value)
    {
        if ($value > self::$int32Max || $value < -self::$int32Max - 1) {
            return false;
        }

        return (int) $value;
    }

    /**
     * Convert the value data type to int or returns false if the value is out of the integer value range.
     *
     * @param mixed $value The value to be converted
     *
     * @return int|float|false The converted value
     */
    private function getInt64Value($value)
    {
        if ($value > self::$int64Max || $value < -self::$int64Max - 1) {
            return false;
        }

        if (PHP_INT_SIZE !== 8 && ($value > self::$int32Max || $value < -self::$int32Max - 1)) {
            return (float) $value;
        }

        return (int) $value;
    }

    /**
     * Check if the rounding mode is invalid.
     *
     * @param int $value The rounding mode value to check
     *
     * @return bool true if the rounding mode is invalid, false otherwise
     */
    private function isInvalidRoundingMode($value)
    {
        if (\in_array($value, self::$roundingModes, true)) {
            return false;
        }

        return true;
    }

    /**
     * Returns the normalized value for the GROUPING_USED attribute. Any value that can be converted to int will be
     * cast to Boolean and then to int again. This way, negative values are converted to 1 and string values to 0.
     *
     * @param mixed $value The value to be normalized
     *
     * @return int The normalized value for the attribute (0 or 1)
     */
    private function normalizeGroupingUsedValue($value)
    {
        return (int) (bool) (int) $value;
    }

    /**
     * Returns the normalized value for the FRACTION_DIGITS attribute.
     *
     * @param mixed $value The value to be normalized
     *
     * @return int The normalized value for the attribute
     */
    private function normalizeFractionDigitsValue($value)
    {
        return (int) $value;
    }
}
