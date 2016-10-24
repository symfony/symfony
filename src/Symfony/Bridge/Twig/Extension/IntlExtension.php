<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Extension;

use Symfony\Component\Intl\DateFormatter\IntlDateFormatter;
use Symfony\Component\Intl\NumberFormatter\NumberFormatter;
use Symfony\Component\Intl\Intl;

/**
 * Provides integration of the Intl component with Twig.
 *
 * @author Jules Pietri <jules@heahprod.com>
 */
class IntlExtension extends \Twig_Extension
{
    private $defaultLocale;
    private $dateFormatters = array();
    private $numberFormatters = array();
    private $dateFormats = array(
        'none' => IntlDateFormatter::NONE,
        'short' => IntlDateFormatter::SHORT,
        'medium' => IntlDateFormatter::MEDIUM,
        'long' => IntlDateFormatter::LONG,
        'full' => IntlDateFormatter::FULL,
    );
    private $numberTypes = array(
        'default' => NumberFormatter::TYPE_DEFAULT,
        'int32' => NumberFormatter::TYPE_INT32,
        'int64' => NumberFormatter::TYPE_INT64,
        'double' => NumberFormatter::TYPE_DOUBLE,
        'currency' => NumberFormatter::TYPE_CURRENCY,
    );
    private $numberStyles = array(
        'decimal' => NumberFormatter::DECIMAL,
        'currency' => NumberFormatter::CURRENCY,
        'percent' => NumberFormatter::PERCENT,
        'scientific' => NumberFormatter::SCIENTIFIC,
        'spellout' => NumberFormatter::SPELLOUT,
        'ordinal' => NumberFormatter::ORDINAL,
        'duration' => NumberFormatter::DURATION,
    );
    private $numberAttributes = array(
        'parse_intl_only' => NumberFormatter::PARSE_INT_ONLY,
        'grouping_used' => NumberFormatter::GROUPING_USED,
        'decimal_always_shown' => NumberFormatter::DECIMAL_ALWAYS_SHOWN,
        'max_integer_digit' => NumberFormatter::MAX_INTEGER_DIGITS,
        'min_integer_digit' => NumberFormatter::MIN_INTEGER_DIGITS,
        'integer_digit' => NumberFormatter::INTEGER_DIGITS,
        'max_fraction_digit' => NumberFormatter::MAX_FRACTION_DIGITS,
        'min_fraction_digit' => NumberFormatter::MIN_FRACTION_DIGITS,
        'fraction_digit' => NumberFormatter::FRACTION_DIGITS,
        'multiplier' => NumberFormatter::MULTIPLIER,
        'grouping_size' => NumberFormatter::GROUPING_SIZE,
        'rounding_mode' => NumberFormatter::ROUNDING_MODE,
        'rounding_increment' => NumberFormatter::ROUNDING_INCREMENT,
        'format_width' => NumberFormatter::FORMAT_WIDTH,
        'padding_position' => NumberFormatter::PADDING_POSITION,
        'secondary_grouping_size' => NumberFormatter::SECONDARY_GROUPING_SIZE,
        'significant_digits_used' => NumberFormatter::SIGNIFICANT_DIGITS_USED,
        'min_significant_digits_used' => NumberFormatter::MIN_SIGNIFICANT_DIGITS,
        'max_significant_digits_used' => NumberFormatter::MAX_SIGNIFICANT_DIGITS,
        'lenient_parse' => NumberFormatter::LENIENT_PARSE,
    );
    private $numberRoundingAttributes = array(
        'ceiling' => NumberFormatter::ROUND_CEILING,
        'floor' => NumberFormatter::ROUND_FLOOR,
        'down' => NumberFormatter::ROUND_DOWN,
        'up' => NumberFormatter::ROUND_UP,
        'halfeven' => NumberFormatter::ROUND_HALFEVEN,
        'halfdown' => NumberFormatter::ROUND_HALFDOWN,
        'halfup' => NumberFormatter::ROUND_HALFUP,
    );
    private $numberPaddingAttributes = array(
        'before_prefix' => NumberFormatter::PAD_BEFORE_PREFIX,
        'after_prefix' => NumberFormatter::PAD_AFTER_PREFIX,
        'before_suffix' => NumberFormatter::PAD_BEFORE_SUFFIX,
        'after_suffix' => NumberFormatter::PAD_AFTER_SUFFIX,
    );
    private $numberTextAttributes = array(
        'positive_prefix' => NumberFormatter::POSITIVE_PREFIX,
        'positive_suffix' => NumberFormatter::POSITIVE_SUFFIX,
        'negative_prefix' => NumberFormatter::NEGATIVE_PREFIX,
        'negative_suffix' => NumberFormatter::NEGATIVE_SUFFIX,
        'padding_character' => NumberFormatter::PADDING_CHARACTER,
        'currency_mode' => NumberFormatter::CURRENCY_CODE,
        'default_ruleset' => NumberFormatter::DEFAULT_RULESET,
        'public_rulesets' => NumberFormatter::PUBLIC_RULESETS,
    );
    private $symbols = array(
        'decimal_separator' => NumberFormatter::DECIMAL_SEPARATOR_SYMBOL,
        'grouping_separator' => NumberFormatter::GROUPING_SEPARATOR_SYMBOL,
        'pattern_separator' => NumberFormatter::PATTERN_SEPARATOR_SYMBOL,
        'percent' => NumberFormatter::PERCENT_SYMBOL,
        'zero_digit' => NumberFormatter::ZERO_DIGIT_SYMBOL,
        'digit' => NumberFormatter::DIGIT_SYMBOL,
        'minus_sign' => NumberFormatter::MINUS_SIGN_SYMBOL,
        'plus_sign' => NumberFormatter::PLUS_SIGN_SYMBOL,
        'currency' => NumberFormatter::CURRENCY_SYMBOL,
        'intl_currency' => NumberFormatter::INTL_CURRENCY_SYMBOL,
        'monetary_separator' => NumberFormatter::MONETARY_SEPARATOR_SYMBOL,
        'exponential' => NumberFormatter::EXPONENTIAL_SYMBOL,
        'permill' => NumberFormatter::PERMILL_SYMBOL,
        'pad_escape' => NumberFormatter::PAD_ESCAPE_SYMBOL,
        'infinity' => NumberFormatter::INFINITY_SYMBOL,
        'nan' => NumberFormatter::NAN_SYMBOL,
        'significant_digit' => NumberFormatter::SIGNIFICANT_DIGIT_SYMBOL,
        'monetary_grouping_separator' => NumberFormatter::MONETARY_GROUPING_SEPARATOR_SYMBOL,
    );

    public function __construct($defaultLocale = null)
    {
        $this->defaultLocale = $defaultLocale;
    }

    /**
     * {@inheritdoc}
     */
    public function getFilters()
    {
        return array(
            new \Twig_SimpleFilter('localized_country', array($this, 'getLocalizedCountry')),
            new \Twig_SimpleFilter('localized_currency', array($this, 'getLocalizedCurrency')),
            new \Twig_SimpleFilter('localized_currency_name', array($this, 'getLocalizedCurrencyName')),
            new \Twig_SimpleFilter('currency_symbol', array($this, 'getCurrencySymbol')),
            new \Twig_SimpleFilter('localized_date', array($this, 'getLocalizedDate')),
            new \Twig_SimpleFilter('localized_language', array($this, 'getLocalizedLanguage')),
            new \Twig_SimpleFilter('localized_locale', array($this, 'getLocalizedLocale')),
            new \Twig_SimpleFilter('localized_number', array($this, 'getLocalizedNumber')),
        );
    }

    /**
     * Returns a localized country name for a given code.
     *
     * @param string      $country
     * @param string|null $displayLocale
     */
    public function getLocalizedCountry($country, $displayLocale = null)
    {
        return Intl::getRegionBundle()->getCountryName((string) $country, $displayLocale ?: $this->defaultLocale);
    }

    /**
     * Returns a localized currency amount for given number and currency code.
     *
     * @param mixed       $amount
     * @param string      $currency
     * @param string|null $displayLocale
     */
    public function getLocalizedCurrency($amount, $currency, $displayLocale = null)
    {
        $formatter = $this->createNumberFormatter(
            $displayLocale ?: $this->defaultLocale,
            'currency'
        );

        return $formatter->formatCurrency((float) $amount, $currency);
    }

    /**
     * Returns a localized currency amount for a given number and currency code.
     *
     * @param string      $currency
     * @param string|null $displayLocale
     */
    public function getLocalizedCurrencyName($currency, $displayLocale = null)
    {
        return Intl::getCurrencyBundle()->getCurrencyName((string) $currency, $displayLocale ?: $this->defaultLocale);
    }

    /**
     * Returns a currency symbol for a given code.
     *
     * @param string      $currency
     * @param string|null $displayLocale
     */
    public function getCurrencySymbol($currency, $displayLocale = null)
    {
        return Intl::getCurrencyBundle()->getCurrencySymbol((string) $currency, $displayLocale ?: $this->defaultLocale);
    }

    /**
     * Returns a date formatted for a given locale and options.
     *
     * @param \DateTimeInterface|string $date
     * @param string                    $dateFormat
     * @param string                    $timeFormat
     * @param string|null               $displayLocale
     * @param \DateTimeZone|string|null $timezone
     * @param string|null               $format
     * @param string                    $calendar
     *
     * @return bool|string
     *
     * @throws \Exception|\InvalidArgumentException If the date or a format is invalid
     */
    public function getLocalizedDate($date, $dateFormat = 'medium', $timeFormat = 'medium', $displayLocale = null, $timezone = null, $format = null, $calendar = 'gregorian')
    {
        if (is_string($date)) {
            $date = new \DateTime($date);
        }

        if (!$date instanceof \DateTimeInterface) {
            throw new \InvalidArgumentException(sprintf('Expected a \DateTimeInterface or time string got "%s"', is_object($date) ? get_class($date) : gettype($date)));
        }

        if (false !== $timezone) {
            if (!$timezone instanceof \DateTimeZone) {
                $timezone = new \DateTimeZone($timezone ?: date_default_timezone_get());
            }
            $date->setTimezone($timezone);
        }

        $formatter = $this->createDateFormatter(
            $displayLocale ?: $this->defaultLocale,
            $dateFormat,
            $timeFormat,
            $timezone,
            'gregorian' === $calendar ? IntlDateFormatter::GREGORIAN : IntlDateFormatter::TRADITIONAL,
            $format
        );

        return $formatter->format($date->getTimestamp());
    }

    /**
     * Returns a localized language name for a given code.
     *
     * @param string      $language
     * @param string|null $displayLocale
     */
    public function getLocalizedLanguage($language, $displayLocale = null)
    {
        $separator = false === strpos($language, '-') ? '_' : '-';
        $params = explode($separator, (string) $language, 2);
        $lang = $params[0];
        $region = isset($params[1]) ? $params[1] : null;

        return Intl::getLanguageBundle()->getLanguageName($lang, $region, $displayLocale ?: $this->defaultLocale);
    }

    /**
     * Returns a localized locale name for a given code.
     *
     * @param string      $locale
     * @param string|null $displayLocale
     */
    public function getLocalizedLocale($locale, $displayLocale = null)
    {
        return Intl::getLocaleBundle()->getLocaleName((string) $locale, $displayLocale ?: $this->defaultLocale);
    }

    /**
     * Returns a number formatted for the given locale and options.
     * 
     * @param mixed  $number
     * @param string $style
     * @param string $type
     * @param null   $displayLocale
     * @param array  $attr
     * @param array  $textAttr
     * @param array  $symbols
     *
     * @return bool|string
     *
     * @throws \InvalidArgumentException When the type or the style is invalid
     */
    public function getLocalizedNumber($number, $style = 'decimal', $type = 'default', $displayLocale = null, array $attr = array(), array $textAttr = array(), array $symbols = array())
    {
        $typeValues = $this->numberTypes;
        if (!isset($typeValues[$type])) {
            throw new \InvalidArgumentException(sprintf('The type "%s" does not exist. Known types are: "%s"', $type, implode('", "', array_keys($typeValues))));
        }

        $formatter = $this->createNumberFormatter(
            $displayLocale ?: $this->defaultLocale,
            $style
        );
        $this->configureNumberFormatterAttributes($formatter, $attr, $textAttr, $symbols);

        return $formatter->format($number, $typeValues[$type]);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'intl_bridge';
    }

    /**
     * @return IntlDateFormatter
     *
     * @throws \InvalidArgumentException On invalid format
     */
    private function createDateFormatter($locale, $dateFormat, $timeFormat, $timezone, $calendar, $format)
    {
        $formatValues = $this->dateFormats;
        if (!isset($formatValues[$dateFormat])) {
            throw new \InvalidArgumentException(sprintf('The date format "%s" does not exist. Known formats are: "%s"', $dateFormat, implode(', ', array_keys($formatValues))));
        }
        if (!isset($formatValues[$timeFormat])) {
            throw new \InvalidArgumentException(sprintf('The time format "%s" does not exist. Known formats are: "%s"', $timeFormat, implode(', ', array_keys($formatValues))));
        }

        $hash = $locale
                .($dateFormatValue = $formatValues[$dateFormat])
                .($timeFormatValue = $formatValues[$timeFormat])
                .$timezone->getName()
                .$calendar
                .$format
        ;
        if (!isset($this->dateFormatters[$hash])) {
            $this->dateFormatters[$hash] = IntlDateFormatter::create(
                $locale,
                $dateFormatValue,
                $timeFormatValue,
                $timezone,
                $calendar,
                $format
            );
        }

        return $this->dateFormatters[$hash];
    }

    /**
     * @return NumberFormatter
     *
     * @throws \InvalidArgumentException On invalid style
     */
    private function createNumberFormatter($locale, $style)
    {
        $styleValues = $this->numberStyles;
        if (!isset($styleValues[$style])) {
            throw new \InvalidArgumentException(sprintf('The style "%s" does not exist. Known styles are: "%s"', $style, implode('", "', array_keys($styleValues))));
        }

        $hash = $locale.$style;
        if (!isset($this->numberFormatters[$hash])) {
            $this->numberFormatters[$hash] = NumberFormatter::create($locale, $styleValues[$style]);
        }

        return $this->numberFormatters[$hash];
    }

    private function configureNumberFormatterAttributes(NumberFormatter $formatter, array $attr, array $textAttr, array $symbols)
    {
        $attributesKeys = $this->numberAttributes;
        $textAttributesKeys = $this->numberTextAttributes;
        $symbolsKeys = $this->symbols;

        foreach ($attr as $attribute => $value) {
            if (!isset($attributesKeys[$attribute])) {
                throw new \InvalidArgumentException(sprintf('The number formatter attribute "%s" does not exist. Known attributes are: "%s"', $attribute, implode('", "', array_keys($attributesKeys))));
            }

            $attributeKey = $attributesKeys[$attribute];

            if ('rounding_mode' === $attribute) {
                $roundingModes = $this->numberRoundingAttributes;
                if (!isset($roundingModes[$value])) {
                    throw new \InvalidArgumentException(sprintf('The number formatter rounding mode "%s" does not exist. Known modes are: "%s"', $value, implode('", "', array_keys($roundingModes))));
                }

                $formatter->setAttribute($attributeKey, $roundingModes[$value]);

                continue;
            }

            if ('padding_position' === $attribute) {
                $paddingPositions = $this->numberRoundingAttributes;
                if (!isset($paddingPositions[$value])) {
                    throw new \InvalidArgumentException(sprintf('The number formatter padding position "%s" does not exist. Known positions are: "%s"', $value, implode('", "', array_keys($paddingPositions))));
                }

                $formatter->setAttribute($attributeKey, $paddingPositions[$value]);

                continue;
            }

            $formatter->setAttribute($attributeKey, $value);
        }

        foreach ($textAttr as $attribute => $value) {
            if (!isset($textAttributesKeys[$attribute])) {
                throw new \InvalidArgumentException(sprintf('The number formatter text attribute "%s" does not exist. Known text attributes are: "%s"', $attribute, implode('", "', array_keys($textAttributesKeys))));
            }

            $formatter->setTextAttribute($textAttributesKeys[$attribute], $value);
        }

        foreach ($symbols as $symbol => $value) {
            if (!isset($symbolsKeys[$symbol])) {
                throw new \InvalidArgumentException(sprintf('The number formatter symbol "%s" does not exist. Known symbols are: "%s"', $symbol, implode('", "', array_keys($symbolsKeys))));
            }

            $formatter->setSymbol($symbolsKeys[$symbol], $value);
        }
    }
}
