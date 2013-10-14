<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl;

use Symfony\Component\Icu\CurrencyDataProvider;
use Symfony\Component\Intl\Exception\InvalidArgumentException;

/**
 * Provides access to currency-related data.
 *
 * @since  2.5
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @api
 */
class Currency
{
    /**
     * @var IcuCurrencyDataProvider
     */
    private static $dataProvider;

    /**
     * @var string[]|null
     */
    private static $currencies;

    /**
     * Returns all available currencies.
     *
     * @return string[] An array of ISO 4217 currency codes
     *
     * @api
     */
    public static function getCurrencies()
    {
        if (null === self::$currencies) {
            self::$currencies = self::getDataProvider()->getCurrencies();
        }

        return self::$currencies;
    }

    /**
     * Returns the symbol of a currency in the given locale.
     *
     * For example, the symbol of the US Dollar ("USD") in the locale "en_US" is
     * "$". If the resource data for the given locale contains no entry for the
     * given currency, then the ISO 4217 currency code is returned.
     *
     * If <code>null</code> is passed as locale, the result of
     * {@link \Locale::getDefault()} is used instead.
     *
     * @param string $currency      An ISO 4217 currency code (e.g. "EUR")
     * @param string $displayLocale The ICU locale code to return the symbol in
     *
     * @return string The currency symbol for the specified locale
     *
     * @throws Exception\InvalidArgumentException If the currency or the locale
     *                                            is invalid
     *
     * @api
     */
    public static function getSymbol($currency, $displayLocale = null)
    {
        if (!in_array($currency, self::getCurrencies(), true)) {
            throw new InvalidArgumentException('The currency "' . $currency . '" does not exist.');
        }

        if (null !== $displayLocale && !in_array($displayLocale, Locale::getLocales(), true)) {
            throw new InvalidArgumentException('The locale "' . $displayLocale . '" does not exist.');
        }

        if (null === $displayLocale) {
            $displayLocale = \Locale::getDefault();
        }

        try {
            return self::getDataProvider()->getSymbol($currency, $displayLocale);
        } catch (NoSuchEntryException $e) {
            return $currency;
        }
    }

    /**
     * Returns the name for this currency in the given locale.
     *
     * For example, the name of the Euro ("EUR") in the locale "ru_RU" is
     * "Евро". If the resource data for the given locale contains no entry for
     * the given currency, then the ISO 4217 currency code is returned.
     *
     * If <code>null</code> is passed as locale, the result of
     * {@link \Locale::getDefault()} is used instead.
     *
     * @param string $currency      An ISO 4217 currency code (e.g. "EUR")
     * @param string $displayLocale The ICU locale code to return the name in
     *
     * @return string The name of the currency
     *
     * @throws Exception\InvalidArgumentException If the currency or the locale
     *                                            is invalid
     *
     * @api
     */
    public static function getName($currency, $displayLocale = null)
    {
        if (!in_array($currency, self::getCurrencies(), true)) {
            throw new InvalidArgumentException('The currency "' . $currency . '" does not exist.');
        }

        if (null !== $displayLocale && !in_array($displayLocale, Locale::getLocales(), true)) {
            throw new InvalidArgumentException('The locale "' . $displayLocale . '" does not exist.');
        }

        if (null === $displayLocale) {
            $displayLocale = \Locale::getDefault();
        }

        try {
            return self::getDataProvider()->getName($currency, $displayLocale);
        } catch (NoSuchEntryException $e) {
            return $currency;
        }
    }

    /**
     * Returns the names of all known currencies in the specified locale.
     *
     * If the resource data for the given locale contains no entry for a
     * currency, then the ISO 4217 currency code is used instead.
     *
     * If <code>null</code> is passed as locale, the result of
     * {@link \Locale::getDefault()} is used instead.
     *
     * @param string $displayLocale The ICU locale code to return the names in
     *
     * @return string[] An array of currency names indexed by currency codes
     *
     * @throws Exception\InvalidArgumentException If the locale is invalid
     *
     * @api
     */
    public static function getNames($displayLocale = null)
    {
        if (null !== $displayLocale && !in_array($displayLocale, Locale::getLocales(), true)) {
            throw new InvalidArgumentException('The locale "' . $displayLocale . '" does not exist.');
        }

        if (null === $displayLocale) {
            $displayLocale = \Locale::getDefault();
        }

        return self::getDataProvider()->getNames($displayLocale);
    }

    /**
     * Returns the default number of fraction digits used with a currency.
     *
     * For example, the default number of fraction digits for the Euro is 2,
     * while for the Japanese Yen it's 0.
     *
     * @param string $currency An ISO 4217 currency code (e.g. "EUR")
     *
     * @return integer The number of digits after the comma
     *
     * @throws Exception\InvalidArgumentException If the currency is invalid
     *
     * @api
     */
    public static function getFractionDigits($currency)
    {
        if (!in_array($currency, self::getCurrencies(), true)) {
            throw new InvalidArgumentException('The currency "' . $currency . '" does not exist.');
        }

        return self::getDataProvider()->getFractionDigits($currency);
    }

    /**
     * Returns the rounding increment of a currency.
     *
     * The rounding increment indicates to which number a currency is rounded.
     * For example, 1230 rounded to the nearest 50 is 1250. 1.234 rounded to the
     * nearest 0.65 is 1.3.
     *
     * @param string $currency An ISO 4217 currency code (e.g. "EUR")
     *
     * @return integer The rounding increment
     *
     * @throws Exception\InvalidArgumentException If the currency is invalid
     *
     * @api
     */
    public static function getRoundingIncrement($currency)
    {
        if (!in_array($currency, self::getCurrencies(), true)) {
            throw new InvalidArgumentException('The currency "' . $currency . '" does not exist.');
        }

        return self::getDataProvider()->getRoundingIncrement($currency);
    }

    /**
     * Returns the ISO 4217 numeric code of a currency.
     *
     * For example, the numeric code of the Canadian Dollar ("CAD") is 124. If
     * no numeric code is available for a currency, 0 is returned.
     *
     * @param string $currency An ISO 4217 currency code (e.g. "EUR")
     *
     * @return integer The numeric code
     *
     * @throws Exception\InvalidArgumentException If the currency is invalid
     *
     * @api
     */
    public static function getNumericCode($currency)
    {
        if (!in_array($currency, self::getCurrencies(), true)) {
            throw new InvalidArgumentException('The currency "' . $currency . '" does not exist.');
        }

        return self::getDataProvider()->getNumericCode($currency);
    }

    /**
     * @return CurrencyDataProvider
     */
    private static function getDataProvider()
    {
        if (null === self::$dataProvider) {
            self::$dataProvider = new CurrencyDataProvider(
                CurrencyDataProvider::getResourceDirectory(),
                Intl::getEntryReader()
            );
        }

        return self::$dataProvider;
    }

    /**
     * This class must not be instantiated.
     */
    private function __construct() {}
}
