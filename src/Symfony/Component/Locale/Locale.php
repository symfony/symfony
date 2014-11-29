<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Locale;

use Symfony\Component\Icu\IcuData;
use Symfony\Component\Intl\Intl;

/**
 * Helper class for dealing with locale strings.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @deprecated Deprecated since version 2.3, to be removed in 3.0.
 *             Use {@link \Locale} and {@link \Symfony\Component\Intl\Intl} instead.
 */
class Locale extends \Locale
{
    /**
     * Caches the countries in different locales
     * @var array
     */
    protected static $countries = array();

    /**
     * Caches the languages in different locales
     * @var array
     */
    protected static $languages = array();

    /**
     * Caches the different locales
     * @var array
     */
    protected static $locales = array();

    /**
     * Returns the country names for a locale
     *
     * @param string $locale The locale to use for the country names
     *
     * @return array              The country names with their codes as keys
     *
     * @throws \RuntimeException  When the resource bundles cannot be loaded
     *
     * @deprecated Deprecated since version 2.7, to be removed in Symfony 3.0.
     *             Use {@link \Symfony\Component\Intl\Intl::getRegionBundle()->getCountryNames()} instead.
     */
    public static function getDisplayCountries($locale)
    {
        trigger_error('Deprecated since version 2.7, to be removed in Symfony 3.0. Use \Symfony\Component\Intl\Intl::getRegionBundle()->getCountryNames() instead.', E_USER_DEPRECATED);

        if (!isset(self::$countries[$locale])) {
            self::$countries[$locale] = Intl::getRegionBundle()->getCountryNames($locale);
        }

        return self::$countries[$locale];
    }

    /**
     * Returns all available country codes
     *
     * @return array              The country codes
     *
     * @throws \RuntimeException  When the resource bundles cannot be loaded
     *
     * @deprecated Deprecated since version 2.7, to be removed in Symfony 3.0.
     *             Use {array_keys(@link \Symfony\Component\Intl\Intl::getRegionBundle()->getCountryNames())} instead.
     */
    public static function getCountries()
    {
        trigger_error('Deprecated since version 2.7, to be removed in Symfony 3.0. Use array_keys(\Symfony\Component\Intl\Intl::getRegionBundle()->getCountryNames()) instead.', E_USER_DEPRECATED);

        return array_keys(self::getDisplayCountries(self::getDefault()));
    }

    /**
     * Returns the language names for a locale
     *
     * @param string $locale The locale to use for the language names
     *
     * @return array              The language names with their codes as keys
     *
     * @throws \RuntimeException  When the resource bundles cannot be loaded
     *
     * @deprecated Deprecated since version 2.7, to be removed in Symfony 3.0.
     *             Use {@link \Symfony\Component\Intl\Intl::getLanguageBundle()->getLanguageNames()} instead.
     */
    public static function getDisplayLanguages($locale)
    {
        trigger_error('Deprecated since version 2.7, to be removed in Symfony 3.0. Use \Symfony\Component\Intl\Intl::getLanguageBundle()->getLanguageNames() instead.', E_USER_DEPRECATED);

        if (!isset(self::$languages[$locale])) {
            self::$languages[$locale] = Intl::getLanguageBundle()->getLanguageNames($locale);
        }

        return self::$languages[$locale];
    }

    /**
     * Returns all available language codes
     *
     * @return array              The language codes
     *
     * @throws \RuntimeException  When the resource bundles cannot be loaded
     *
     * @deprecated Deprecated since version 2.7, to be removed in Symfony 3.0.
     *             Use {array_keys(@link \Symfony\Component\Intl\Intl::getLanguageBundle()->getLanguageNames())} instead.
     */
    public static function getLanguages()
    {
        trigger_error('Deprecated since version 2.7, to be removed in Symfony 3.0. Use array_keys(\Symfony\Component\Intl\Intl::getLanguageBundle()->getLanguageNames()) instead.', E_USER_DEPRECATED);

        return array_keys(self::getDisplayLanguages(self::getDefault()));
    }

    /**
     * Returns the locale names for a locale
     *
     * @param string $locale The locale to use for the locale names
     *
     * @return array              The locale names with their codes as keys
     *
     * @throws \RuntimeException  When the resource bundles cannot be loaded
     *
     * @deprecated Deprecated since version 2.7, to be removed in Symfony 3.0.
     *             Use {@link \Symfony\Component\Intl\Intl::getLocaleBundle()->getLocaleNames()} instead.
     */
    public static function getDisplayLocales($locale)
    {
        trigger_error('Deprecated since version 2.7, to be removed in Symfony 3.0. Use \Symfony\Component\Intl\Intl::getLocaleBundle()->getLocaleNames() instead.', E_USER_DEPRECATED);

        if (!isset(self::$locales[$locale])) {
            self::$locales[$locale] = Intl::getLocaleBundle()->getLocaleNames($locale);
        }

        return self::$locales[$locale];
    }

    /**
     * Returns all available locale codes
     *
     * @return array              The locale codes
     *
     * @throws \RuntimeException  When the resource bundles cannot be loaded
     *
     * @deprecated Deprecated since version 2.7, to be removed in Symfony 3.0.
     *             Use {array_keys(@link \Symfony\Component\Intl\Intl::getLocaleBundle()->getLocaleNames())} instead.
     */
    public static function getLocales()
    {
        trigger_error('Deprecated since version 2.7, to be removed in Symfony 3.0. Use array_keys(\Symfony\Component\Intl\Intl::getLocaleBundle()->getLocaleNames()) instead.', E_USER_DEPRECATED);

        return array_keys(self::getDisplayLocales(self::getDefault()));
    }

    /**
     * Returns the ICU version as defined by the intl extension
     *
     * @return string|null The ICU version
     */
    public static function getIntlIcuVersion()
    {
        return Intl::getIcuVersion();
    }

    /**
     * Returns the ICU Data version as defined by the intl extension
     *
     * @return string|null The ICU Data version
     */
    public static function getIntlIcuDataVersion()
    {
        return Intl::getIcuDataVersion();
    }

    /**
     * Returns the ICU data version that ships with Symfony. If the environment variable USE_INTL_ICU_DATA_VERSION is
     * defined, it will try use the ICU data version as defined by the intl extension, if available.
     *
     * @return string The ICU data version that ships with Symfony
     */
    public static function getIcuDataVersion()
    {
        return Intl::getIcuDataVersion();
    }

    /**
     * Returns the directory path of the ICU data that ships with Symfony
     *
     * @return string The path to the ICU data directory
     */
    public static function getIcuDataDirectory()
    {
        return IcuData::getResourceDirectory();
    }

    /**
     * Returns the fallback locale for a given locale, if any
     *
     * @param string $locale The locale to find the fallback for.
     *
     * @return string|null The fallback locale, or null if no parent exists
     */
    protected static function getFallbackLocale($locale)
    {
        if (false === $pos = strrpos($locale, '_')) {
            return;
        }

        return substr($locale, 0, $pos);
    }
}
