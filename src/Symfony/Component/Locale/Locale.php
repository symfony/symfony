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
     * @param  string $locale     The locale to use for the country names
     * @return array              The country names with their codes as keys
     * @throws RuntimeException   When the resource bundles cannot be loaded
     */
    static public function getDisplayCountries($locale)
    {
        if (!isset(self::$countries[$locale])) {
            $bundle = \ResourceBundle::create($locale, __DIR__.'/Resources/data/region');

            if (null === $bundle) {
                throw new \RuntimeException('The country resource bundle could not be loaded');
            }

            $collator = new \Collator($locale);
            $countries = array();

            foreach ($bundle->get('Countries') as $code => $name) {
                // Global countries (f.i. "America") have numeric codes
                // Countries have alphabetic codes
                // "ZZ" is the code for unknown country
                if (ctype_alpha($code) && 'ZZ' !== $code) {
                    $countries[$code] = $name;
                }
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
    static public function getCountries()
    {
        return array_keys(self::getDisplayCountries(self::getDefault()));
    }

    /**
     * Returns the language names for a locale
     *
     * @param  string $locale     The locale to use for the language names
     * @return array              The language names with their codes as keys
     * @throws RuntimeException   When the resource bundles cannot be loaded
     */
    static public function getDisplayLanguages($locale)
    {
        if (!isset(self::$languages[$locale])) {
            $bundle = \ResourceBundle::create($locale, __DIR__.'/Resources/data/lang');

            if (null === $bundle) {
                throw new \RuntimeException('The language resource bundle could not be loaded');
            }

            $collator = new \Collator($locale);
            $languages = array();

            foreach ($bundle->get('Languages') as $code => $name) {
                // "mul" is the code for multiple languages
                if ('mul' !== $code) {
                    $languages[$code] = $name;
                }
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
    static public function getLanguages()
    {
        return array_keys(self::getDisplayLanguages(self::getDefault()));
    }

    /**
     * Returns the locale names for a locale
     *
     * @param  string $locale     The locale to use for the locale names
     * @return array              The locale names with their codes as keys
     * @throws RuntimeException   When the resource bundles cannot be loaded
     */
    static public function getDisplayLocales($locale)
    {
        if (!isset(self::$locales[$locale])) {
            $bundle = \ResourceBundle::create($locale, __DIR__.'/Resources/data/names');

            if (null === $bundle) {
                throw new \RuntimeException('The locale resource bundle could not be loaded');
            }

            $collator = new \Collator($locale);
            $locales = array();

            foreach ($bundle->get('Locales') as $code => $name) {
                $locales[$code] = $name;
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
    static public function getLocales()
    {
        return array_keys(self::getDisplayLocales(self::getDefault()));
    }
}
