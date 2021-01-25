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

use Symfony\Component\Intl\Currencies;
use Symfony\Component\Intl\Exception\MethodArgumentNotImplementedException;
use Symfony\Component\Intl\Exception\MethodArgumentValueNotImplementedException;
use Symfony\Component\Intl\Exception\MethodNotImplementedException;
use Symfony\Component\Intl\Exception\NotImplementedException;
use Symfony\Component\Intl\Globals\IntlGlobals;
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
abstract class NumberFormatter
{
    /* Format style constants */
    public const PATTERN_DECIMAL = 0;
    public const DECIMAL = 1;
    public const CURRENCY = 2;
    public const PERCENT = 3;
    public const SCIENTIFIC = 4;
    public const SPELLOUT = 5;
    public const ORDINAL = 6;
    public const DURATION = 7;
    public const PATTERN_RULEBASED = 9;
    public const IGNORE = 0;
    public const DEFAULT_STYLE = 1;

    /* Format type constants */
    public const TYPE_DEFAULT = 0;
    public const TYPE_INT32 = 1;
    public const TYPE_INT64 = 2;
    public const TYPE_DOUBLE = 3;
    public const TYPE_CURRENCY = 4;

    /* Numeric attribute constants */
    public const PARSE_INT_ONLY = 0;
    public const GROUPING_USED = 1;
    public const DECIMAL_ALWAYS_SHOWN = 2;
    public const MAX_INTEGER_DIGITS = 3;
    public const MIN_INTEGER_DIGITS = 4;
    public const INTEGER_DIGITS = 5;
    public const MAX_FRACTION_DIGITS = 6;
    public const MIN_FRACTION_DIGITS = 7;
    public const FRACTION_DIGITS = 8;
    public const MULTIPLIER = 9;
    public const GROUPING_SIZE = 10;
    public const ROUNDING_MODE = 11;
    public const ROUNDING_INCREMENT = 12;
    public const FORMAT_WIDTH = 13;
    public const PADDING_POSITION = 14;
    public const SECONDARY_GROUPING_SIZE = 15;
    public const SIGNIFICANT_DIGITS_USED = 16;
    public const MIN_SIGNIFICANT_DIGITS = 17;
    public const MAX_SIGNIFICANT_DIGITS = 18;
    public const LENIENT_PARSE = 19;

    /* Text attribute constants */
    public const POSITIVE_PREFIX = 0;
    public const POSITIVE_SUFFIX = 1;
    public const NEGATIVE_PREFIX = 2;
    public const NEGATIVE_SUFFIX = 3;
    public const PADDING_CHARACTER = 4;
    public const CURRENCY_CODE = 5;
    public const DEFAULT_RULESET = 6;
    public const PUBLIC_RULESETS = 7;

    /* Format symbol constants */
    public const DECIMAL_SEPARATOR_SYMBOL = 0;
    public const GROUPING_SEPARATOR_SYMBOL = 1;
    public const PATTERN_SEPARATOR_SYMBOL = 2;
    public const PERCENT_SYMBOL = 3;
    public const ZERO_DIGIT_SYMBOL = 4;
    public const DIGIT_SYMBOL = 5;
    public const MINUS_SIGN_SYMBOL = 6;
    public const PLUS_SIGN_SYMBOL = 7;
    public const CURRENCY_SYMBOL = 8;
    public const INTL_CURRENCY_SYMBOL = 9;
    public const MONETARY_SEPARATOR_SYMBOL = 10;
    public const EXPONENTIAL_SYMBOL = 11;
    public const PERMILL_SYMBOL = 12;
    public const PAD_ESCAPE_SYMBOL = 13;
    public const INFINITY_SYMBOL = 14;
    public const NAN_SYMBOL = 15;
    public const SIGNIFICANT_DIGIT_SYMBOL = 16;
    public const MONETARY_GROUPING_SEPARATOR_SYMBOL = 17;

    /* Rounding mode values used by NumberFormatter::setAttribute() with NumberFormatter::ROUNDING_MODE attribute */
    public const ROUND_CEILING = 0;
    public const ROUND_FLOOR = 1;
    public const ROUND_DOWN = 2;
    public const ROUND_UP = 3;
    public const ROUND_HALFEVEN = 4;
    public const ROUND_HALFDOWN = 5;
    public const ROUND_HALFUP = 6;

    /* Pad position values used by NumberFormatter::setAttribute() with NumberFormatter::PADDING_POSITION attribute */
    public const PAD_BEFORE_PREFIX = 0;
    public const PAD_AFTER_PREFIX = 1;
    public const PAD_BEFORE_SUFFIX = 2;
    public const PAD_AFTER_SUFFIX = 3;

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
    private $attributes = [
        self::FRACTION_DIGITS => 0,
        self::GROUPING_USED => 1,
        self::ROUNDING_MODE => self::ROUND_HALFEVEN,
    ];

    /**
     * Holds the initialized attributes code.
     */
    private $initializedAttributes = [];

    /**
     * The supported styles to the constructor $styles argument.
     */
    private const SUPPORTED_STYLES = [
        'CURRENCY' => self::CURRENCY,
        'DECIMAL' => self::DECIMAL,
    ];

    /**
     * Supported attributes to the setAttribute() $attr argument.
     */
    private const SUPPORTED_ATTRIBUTES = [
        'FRACTION_DIGITS' => self::FRACTION_DIGITS,
        'GROUPING_USED' => self::GROUPING_USED,
        'ROUNDING_MODE' => self::ROUNDING_MODE,
    ];

    /**
     * The available rounding modes for setAttribute() usage with
     * NumberFormatter::ROUNDING_MODE. NumberFormatter::ROUND_DOWN
     * and NumberFormatter::ROUND_UP does not have a PHP only equivalent.
     */
    private const ROUNDING_MODES = [
        'ROUND_HALFEVEN' => self::ROUND_HALFEVEN,
        'ROUND_HALFDOWN' => self::ROUND_HALFDOWN,
        'ROUND_HALFUP' => self::ROUND_HALFUP,
        'ROUND_CEILING' => self::ROUND_CEILING,
        'ROUND_FLOOR' => self::ROUND_FLOOR,
        'ROUND_DOWN' => self::ROUND_DOWN,
        'ROUND_UP' => self::ROUND_UP,
    ];

    /**
     * The mapping between NumberFormatter rounding modes to the available
     * modes in PHP's round() function.
     *
     * @see https://php.net/round
     */
    private const PHP_ROUNDING_MAP = [
        self::ROUND_HALFDOWN => \PHP_ROUND_HALF_DOWN,
        self::ROUND_HALFEVEN => \PHP_ROUND_HALF_EVEN,
        self::ROUND_HALFUP => \PHP_ROUND_HALF_UP,
    ];

    /**
     * The list of supported rounding modes which aren't available modes in
     * PHP's round() function, but there's an equivalent. Keys are rounding
     * modes, values does not matter.
     */
    private const CUSTOM_ROUNDING_LIST = [
        self::ROUND_CEILING => true,
        self::ROUND_FLOOR => true,
        self::ROUND_DOWN => true,
        self::ROUND_UP => true,
    ];

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

    private const EN_SYMBOLS = [
        self::DECIMAL => ['.', ',', ';', '%', '0', '#', '-', '+', '¤', '¤¤', '.', 'E', '‰', '*', '∞', 'NaN', '@', ','],
        self::CURRENCY => ['.', ',', ';', '%', '0', '#', '-', '+', '¤', '¤¤', '.', 'E', '‰', '*', '∞', 'NaN', '@', ','],
    ];

    private const EN_TEXT_ATTRIBUTES = [
        self::DECIMAL => ['', '', '-', '', ' ', 'XXX', ''],
        self::CURRENCY => ['¤', '', '-¤', '', ' ', 'XXX'],
    ];

    /**
     * @param string|null $locale  The locale code. The only currently supported locale is "en" (or null using the default locale, i.e. "en")
     * @param int         $style   Style of the formatting, one of the format style constants.
     *                             The only supported styles are NumberFormatter::DECIMAL
     *                             and NumberFormatter::CURRENCY.
     * @param string      $pattern Not supported. A pattern string in case $style is NumberFormat::PATTERN_DECIMAL or
     *                             NumberFormat::PATTERN_RULEBASED. It must conform to  the syntax
     *                             described in the ICU DecimalFormat or ICU RuleBasedNumberFormat documentation
     *
     * @see https://php.net/numberformatter.create
     * @see https://unicode-org.github.io/icu-docs/apidoc/released/icu4c/classicu_1_1DecimalFormat.html#details
     * @see https://unicode-org.github.io/icu-docs/apidoc/released/icu4c/classicu_1_1RuleBasedNumberFormat.html#details
     *
     * @throws MethodArgumentValueNotImplementedException When $locale different than "en" or null is passed
     * @throws MethodArgumentValueNotImplementedException When the $style is not supported
     * @throws MethodArgumentNotImplementedException      When the pattern value is different than null
     */
    public function __construct(?string $locale = 'en', int $style = null, string $pattern = null)
    {
        if ('en' !== $locale && null !== $locale) {
            throw new MethodArgumentValueNotImplementedException(__METHOD__, 'locale', $locale, 'Only the locale "en" is supported');
        }

        if (!\in_array($style, self::SUPPORTED_STYLES)) {
            $message = sprintf('The available styles are: %s.', implode(', ', array_keys(self::SUPPORTED_STYLES)));
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
     * @param string|null $locale  The locale code. The only supported locale is "en" (or null using the default locale, i.e. "en")
     * @param int         $style   Style of the formatting, one of the format style constants.
     *                             The only currently supported styles are NumberFormatter::DECIMAL
     *                             and NumberFormatter::CURRENCY.
     * @param string      $pattern Not supported. A pattern string in case $style is NumberFormat::PATTERN_DECIMAL or
     *                             NumberFormat::PATTERN_RULEBASED. It must conform to  the syntax
     *                             described in the ICU DecimalFormat or ICU RuleBasedNumberFormat documentation
     *
     * @return static
     *
     * @see https://php.net/numberformatter.create
     * @see http://www.icu-project.org/apiref/icu4c/classDecimalFormat.html#_details
     * @see http://www.icu-project.org/apiref/icu4c/classRuleBasedNumberFormat.html#_details
     *
     * @throws MethodArgumentValueNotImplementedException When $locale different than "en" or null is passed
     * @throws MethodArgumentValueNotImplementedException When the $style is not supported
     * @throws MethodArgumentNotImplementedException      When the pattern value is different than null
     */
    public static function create(?string $locale = 'en', int $style = null, string $pattern = null)
    {
        return new static($locale, $style, $pattern);
    }

    /**
     * Format a currency value.
     *
     * @param string $currency The 3-letter ISO 4217 currency code indicating the currency to use
     *
     * @return string The formatted currency value
     *
     * @see https://php.net/numberformatter.formatcurrency
     * @see https://en.wikipedia.org/wiki/ISO_4217#Active_codes
     */
    public function formatCurrency(float $value, string $currency)
    {
        if (self::DECIMAL === $this->style) {
            return $this->format($value);
        }

        $symbol = Currencies::getSymbol($currency, 'en');
        $fractionDigits = Currencies::getFractionDigits($currency);

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
     * @see https://php.net/numberformatter.format
     *
     * @throws NotImplementedException                    If the method is called with the class $style 'CURRENCY'
     * @throws MethodArgumentValueNotImplementedException If the $type is different than TYPE_DEFAULT
     */
    public function format($value, int $type = self::TYPE_DEFAULT)
    {
        // The original NumberFormatter does not support this format type
        if (self::TYPE_CURRENCY === $type) {
            if (\PHP_VERSION_ID >= 80000) {
                throw new \ValueError(sprintf('The format type must be a NumberFormatter::TYPE_* constant (%s given).', $type));
            }

            trigger_error(__METHOD__.'(): Unsupported format type '.$type, \E_USER_WARNING);

            return false;
        }

        if (self::CURRENCY === $this->style) {
            throw new NotImplementedException(sprintf('"%s()" method does not support the formatting of currencies (instance with CURRENCY style). "%s".', __METHOD__, NotImplementedException::INTL_INSTALL_MESSAGE));
        }

        // Only the default type is supported.
        if (self::TYPE_DEFAULT !== $type) {
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
     * @return int|false The attribute value on success or false on error
     *
     * @see https://php.net/numberformatter.getattribute
     */
    public function getAttribute(int $attr)
    {
        return $this->attributes[$attr] ?? null;
    }

    /**
     * Returns formatter's last error code. Always returns the U_ZERO_ERROR class constant value.
     *
     * @return int The error code from last formatter call
     *
     * @see https://php.net/numberformatter.geterrorcode
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
     * @see https://php.net/numberformatter.geterrormessage
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
     * @see https://php.net/numberformatter.getlocale
     */
    public function getLocale(int $type = Locale::ACTUAL_LOCALE)
    {
        return 'en';
    }

    /**
     * Not supported. Returns the formatter's pattern.
     *
     * @return string|false The pattern string used by the formatter or false on error
     *
     * @see https://php.net/numberformatter.getpattern
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
     * @return string|false The symbol value or false on error
     *
     * @see https://php.net/numberformatter.getsymbol
     */
    public function getSymbol(int $attr)
    {
        return \array_key_exists($this->style, self::EN_SYMBOLS) && \array_key_exists($attr, self::EN_SYMBOLS[$this->style]) ? self::EN_SYMBOLS[$this->style][$attr] : false;
    }

    /**
     * Not supported. Returns a formatter text attribute value.
     *
     * @param int $attr An attribute specifier, one of the text attribute constants
     *
     * @return string|false The attribute value or false on error
     *
     * @see https://php.net/numberformatter.gettextattribute
     */
    public function getTextAttribute(int $attr)
    {
        return \array_key_exists($this->style, self::EN_TEXT_ATTRIBUTES) && \array_key_exists($attr, self::EN_TEXT_ATTRIBUTES[$this->style]) ? self::EN_TEXT_ATTRIBUTES[$this->style][$attr] : false;
    }

    /**
     * Not supported. Parse a currency number.
     *
     * @param string $value    The value to parse
     * @param string $currency Parameter to receive the currency name (reference)
     * @param int    $position Offset to begin the parsing on return this value will hold the offset at which the parsing ended
     *
     * @return float|false The parsed numeric value or false on error
     *
     * @see https://php.net/numberformatter.parsecurrency
     *
     * @throws MethodNotImplementedException
     */
    public function parseCurrency(string $value, string &$currency, int &$position = null)
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
     * @return int|float|false The parsed value or false on error
     *
     * @see https://php.net/numberformatter.parse
     */
    public function parse(string $value, int $type = self::TYPE_DOUBLE, int &$position = 0)
    {
        if (self::TYPE_DEFAULT === $type || self::TYPE_CURRENCY === $type) {
            if (\PHP_VERSION_ID >= 80000) {
                throw new \ValueError(sprintf('The format type must be a NumberFormatter::TYPE_* constant (%d given).', $type));
            }

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
     * @param int $attr An attribute specifier, one of the numeric attribute constants.
     *                  The only currently supported attributes are NumberFormatter::FRACTION_DIGITS,
     *                  NumberFormatter::GROUPING_USED and NumberFormatter::ROUNDING_MODE.
     *
     * @return bool true on success or false on failure
     *
     * @see https://php.net/numberformatter.setattribute
     *
     * @throws MethodArgumentValueNotImplementedException When the $attr is not supported
     * @throws MethodArgumentValueNotImplementedException When the $value is not supported
     */
    public function setAttribute(int $attr, int $value)
    {
        if (!\in_array($attr, self::SUPPORTED_ATTRIBUTES)) {
            $message = sprintf(
                'The available attributes are: %s',
                implode(', ', array_keys(self::SUPPORTED_ATTRIBUTES))
            );

            throw new MethodArgumentValueNotImplementedException(__METHOD__, 'attr', $value, $message);
        }

        if (self::SUPPORTED_ATTRIBUTES['ROUNDING_MODE'] === $attr && $this->isInvalidRoundingMode($value)) {
            $message = sprintf(
                'The supported values for ROUNDING_MODE are: %s',
                implode(', ', array_keys(self::ROUNDING_MODES))
            );

            throw new MethodArgumentValueNotImplementedException(__METHOD__, 'attr', $value, $message);
        }

        if (self::SUPPORTED_ATTRIBUTES['GROUPING_USED'] === $attr) {
            $value = $this->normalizeGroupingUsedValue($value);
        }

        if (self::SUPPORTED_ATTRIBUTES['FRACTION_DIGITS'] === $attr) {
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
     * @see https://php.net/numberformatter.setpattern
     * @see http://www.icu-project.org/apiref/icu4c/classDecimalFormat.html#_details
     *
     * @throws MethodNotImplementedException
     */
    public function setPattern(string $pattern)
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
     * @see https://php.net/numberformatter.setsymbol
     *
     * @throws MethodNotImplementedException
     */
    public function setSymbol(int $attr, string $value)
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
     * @see https://php.net/numberformatter.settextattribute
     *
     * @throws MethodNotImplementedException
     */
    public function setTextAttribute(int $attr, string $value)
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
     * @see http://en.wikipedia.org/wiki/Swedish_rounding
     * @see http://www.docjar.com/html/api/com/ibm/icu/util/Currency.java.html#1007
     */
    private function roundCurrency(float $value, string $currency): float
    {
        $fractionDigits = Currencies::getFractionDigits($currency);
        $roundingIncrement = Currencies::getRoundingIncrement($currency);

        // Round with the formatter rounding mode
        $value = $this->round($value, $fractionDigits);

        // Swiss rounding
        if (0 < $roundingIncrement && 0 < $fractionDigits) {
            $roundingFactor = $roundingIncrement / 10 ** $fractionDigits;
            $value = round($value / $roundingFactor) * $roundingFactor;
        }

        return $value;
    }

    /**
     * Rounds a value.
     *
     * @param int|float $value The value to round
     *
     * @return int|float The rounded value
     */
    private function round($value, int $precision)
    {
        $precision = $this->getUninitializedPrecision($value, $precision);

        $roundingModeAttribute = $this->getAttribute(self::ROUNDING_MODE);
        if (isset(self::PHP_ROUNDING_MAP[$roundingModeAttribute])) {
            $value = round($value, $precision, self::PHP_ROUNDING_MAP[$roundingModeAttribute]);
        } elseif (isset(self::CUSTOM_ROUNDING_LIST[$roundingModeAttribute])) {
            $roundingCoef = 10 ** $precision;
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
     * @param int|float $value The numeric value to format
     */
    private function formatNumber($value, int $precision): string
    {
        $precision = $this->getUninitializedPrecision($value, $precision);

        return number_format($value, $precision, '.', $this->getAttribute(self::GROUPING_USED) ? ',' : '');
    }

    /**
     * Returns the precision value if the DECIMAL style is being used and the FRACTION_DIGITS attribute is uninitialized.
     *
     * @param int|float $value The value to get the precision from if the FRACTION_DIGITS attribute is uninitialized
     */
    private function getUninitializedPrecision($value, int $precision): int
    {
        if (self::CURRENCY === $this->style) {
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
     */
    private function isInitializedAttribute(string $attr): bool
    {
        return isset($this->initializedAttributes[$attr]);
    }

    /**
     * Returns the numeric value using the $type to convert to the right data type.
     *
     * @param mixed $value The value to be converted
     *
     * @return int|float|false The converted value
     */
    private function convertValueDataType($value, int $type)
    {
        if (self::TYPE_DOUBLE === $type) {
            $value = (float) $value;
        } elseif (self::TYPE_INT32 === $type) {
            $value = $this->getInt32Value($value);
        } elseif (self::TYPE_INT64 === $type) {
            $value = $this->getInt64Value($value);
        }

        return $value;
    }

    /**
     * Convert the value data type to int or returns false if the value is out of the integer value range.
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
     * @return int|float|false The converted value
     */
    private function getInt64Value($value)
    {
        if ($value > self::$int64Max || $value < -self::$int64Max - 1) {
            return false;
        }

        if (\PHP_INT_SIZE !== 8 && ($value > self::$int32Max || $value < -self::$int32Max - 1)) {
            return (float) $value;
        }

        return (int) $value;
    }

    /**
     * Check if the rounding mode is invalid.
     */
    private function isInvalidRoundingMode(int $value): bool
    {
        if (\in_array($value, self::ROUNDING_MODES, true)) {
            return false;
        }

        return true;
    }

    /**
     * Returns the normalized value for the GROUPING_USED attribute. Any value that can be converted to int will be
     * cast to Boolean and then to int again. This way, negative values are converted to 1 and string values to 0.
     */
    private function normalizeGroupingUsedValue($value): int
    {
        return (int) (bool) (int) $value;
    }

    /**
     * Returns the normalized value for the FRACTION_DIGITS attribute.
     */
    private function normalizeFractionDigitsValue($value): int
    {
        return (int) $value;
    }
}
