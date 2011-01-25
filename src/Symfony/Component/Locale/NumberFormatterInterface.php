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

interface NumberFormatterInterface
{
    /** Format style constants */
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

    /** Format type constants */
    const TYPE_DEFAULT  = 0;
    const TYPE_INT32    = 1;
    const TYPE_INT64    = 2;
    const TYPE_DOUBLE   = 3;
    const TYPE_CURRENCY = 4;

    /** Numeric attribute constants */
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

    /** Text attribute constants */
    const POSITIVE_PREFIX   = 0;
    const POSITIVE_SUFFIX   = 1;
    const NEGATIVE_PREFIX   = 2;
    const NEGATIVE_SUFFIX   = 3;
    const PADDING_CHARACTER = 4;
    const CURRENCY_CODE     = 5;
    const DEFAULT_RULESET   = 6;
    const PUBLIC_RULESETS   = 7;

    /** Format symbol constants */
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

    /** Rounding mode values used by NumberFormatter::setAttribute() with NumberFormatter::ROUNDING_MODE attribute */
    const ROUND_CEILING  = 0;
    const ROUND_FLOOR    = 1;
    const ROUND_DOWN     = 2;
    const ROUND_UP       = 3;
    const ROUND_HALFEVEN = 4;
    const ROUND_HALFDOWN = 5;
    const ROUND_HALFUP   = 6;

    /** Pad position values used by NumberFormatter::setAttribute() with NumberFormatter::PADDING_POSITION attribute */
    const PAD_BEFORE_PREFIX = 0;
    const PAD_AFTER_PREFIX  = 1;
    const PAD_BEFORE_SUFFIX = 2;
    const PAD_AFTER_SUFFIX  = 3;

    /**
     * Constructor
     *
     * @param string  $locale   The locale code
     * @param int     $style    Style of the formatting, one of the format style constants
     * @param string  $pattern  A pattern string in case $style is NumberFormat::PATTERN_DECIMAL or
     *                          NumberFormat::PATTERN_RULEBASED. It must conform to  the syntax
     *                          described in the ICU DecimalFormat or ICU RuleBasedNumberFormat documentation
     * @see   http://www.icu-project.org/apiref/icu4c/classDecimalFormat.html#_details
     * @see   http://www.icu-project.org/apiref/icu4c/classRuleBasedNumberFormat.html#_details
     */
    function __construct($locale, $style, $pattern = null);

    /**
     * Format a currency value
     *
     * @param  float   $value     The numeric currency value
     * @param  string  $currency  The 3-letter ISO 4217 currency code indicating the currency to use
     * @return string             The formatted currency value
     * @see    http://www.iso.org/iso/support/faqs/faqs_widely_used_standards/widely_used_standards_other/currency_codes/currency_codes_list-1.htm
     */
    function formatCurrency($value, $currency);

    /**
     * Format a number
     *
     * @param  number      $value  The value to format
     * @param  int         $type   Type of the formatting, one of the format type constants
     * @return bool|string         The formatted value or false on error
     */
    function format($value, $type);

    /**
     * Returns an attribute value
     *
     * @param  int       $attr   An attribute specifier, one of the numeric attribute constants
     * @return bool|int          The attribute value on success or false on error
     */
    function getAttribute($attr);

    /**
     * Returns formatter's last error code
     *
     * @return int The error code from last formatter call
     */
    function getErrorCode();

    /**
     * Returns formatter's last error message
     *
     * @return string The error message from last formatter call
     */
    function getErrorMessage();

    /**
     * Returns the formatter's locale
     *
     * @param  int      $type  The locale name type to return between valid or actual (Locale::VALID_LOCALE or Locale::ACTUAL_LOCALE, respectively)
     * @return string          The locale name used to create the formatter
     */
    function getLocale($type);

    /**
     * Returns the formatter's pattern
     *
     * @return bool|string The pattern string used by the formatter or false on error
     */
    function getPattern();

    /**
     * Returns a formatter symbol value
     *
     * @param  int           $attr   A symbol specifier, one of the format symbol constants
     * @return bool|string   The symbol value or false on error
     */
    function getSymbol($attr);

    /**
     * Returns a formatter text attribute value
     *
     * @param  int           $attr   An attribute specifier, one of the text attribute constants
     * @return bool|string   The attribute value or false on error
     */
    function getTextAttribute($attr);

    /**
     * Parse a currency number
     *
     * @param  string       $value     The value to parse
     * @param  string       $currency  Parameter to receive the currency name (reference)
     * @param  int          $position  Offset to begin the parsing on return this value will hold the offset at which the parsing ended
     * @return bool|string  The parsed numeric value of false on error
     */
    function parseCurrency($value, &$currency, &$position = null);

    /**
     * Parse a number
     *
     * @param  string       $value     The value to parse
     * @param  string       $type      Type of the formatting, one of the format type constants. NumberFormatter::TYPE_DOUBLE by default
     * @param  int          $position  Offset to begin the parsing on return this value will hold the offset at which the parsing ended
     * @return bool|string  The parsed value of false on error
     */
    function parse($value, $type = self::TYPE_DOUBLE, &$position = null);

    /**
     * Set an attribute
     *
     * @param  int   $attr    An attribute specifier, one of the numeric attribute constants
     * @param  int   $value   The attribute value
     * @return bool           true on success or false on failure
     */
    function setAttribute($attr, $value);

    /**
     * Set the formatter's pattern
     *
     * @param  string  $pattern   A pattern string in conformance with the ICU DecimalFormat documentation
     * @return bool               true on success or false on failure
     * @see    http://www.icu-project.org/apiref/icu4c/classDecimalFormat.html#_details
     */
    function setPattern($attr, $value);

    /**
     * Set the formatter's symbol
     *
     * @param  int      $attr    A symbol specifier, one of the format symbol constants
     * @param  string   $value   The value for the symbol
     * @return bool              true on success or false on failure
     */
    function setSymbol($attr, $value);

    /**
     * Set a text attribute
     *
     * @param  int   $attr    An attribute specifier, one of the text attribute constants
     * @param  int   $value   The attribute value
     * @return bool           true on success or false on failure
     */
    function setTextAttribute($attr, $value);
}
