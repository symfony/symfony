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

class Locale extends \Locale
{
    /**
     * The ICU data version that ships with Symfony
     */
    const ICU_DATA_VERSION = '49';

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
     */
    public static function getDisplayCountries($locale)
    {
        if (!isset(self::$countries[$locale])) {
            $bundle = \ResourceBundle::create($locale, self::getIcuDataDirectory().'/region');

            if (null === $bundle) {
                throw new \RuntimeException(sprintf('The country resource bundle could not be loaded for locale "%s"', $locale));
            }

            $collator = new \Collator($locale);
            $countries = array();
            $bundleCountries = $bundle->get('Countries') ?: array();

            foreach ($bundleCountries as $code => $name) {
                // Global countries (f.i. "America") have numeric codes
                // Countries have alphabetic codes
                // "ZZ" is the code for unknown country
                if (ctype_alpha($code) && 'ZZ' !== $code) {
                    $countries[$code] = $name;
                }
            }

            $fallbackLocale = self::getFallbackLocale($locale);
            if (null !== $fallbackLocale) {
                $countries = array_merge(self::getDisplayCountries($fallbackLocale), $countries);
            }

            $collator->asort($countries);

            self::$countries[$locale] = $countries;
        }

        return self::$countries[$locale];
    }

    /**
     * Returns all available country codes
     *
     * @return array              The country codes
     * @throws RuntimeException   When the resource bundles cannot be loaded
     */
    public static function getCountries()
    {
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
     */
    public static function getDisplayLanguages($locale)
    {
        if (!isset(self::$languages[$locale])) {
            $bundle = \ResourceBundle::create($locale, self::getIcuDataDirectory().'/lang');

            if (null === $bundle) {
                throw new \RuntimeException(sprintf('The language resource bundle could not be loaded for locale "%s"', $locale));
            }

            $collator = new \Collator($locale);
            $languages = array();
            $bundleLanguages = $bundle->get('Languages') ?: array();

            foreach ($bundleLanguages as $code => $name) {
                // "mul" is the code for multiple languages
                if ('mul' !== $code) {
                    $languages[$code] = $name;
                }
            }

            $fallbackLocale = self::getFallbackLocale($locale);
            if (null !== $fallbackLocale) {
                $languages = array_merge(self::getDisplayLanguages($fallbackLocale), $languages);
            }

            $collator->asort($languages);

            self::$languages[$locale] = $languages;
        }

        return self::$languages[$locale];
    }

    /**
     * Returns all available language codes
     *
     * @return array              The language codes
     * @throws RuntimeException   When the resource bundles cannot be loaded
     */
    public static function getLanguages()
    {
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
     */
    public static function getDisplayLocales($locale)
    {
        if (!isset(self::$locales[$locale])) {
            $bundle = \ResourceBundle::create($locale, self::getIcuDataDirectory().'/names');

            if (null === $bundle) {
                throw new \RuntimeException(sprintf('The locale resource bundle could not be loaded for locale "%s"', $locale));
            }

            $collator = new \Collator($locale);
            $locales = array();
            $bundleLocales = $bundle->get('Locales') ?: array();

            foreach ($bundleLocales as $code => $name) {
                $locales[$code] = $name;
            }

            $fallbackLocale = self::getFallbackLocale($locale);
            if (null !== $fallbackLocale) {
                $locales = array_merge(self::getDisplayLocales($fallbackLocale), $locales);
            }

            $collator->asort($locales);

            self::$locales[$locale] = $locales;
        }

        return self::$locales[$locale];
    }

    /**
     * Returns all available locale codes
     *
     * @return array              The locale codes
     * @throws RuntimeException   When the resource bundles cannot be loaded
     */
    public static function getLocales()
    {
        return array_keys(self::getDisplayLocales(self::getDefault()));
    }

    /**
     * Returns the ICU version as defined by the intl extension
     *
     * @return string|null The ICU version
     */
    public static function getIntlIcuVersion()
    {
        if (defined('INTL_ICU_VERSION')) {
            return INTL_ICU_VERSION;
        }

        try {
            $reflector = new \ReflectionExtension('intl');
        } catch (\ReflectionException $e) {
            return;
        }

        ob_start();
        $reflector->info();
        $output = strip_tags(ob_get_clean());
        preg_match('/^ICU version (?:=>)?(.*)$/m', $output, $matches);

        return trim($matches[1]);
    }

    /**
     * Returns the ICU Data version as defined by the intl extension
     *
     * @return string|null The ICU Data version
     */
    public static function getIntlIcuDataVersion()
    {
        if (defined('INTL_ICU_DATA_VERSION')) {
            return INTL_ICU_DATA_VERSION;
        }

        try {
            $reflector = new \ReflectionExtension('intl');
        } catch (\ReflectionException $e) {
            return;
        }

        ob_start();
        $reflector->info();
        $output = strip_tags(ob_get_clean());
        preg_match('/^ICU Data version (?:=>)?(.*)$/m', $output, $matches);

        return trim($matches[1]);
    }

    /**
     * Returns the ICU data version that ships with Symfony. If the environment variable USE_INTL_ICU_DATA_VERSION is
     * defined, it will try use the ICU data version as defined by the intl extension, if available.
     *
     * @return string The ICU data version that ships with Symfony
     */
    public static function getIcuDataVersion()
    {
        static $dataVersion;

        if (null === $dataVersion) {
            $dataVersion = self::ICU_DATA_VERSION;

            if (getenv('USE_INTL_ICU_DATA_VERSION') && self::getIntlIcuVersion()) {
                $dataVersion = self::getIntlIcuVersion();

                preg_match('/^(?P<version>[0-9]\.[0-9]|[0-9]{2,})/', $dataVersion, $matches);

                $dataVersion = $matches['version'];
            }
        }

        return $dataVersion;
    }

    /**
     * Returns the directory path of the ICU data that ships with Symfony
     *
     * @return string The path to the ICU data directory
     */
    public static function getIcuDataDirectory()
    {
        return __DIR__.'/Resources/data/'.self::getIcuDataVersion();
    }

    /**
     * Returns the fallback locale for a given locale, if any
     *
     * @param $locale             The locale to find the fallback for
     * @return string|null        The fallback locale, or null if no parent exists
     */
    protected static function getFallbackLocale($locale)
    {
        if (false === $pos = strrpos($locale, '_')) {
            return null;
        }

        return substr($locale, 0, $pos);
    }
}
