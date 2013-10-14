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

use Symfony\Component\Icu\LocaleDataProvider;
use Symfony\Component\Intl\Exception\InvalidArgumentException;
use Symfony\Component\Intl\Exception\NoSuchEntryException;

/**
 * Provides access to locale-related data.
 *
 * @since  2.5
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @api
 */
class Locale extends \Locale
{
    /**
     * @var IcuCurrencyDataProvider
     */
    private static $dataProvider;

    /**
     * @var string[]|null
     */
    private static $locales;

    /**
     * Returns all available locales.
     *
     * @return string[] A list of ICU locale codes
     *
     * @api
     */
    public static function getLocales()
    {
        if (null === self::$locales) {
            self::$locales = self::getDataProvider()->getLocales();
        }

        return self::$locales;
    }

    /**
     * Returns the name of a locale in a specified display locale.
     *
     * If there is no suitable name found for a display locale, the ICU locale
     * code is used instead.
     *
     * If <code>null</code> is passed as display locale, the result of
     * {@link \Locale::getDefault()} is used instead.
     *
     * @param string $locale        The ICU locale code to return the name of
     *                              (e.g. "de_AT")
     * @param string $displayLocale The ICU locale code to return the name in
     *
     * @return string The name of the locale
     *
     * @throws InvalidArgumentException If the locale or the display locale is
     *                                  invalid
     *
     * @see getNames
     *
     * @api
     */
    public static function getName($locale, $displayLocale = null)
    {
        if (!in_array($locale, self::getLocales(), true)) {
            throw new InvalidArgumentException('The locale "' . $locale . '" does not exist.');
        }

        if (null !== $displayLocale && !in_array($displayLocale, self::getLocales(), true)) {
            throw new InvalidArgumentException('The locale "' . $displayLocale . '" does not exist.');
        }

        if (null === $displayLocale) {
            $displayLocale = \Locale::getDefault();
        }

        return self::getDataProvider()->getName($locale, $displayLocale);
    }

    /**
     * Returns the names of all known locales in a specified display locale.
     *
     * If <code>null</code> is passed as display locale, the result of
     * {@link \Locale::getDefault()} is used instead.
     *
     * @param string $displayLocale The ICU locale code to return the names in
     *
     * @return string[] A list of locale names indexed by the corresponding ICU
     *                  locale codes
     *
     * @throws InvalidArgumentException If the display locale is invalid
     *
     * @see getName
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
     * Alias of {@link getName()}.
     *
     * This method exists for compatibility with the {@link \Locale} class.
     *
     * @param string $locale        The ICU locale code to return the name of
     *                              (e.g. "de_AT")
     * @param string $displayLocale The ICU locale code to return the name in
     *
     * @return string The name of the locale
     *
     * @throws InvalidArgumentException If the locale or the display locale is
     *                                  invalid
     *
     * @see getName
     */
    public static function getDisplayName($locale, $displayLocale = null)
    {
        return static::getName($locale, $displayLocale);
    }

    /**
     * Alias of {@link getNames()}.
     *
     * This method exists for compatibility with the {@link \Locale} class.
     *
     * @param string $displayLocale The ICU locale code to return the names in
     *
     * @return string[] A list of locale names indexed by the corresponding ICU
     *                  locale codes
     *
     * @throws InvalidArgumentException If the display locale is invalid
     *
     * @see getNames
     */
    public static function getDisplayNames($locale, $displayLocale = null)
    {
        return static::getNames($locale, $displayLocale);
    }

    /**
     * Returns a list of locale aliases.
     *
     * @return string[] An array with locale aliases as keys and ICU locale
     *                  codes as values
     *
     * @api
     */
    public static function getAliases()
    {
        return self::getDataProvider()->getAliases();
    }

    /**
     * @return LocaleDataProvider
     */
    private static function getDataProvider()
    {
        if (null === self::$dataProvider) {
            self::$dataProvider = new LocaleDataProvider(
                LocaleDataProvider::getResourceDirectory(),
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
