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

use Symfony\Component\Intl\Exception\NotImplementedException;
use Symfony\Component\Intl\Exception\MethodNotImplementedException;
use Symfony\Component\Intl\Exception\MethodArgumentNotImplementedException;
use Symfony\Component\Intl\Exception\MethodArgumentValueNotImplementedException;
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
 */
class NumberFormatter
{
    /* Format style constants */
    const PATTERN_DECIMAL   = 0;
    const DECIMAL           = 1;
    const CURRENCY          = 2;
    const PERCENT           = 3;
    const SCIENTIFIC        = 4;
    const SPELLOUT          = 5;
    const ORDINAL           = 6;
    const DURATION          = 7;
    const PATTERN_RULEBASED = 9;
    const IGNORE            = 0;
    const DEFAULT_STYLE     = 1;

    /* Format type constants */
    const TYPE_DEFAULT  = 0;
    const TYPE_INT32    = 1;
    const TYPE_INT64    = 2;
    const TYPE_DOUBLE   = 3;
    const TYPE_CURRENCY = 4;

    /* Numeric attribute constants */
    const PARSE_INT_ONLY          = 0;
    const GROUPING_USED           = 1;
    const DECIMAL_ALWAYS_SHOWN    = 2;
    const MAX_INTEGER_DIGITS      = 3;
    const MIN_INTEGER_DIGITS      = 4;
    const INTEGER_DIGITS          = 5;
    const MAX_FRACTION_DIGITS     = 6;
    const MIN_FRACTION_DIGITS     = 7;
    const FRACTION_DIGITS         = 8;
    const MULTIPLIER              = 9;
    const GROUPING_SIZE           = 10;
    const ROUNDING_MODE           = 11;
    const ROUNDING_INCREMENT      = 12;
    const FORMAT_WIDTH            = 13;
    const PADDING_POSITION        = 14;
    const SECONDARY_GROUPING_SIZE = 15;
    const SIGNIFICANT_DIGITS_USED = 16;
    const MIN_SIGNIFICANT_DIGITS  = 17;
    const MAX_SIGNIFICANT_DIGITS  = 18;
    const LENIENT_PARSE           = 19;

    /* Text attribute constants */
    const POSITIVE_PREFIX   = 0;
    const POSITIVE_SUFFIX   = 1;
    const NEGATIVE_PREFIX   = 2;
    const NEGATIVE_SUFFIX   = 3;
    const PADDING_CHARACTER = 4;
    const CURRENCY_CODE     = 5;
    const DEFAULT_RULESET   = 6;
    const PUBLIC_RULESETS   = 7;

    /* Format symbol constants */
    const DECIMAL_SEPARATOR_SYMBOL           = 0;
    const GROUPING_SEPARATOR_SYMBOL          = 1;
    const PATTERN_SEPARATOR_SYMBOL           = 2;
    const PERCENT_SYMBOL                     = 3;
    const ZERO_DIGIT_SYMBOL                  = 4;
    const DIGIT_SYMBOL                       = 5;
    const MINUS_SIGN_SYMBOL                  = 6;
    const PLUS_SIGN_SYMBOL                   = 7;
    const CURRENCY_SYMBOL                    = 8;
    const INTL_CURRENCY_SYMBOL               = 9;
    const MONETARY_SEPARATOR_SYMBOL          = 10;
    const EXPONENTIAL_SYMBOL                 = 11;
    const PERMILL_SYMBOL                     = 12;
    const PAD_ESCAPE_SYMBOL                  = 13;
    const INFINITY_SYMBOL                    = 14;
    const NAN_SYMBOL                         = 15;
    const SIGNIFICANT_DIGIT_SYMBOL           = 16;
    const MONETARY_GROUPING_SEPARATOR_SYMBOL = 17;

    /* Rounding mode values used by NumberFormatter::setAttribute() with NumberFormatter::ROUNDING_MODE attribute */
    const ROUND_CEILING  = 0;
    const ROUND_FLOOR    = 1;
    const ROUND_DOWN     = 2;
    const ROUND_UP       = 3;
    const ROUND_HALFEVEN = 4;
    const ROUND_HALFDOWN = 5;
    const ROUND_HALFUP   = 6;

    /* Pad position values used by NumberFormatter::setAttribute() with NumberFormatter::PADDING_POSITION attribute */
    const PAD_BEFORE_PREFIX = 0;
    const PAD_AFTER_PREFIX  = 1;
    const PAD_BEFORE_SUFFIX = 2;
    const PAD_AFTER_SUFFIX  = 3;

    /**
     * The error code from the last operation
     *
     * @var integer
     */
    protected $errorCode = IntlGlobals::U_ZERO_ERROR;

    /**
     * The error message from the last operation
     *
     * @var string
     */
    protected $errorMessage = 'U_ZERO_ERROR';

    /**
     * @var int
     */
    private $style;

    /**
     * Default values for the en locale
     *
     * @var array
     */
    private $attributes = array(
        self::FRACTION_DIGITS => 0,
        self::GROUPING_USED   => 1,
        self::ROUNDING_MODE   => self::ROUND_HALFEVEN
    );

    /**
     * Holds the initialized attributes code
     *
     * @var array
     */
    private $initializedAttributes = array();

    /**
     * The supported styles to the constructor $styles argument
     *
     * @var array
     */
    private static $supportedStyles = array(
        'CURRENCY' => self::CURRENCY,
        'DECIMAL'  => self::DECIMAL
    );

    /**
     * Supported attributes to the setAttribute() $attr argument
     *
     * @var array
     */
    private static $supportedAttributes = array(
        'FRACTION_DIGITS' => self::FRACTION_DIGITS,
        'GROUPING_USED'   => self::GROUPING_USED,
        'ROUNDING_MODE'   => self::ROUNDING_MODE
    );

    /**
     * The available rounding modes for setAttribute() usage with
     * NumberFormatter::ROUNDING_MODE. NumberFormatter::ROUND_DOWN
     * and NumberFormatter::ROUND_UP does not have a PHP only equivalent
     *
     * @var array
     */
    private static $roundingModes = array(
        'ROUND_HALFEVEN' => self::ROUND_HALFEVEN,
        'ROUND_HALFDOWN' => self::ROUND_HALFDOWN,
        'ROUND_HALFUP'   => self::ROUND_HALFUP
    );

    /**
     * The mapping between NumberFormatter rounding modes to the available
     * modes in PHP's round() function.
     *
     * @see http://www.php.net/manual/en/function.round.php
     *
     * @var array
     */
    private static $phpRoundingMap = array(
        self::ROUND_HALFDOWN => \PHP_ROUND_HALF_DOWN,
        self::ROUND_HALFEVEN => \PHP_ROUND_HALF_EVEN,
        self::ROUND_HALFUP   => \PHP_ROUND_HALF_UP
    );

    /**
     * The maximum values of the integer type in 32 bit platforms.
     *
     * @var array
     */
    private static $int32Range = array(
        'positive' => 2147483647,
        'negative' => -2147483648
    );

    /**
     * The maximum values of the integer type in 64 bit platforms.
     *
     * @var array
     */
    private static $int64Range = array(
        'positive' => 9223372036854775807,
        'negative' => -9223372036854775808
    );

    /**
     * Constructor.
     *
     * @param string $locale  The locale code. The only currently supported locale is "en".
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
     * @throws MethodArgumentValueNotImplementedException  When $locale different than "en" is passed
     * @throws MethodArgumentValueNotImplementedException  When the $style is not supported
     * @throws MethodArgumentNotImplementedException       When the pattern value is different than null
     */
    public function __construct($locale = 'en', $style = null, $pattern = null)
    {
        if ('en' != $locale) {
            throw new MethodArgumentValueNotImplementedException(__METHOD__, 'locale', $locale, 'Only the locale "en" is supported');
        }

        if (!in_array($style, self::$supportedStyles)) {
            $message = sprintf('The available styles are: %s.', implode(', ', array_keys(self::$supportedStyles)));
            throw new MethodArgumentValueNotImplementedException(__METHOD__, 'style', $style, $message);
        }

        if (null !== $pattern) {
            throw new MethodArgumentNotImplementedException(__METHOD__, 'pattern');
        }

        $this->style  = $style;
    }

    /**
     * Static constructor.
     *
     * @param string $locale  The locale code. The only supported locale is "en".
     * @param int    $style   Style of the formatting, one of the format style constants.
     *                        The only currently supported styles are NumberFormatter::DECIMAL
     *                        and NumberFormatter::CURRENCY.
     * @param string $pattern Not supported. A pattern string in case $style is NumberFormat::PATTERN_DECIMAL or
     *                        NumberFormat::PATTERN_RULEBASED. It must conform to  the syntax
     *                        described in the ICU DecimalFormat or ICU RuleBasedNumberFormat documentation
     *
     * @return NumberFormatter
     *
     * @see http://www.php.net/manual/en/numberformatter.create.php
     * @see http://www.icu-project.org/apiref/icu4c/classDecimalFormat.html#_details
     * @see http://www.icu-project.org/apiref/icu4c/classRuleBasedNumberFormat.html#_details
     *
     * @throws MethodArgumentValueNotImplementedException  When $locale different than "en" is passed
     * @throws MethodArgumentValueNotImplementedException  When the $style is not supported
     * @throws MethodArgumentNotImplementedException       When the pattern value is different than null
     */
    public static function create($locale = 'en', $style = null, $pattern = null)
    {
        return new self($locale, $style, $pattern);
    }

    /**
     * Format a currency value
     *
     * @param float  $value    The numeric currency value
     * @param string $currency The 3-letter ISO 4217 currency code indicating the currency to use
     *
     * @return string The formatted currency value
     *
     * @see http://www.php.net/manual/en/numberformatter.formatcurrency.php
     * @see http://www.iso.org/iso/support/faqs/faqs_widely_used_standards/widely_used_standards_other/currency_codes/currency_codes_list-1.htm
     */
    public function formatCurrency($value, $currency)
    {
        if ($this->style == self::DECIMAL) {
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

        $ret = $symbol.$value;

        return $negative ? '('.$ret.')' : $ret;
    }

    /**
     * Format a number
     *
     * @param number $value The value to format
     * @param int    $type  Type of the formatting, one of the format type constants.
     *                      Only type NumberFormatter::TYPE_DEFAULT is currently supported.
     *
     * @return Boolean|string The formatted value or false on error
     *
     * @see http://www.php.net/manual/en/numberformatter.format.php
     *
     * @throws NotImplementedException                    If the method is called with the class $style 'CURRENCY'
     * @throws MethodArgumentValueNotImplementedException If the $type is different than TYPE_DEFAULT
     */
    public function format($value, $type = self::TYPE_DEFAULT)
    {
        // The original NumberFormatter does not support this format type
        if ($type == self::TYPE_CURRENCY) {
            trigger_error(__METHOD__.'(): Unsupported format type '.$type, \E_USER_WARNING);

            return false;
        }

        if ($this->style == self::CURRENCY) {
            throw new NotImplementedException(sprintf(
                '%s() method does not support the formatting of currencies (instance with CURRENCY style). %s',
                __METHOD__, NotImplementedException::INTL_INSTALL_MESSAGE
            ));
        }

        // Only the default type is supported.
        if ($type != self::TYPE_DEFAULT) {
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
     * Returns an attribute value
     *
     * @param int $attr An attribute specifier, one of the numeric attribute constants
     *
     * @return Boolean|int The attribute value on success or false on error
     *
     * @see http://www.php.net/manual/en/numberformatter.getattribute.php
     */
    public function getAttribute($attr)
    {
        return isset($this->attributes[$attr]) ? $this->attributes[$attr] : null;
    }

    /**
     * Returns formatter's last error code. Always returns the U_ZERO_ERROR class constant value
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
     * Returns formatter's last error message. Always returns the U_ZERO_ERROR_MESSAGE class constant value
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
     * Returns the formatter's locale
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
     * Not supported. Returns the formatter's pattern
     *
     * @return Boolean|string     The pattern string used by the formatter or false on error
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
     * Not supported. Returns a formatter symbol value
     *
     * @param int $attr A symbol specifier, one of the format symbol constants
     *
     * @return Boolean|string        The symbol value or false on error
     *
     * @see http://www.php.net/manual/en/numberformatter.getsymbol.php
     *
     * @throws MethodNotImplementedException
     */
    public function getSymbol($attr)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    /**
     * Not supported. Returns a formatter text attribute value
     *
     * @param int $attr An attribute specifier, one of the text attribute constants
     *
     * @return Boolean|string        The attribute value or false on error
     *
     * @see http://www.php.net/manual/en/numberformatter.gettextattribute.php
     *
     * @throws MethodNotImplementedException
     */
    public function getTextAttribute($attr)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    /**
     * Not supported. Parse a currency number
     *
     * @param string $value    The value to parse
     * @param string $currency Parameter to receive the currency name (reference)
     * @param int    $position Offset to begin the parsing on return this value will hold the offset at which the parsing ended
     *
     * @return Boolean|string           The parsed numeric value of false on error
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
     * Parse a number
     *
     * @param string $value    The value to parse
     * @param int    $type     Type of the formatting, one of the format type constants.
     *                         The only currently supported types are NumberFormatter::TYPE_DOUBLE,
     *                         NumberFormatter::TYPE_INT32 and NumberFormatter::TYPE_INT64.
     * @param int    $position Not supported. Offset to begin the parsing on return this value will hold the offset at which the parsing ended
     *
     * @return Boolean|string                               The parsed value of false on error
     *
     * @see http://www.php.net/manual/en/numberformatter.parse.php
     *
     * @throws MethodArgumentNotImplementedException        When $position different than null, behavior not implemented
     */
    public function parse($value, $type = self::TYPE_DOUBLE, &$position = null)
    {
        if ($type == self::TYPE_DEFAULT || $type == self::TYPE_CURRENCY) {
            trigger_error(__METHOD__.'(): Unsupported format type '.$type, \E_USER_WARNING);

            return false;
        }

        // We don't calculate the position when parsing the value
        if (null !== $position) {
            throw new MethodArgumentNotImplementedException(__METHOD__, 'position');
        }

        preg_match('/^([^0-9\-]{0,})(.*)/', $value, $matches);

        // Any string before the numeric value causes error in the parsing
        if (isset($matches[1]) && !empty($matches[1])) {
            IntlGlobals::setError(IntlGlobals::U_PARSE_ERROR, 'Number parsing failed');
            $this->errorCode = IntlGlobals::getErrorCode();
            $this->errorMessage = IntlGlobals::getErrorMessage();

            return false;
        }

        // Remove everything that is not number or dot (.)
        $value = preg_replace('/[^0-9\.\-]/', '', $value);
        $value = $this->convertValueDataType($value, $type);

        // behave like the intl extension
        $this->resetError();

        return $value;
    }

    /**
     * Set an attribute
     *
     * @param int $attr  An attribute specifier, one of the numeric attribute constants.
     *                   The only currently supported attributes are NumberFormatter::FRACTION_DIGITS,
     *                   NumberFormatter::GROUPING_USED and NumberFormatter::ROUNDING_MODE.
     * @param int $value The attribute value. The only currently supported rounding modes are
     *                   NumberFormatter::ROUND_HALFEVEN, NumberFormatter::ROUND_HALFDOWN and
     *                   NumberFormatter::ROUND_HALFUP.
     *
     * @return Boolean true on success or false on failure
     *
     * @see http://www.php.net/manual/en/numberformatter.setattribute.php
     *
     * @throws MethodArgumentValueNotImplementedException  When the $attr is not supported
     * @throws MethodArgumentValueNotImplementedException  When the $value is not supported
     */
    public function setAttribute($attr, $value)
    {
        if (!in_array($attr, self::$supportedAttributes)) {
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
        }

        $this->attributes[$attr] = $value;
        $this->initializedAttributes[$attr] = true;

        return true;
    }

    /**
     * Not supported. Set the formatter's pattern
     *
     * @param string $pattern A pattern string in conformance with the ICU DecimalFormat documentation
     *
     * @return Boolean true on success or false on failure
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
     * Not supported. Set the formatter's symbol
     *
     * @param int    $attr  A symbol specifier, one of the format symbol constants
     * @param string $value The value for the symbol
     *
     * @return Boolean true on success or false on failure
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
     * Not supported. Set a text attribute
     *
     * @param int $attr  An attribute specifier, one of the text attribute constants
     * @param int $value The attribute value
     *
     * @return Boolean true on success or false on failure
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
     * Set the error to the default U_ZERO_ERROR
     */
    protected function resetError()
    {
        IntlGlobals::setError(IntlGlobals::U_ZERO_ERROR);
        $this->errorCode = IntlGlobals::getErrorCode();
        $this->errorMessage = IntlGlobals::getErrorMessage();
    }

    /**
     * Rounds a currency value, applying increment rounding if applicable
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
     * @return string The rounded numeric currency value
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
     * @param integer|float $value     The value to round
     * @param int           $precision The number of decimal digits to round to
     *
     * @return integer|float The rounded value
     */
    private function round($value, $precision)
    {
        $precision = $this->getUnitializedPrecision($value, $precision);

        $roundingMode = self::$phpRoundingMap[$this->getAttribute(self::ROUNDING_MODE)];
        $value = round($value, $precision, $roundingMode);

        return $value;
    }

    /**
     * Formats a number.
     *
     * @param integer|float $value     The numeric value to format
     * @param int           $precision The number of decimal digits to use
     *
     * @return string The formatted number
     */
    private function formatNumber($value, $precision)
    {
        $precision = $this->getUnitializedPrecision($value, $precision);

        return number_format($value, $precision, '.', $this->getAttribute(self::GROUPING_USED) ? ',' : '');
    }

    /**
     * Returns the precision value if the DECIMAL style is being used and the FRACTION_DIGITS attribute is unitialized.
     *
     * @param integer|float $value     The value to get the precision from if the FRACTION_DIGITS attribute is unitialized
     * @param int           $precision The precision value to returns if the FRACTION_DIGITS attribute is initialized
     *
     * @return int The precision value
     */
    private function getUnitializedPrecision($value, $precision)
    {
        if ($this->style == self::CURRENCY) {
            return $precision;
        }

        if (!$this->isInitializedAttribute(self::FRACTION_DIGITS)) {
            preg_match('/.*\.(.*)/', (string) $value, $digits);
            if (isset($digits[1])) {
                $precision = strlen($digits[1]);
            }
        }

        return $precision;
    }

    /**
     * Check if the attribute is initialized (value set by client code).
     *
     * @param string $attr The attribute name
     *
     * @return Boolean true if the value was set by client, false otherwise
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
     * @return integer|float The converted value
     */
    private function convertValueDataType($value, $type)
    {
        if ($type == self::TYPE_DOUBLE) {
            $value = (float) $value;
        } elseif ($type == self::TYPE_INT32) {
            $value = $this->getInt32Value($value);
        } elseif ($type == self::TYPE_INT64) {
            $value = $this->getInt64Value($value);
        }

        return $value;
    }

    /**
     * Convert the value data type to int or returns false if the value is out of the integer value range.
     *
     * @param mixed $value The value to be converted
     *
     * @return int The converted value
     */
    private function getInt32Value($value)
    {
        if ($value > self::$int32Range['positive'] || $value < self::$int32Range['negative']) {
            return false;
        }

        return (int) $value;
    }

    /**
     * Convert the value data type to int or returns false if the value is out of the integer value range.
     *
     * @param mixed $value The value to be converted
     *
     * @return int|float       The converted value
     *
     * @see https://bugs.php.net/bug.php?id=59597 Bug #59597
     */
    private function getInt64Value($value)
    {
        if ($value > self::$int64Range['positive'] || $value < self::$int64Range['negative']) {
            return false;
        }

        if (PHP_INT_SIZE !== 8 && ($value > self::$int32Range['positive'] || $value <= self::$int32Range['negative'])) {
            // Bug #59597 was fixed on PHP 5.3.14 and 5.4.4
            // The negative PHP_INT_MAX was being converted to float
            if (
                $value == self::$int32Range['negative'] &&
                (
                    (version_compare(PHP_VERSION, '5.4.0', '<') && version_compare(PHP_VERSION, '5.3.14', '>=')) ||
                    version_compare(PHP_VERSION, '5.4.4', '>=')
                )
            ) {
                return (int) $value;
            }

            return (float) $value;
        }

        if (PHP_INT_SIZE === 8) {
            // Bug #59597 was fixed on PHP 5.3.14 and 5.4.4
            // A 32 bit integer was being generated instead of a 64 bit integer
            if (
                  ($value > self::$int32Range['positive'] || $value < self::$int32Range['negative']) &&
                  (
                      (version_compare(PHP_VERSION, '5.3.14', '<')) ||
                      (version_compare(PHP_VERSION, '5.4.0', '>=') && version_compare(PHP_VERSION, '5.4.4', '<'))
                  )
            ) {
                $value = (-2147483648 - ($value % -2147483648)) * ($value / abs($value));
            }
        }

        return (int) $value;
    }

    /**
     * Check if the rounding mode is invalid.
     *
     * @param int $value The rounding mode value to check
     *
     * @return Boolean true if the rounding mode is invalid, false otherwise
     */
    private function isInvalidRoundingMode($value)
    {
        if (in_array($value, self::$roundingModes, true)) {
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
        return (int) (Boolean) (int) $value;
    }

    /**
     * Returns the normalized value for the FRACTION_DIGITS attribute. The value is converted to int and if negative,
     * the returned value will be 0.
     *
     * @param mixed $value The value to be normalized
     *
     * @return int The normalized value for the attribute
     */
    private function normalizeFractionDigitsValue($value)
    {
        $value = (int) $value;

        return (0 > $value) ? 0 : $value;
    }
}
