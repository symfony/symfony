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
    private $dateFormatValues = array(
        'none' => IntlDateFormatter::NONE,
        'short' => IntlDateFormatter::SHORT,
        'medium' => IntlDateFormatter::MEDIUM,
        'long' => IntlDateFormatter::LONG,
        'full' => IntlDateFormatter::FULL,
    );
    private $numberFormatters = array();
    private $numberTypeValues = array(
        'default' => NumberFormatter::TYPE_DEFAULT,
        'int32' => NumberFormatter::TYPE_INT32,
        'int64' => NumberFormatter::TYPE_INT64,
        'double' => NumberFormatter::TYPE_DOUBLE,
        'currency' => NumberFormatter::TYPE_CURRENCY,
    );
    private $numberStyleValues = array(
        'decimal' => NumberFormatter::DECIMAL,
        'currency' => NumberFormatter::CURRENCY,
        'percent' => NumberFormatter::PERCENT,
        'scientific' => NumberFormatter::SCIENTIFIC,
        'spellout' => NumberFormatter::SPELLOUT,
        'ordinal' => NumberFormatter::ORDINAL,
        'duration' => NumberFormatter::DURATION,
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
     *
     * @return bool|string
     *
     * @throws \InvalidArgumentException When the type or the style is invalid
     */
    public function getLocalizedNumber($number, $style = 'decimal', $type = 'default', $displayLocale = null)
    {
        $typeValues = $this->numberTypeValues;
        if (!isset($typeValues[$type])) {
            throw new \InvalidArgumentException(sprintf('The type "%s" does not exist. Known types are: "%s"', $type, implode('", "', array_keys($typeValues))));
        }

        $formatter = $this->createNumberFormatter(
            $displayLocale ?: $this->defaultLocale,
            $style
        );

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
        $formatValues = $this->dateFormatValues;
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
        $styleValues = $this->numberStyleValues;
        if (!isset($styleValues[$style])) {
            throw new \InvalidArgumentException(sprintf('The style "%s" does not exist. Known styles are: "%s"', $style, implode('", "', array_keys($styleValues))));
        }

        $hash = $locale.$style;
        if (!isset($this->numberFormatters[$hash])) {
            $this->numberFormatters[$hash] = NumberFormatter::create($locale, $styleValues[$style]);
        }

        return $this->numberFormatters[$hash];
    }
}
