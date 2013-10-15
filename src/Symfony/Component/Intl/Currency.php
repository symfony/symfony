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
use Symfony\Component\Intl\Exception\MissingResourceException;

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
     * @var CurrencyDataProvider
     */
    private static $dataProvider;

    /**
     * @var string[]|null
     */
    private static $currencies;

    /**
     * Returns all available currencies.
     *
     * @return string[] An array of ISO 4217 three-letter currency codes
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
     * Returns whether the given ISO 4217 currency code exists.
     *
     * This method does not canonicalize the given currency code. Specifically,
     * it will return false if the currency is not correctly cased or is
     * provided as numeric code instead of as three-letter code. For
     * example, this method returns false for "cad" and 124 (the numeric ISO
     * 4217 code of the Canadian Dollar), but true for "CAD".
     *
     * If you want to support the lowercase currencies, you should manually
     * canonicalize the currency code prior to calling this method. If you
     * want to support numeric codes, you should convert them into three-letter
     * codes by calling {@link forNumericCode()}.
     *
     * @param string $currency A canonical ISO 4217 three-letter currency code
     *                         (e.g. "EUR")
     *
     * @return Boolean Whether the currency code exists
     *
     * @see canonicalize
     * @see forNumericCode
     *
     * @api
     */
    public static function exists($currency)
    {
        if (null === self::$lookupTable) {
            self::$lookupTable = array_flip(static::getCurrencies());
        }

        return isset(self::$lookupTable[$currency]);
    }

    /**
     * Canonicalizes the given ISO 4217 currency code.
     *
     * The currency code is converted to uppercase during canonicalization. This
     * method does not check whether the given currency actually exists. In case
     * of doubt, you should pass the canonicalized currency to {@link exists()}.
     *
     * @param string $currency An ISO 4217 three-letter currency code (e.g. "EUR")
     *
     * @return string The canonicalized currency code
     *
     * @see exists
     *
     * @api
     */
    public static function canonicalize($currency)
    {
        return strtoupper($currency);
    }

    /**
     * Returns the symbol of a currency in the given locale.
     *
     * For example, the symbol of the US Dollar ("USD") in the locale "en_US" is
     * "$". If the resource data for the given locale contains no entry for the
     * given currency, then the ISO 4217 three-letter currency code is returned.
     *
     * If <code>null</code> is passed as locale, the result of
     * {@link \Locale::getDefault()} is used instead.
     *
     * @param string $currency      A canonical ISO 4217 three-letter currency
     *                              code (e.g. "EUR")
     * @param string $displayLocale The ICU locale code to return the symbol in
     *
     * @return string The currency symbol for the specified locale
     *
     * @throws InvalidArgumentException If the currency or the locale is invalid
     *
     * @api
     */
    public static function getSymbol($currency, $displayLocale = null)
    {
        if (!static::exists($currency)) {
            throw new InvalidArgumentException('The currency "' . $currency . '" does not exist.');
        }

        if (null !== $displayLocale && !Locale::exists($displayLocale)) {
            throw new InvalidArgumentException('The locale "' . $displayLocale . '" does not exist.');
        }

        if (null === $displayLocale) {
            $displayLocale = \Locale::getDefault();
        }

        return self::getDataProvider()->getSymbol($currency, $displayLocale);
    }

    /**
     * Returns the name of a currency in the given locale.
     *
     * For example, the name of the Euro ("EUR") in the locale "ru_RU" is
     * "Евро". If the resource data for the given locale contains no entry for
     * the given currency, then the ISO 4217 three-letter currency code is
     * returned.
     *
     * If <code>null</code> is passed as locale, the result of
     * {@link \Locale::getDefault()} is used instead.
     *
     * @param string $currency      A canonical ISO 4217 three-letter currency
     *                              code (e.g. "EUR")
     * @param string $displayLocale The ICU locale code to return the name in
     *
     * @return string The name of the currency
     *
     * @throws InvalidArgumentException If the currency or the locale is invalid
     *
     * @api
     */
    public static function getName($currency, $displayLocale = null)
    {
        if (!static::exists($currency)) {
            throw new InvalidArgumentException('The currency "' . $currency . '" does not exist.');
        }

        if (null !== $displayLocale && !Locale::exists($displayLocale)) {
            throw new InvalidArgumentException('The locale "' . $displayLocale . '" does not exist.');
        }

        if (null === $displayLocale) {
            $displayLocale = \Locale::getDefault();
        }

        return self::getDataProvider()->getName($currency, $displayLocale);
    }

    /**
     * Returns the names of all known currencies in the specified locale.
     *
     * If the resource data for the given locale contains no entry for a
     * currency, then the ISO 4217 three-letter currency code is used instead.
     *
     * If <code>null</code> is passed as locale, the result of
     * {@link \Locale::getDefault()} is used instead.
     *
     * @param string $displayLocale The ICU locale code to return the names in
     *
     * @return string[] An array of currency names indexed by ISO 4217
     *                  three-letter currency codes
     *
     * @throws InvalidArgumentException If the locale is invalid
     *
     * @api
     */
    public static function getNames($displayLocale = null)
    {
        if (null !== $displayLocale && !Locale::exists($displayLocale)) {
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
     * @param string $currency A canonical ISO 4217 three-letter currency code
     *                         (e.g. "EUR")
     *
     * @return integer The number of digits after the comma
     *
     * @throws InvalidArgumentException If the currency is invalid
     *
     * @api
     */
    public static function getFractionDigits($currency)
    {
        if (!static::exists($currency)) {
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
     * @param string $currency A canonical ISO 4217 three-letter currency code
     *                         (e.g. "EUR")
     *
     * @return integer The rounding increment
     *
     * @throws InvalidArgumentException If the currency is invalid
     *
     * @api
     */
    public static function getRoundingIncrement($currency)
    {
        if (!static::exists($currency)) {
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
     * @param string $currency A canonical ISO 4217 three-letter currency code
     *                         (e.g. "EUR")
     *
     * @return integer The numeric code
     *
     * @throws InvalidArgumentException If the currency is invalid
     *
     * @api
     */
    public static function getNumericCode($currency)
    {
        if (!static::exists($currency)) {
            throw new InvalidArgumentException('The currency "'.$currency.'" does not exist.');
        }

        return self::getDataProvider()->getNumericCode($currency);
    }

    /**
     * Returns the matching ISO 4217 three-letter codes for a numeric code.
     *
     * For example, the numeric code 124 belongs to the Canadian Dollar ("CAD").
     * Some numeric codes belong to multiple currencies. For example, the
     * number 428 is assigned to both the Latvian Ruble ("LVR") and the Latvian
     * Lats ("LVL"). For this reason, this method always returns an array.
     *
     * @param integer $numericCode An ISO 4217 numeric currency code (e.g. 124)
     *
     * @return string[] The matching ISO 4217 three-letter currency codes
     *
     * @throws InvalidArgumentException If the numeric code does not exist
     *
     * @api
     */
    public static function forNumericCode($numericCode)
    {
        try {
            return self::getDataProvider()->forNumericCode($numericCode);
        } catch (MissingResourceException $e) {
            throw new InvalidArgumentException(
                'The numeric currency code "'.$numericCode.'" does not exist.',
                0,
                $e
            );
        }
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
