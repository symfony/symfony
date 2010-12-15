<?php

namespace Symfony\Component\Locale;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
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
     * Returns the country names for a locale
     *
     * @param  string $locale     The locale to use for the country names
     * @return array              The country names with their codes as keys
     * @throws RuntimeException   When the resource bundles cannot be loaded
     */
    public static function getDisplayCountries($locale)
    {
        if (!isset(self::$countries[$locale])) {
            $bundle = new \ResourceBundle($locale, __DIR__.'/Resources/data/region');

            if ($bundle === null) {
                throw new \RuntimeException('The country resource bundle could not be loaded');
            }

            $collator = new \Collator($locale);
            $countries = array();

            foreach ($bundle->get('Countries') as $code => $name) {
                // Global countries (f.i. "America") have numeric codes
                // Countries have alphabetic codes
                // "ZZ" is the code for unknown country
                if (ctype_alpha($code) && $code !== 'ZZ') {
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
    public static function getCountries()
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
    public static function getDisplayLanguages($locale)
    {
        if (!isset(self::$languages[$locale])) {
            $bundle = new \ResourceBundle($locale, __DIR__.'/Resources/data/lang');

            if ($bundle === null) {
                throw new \RuntimeException('The language resource bundle could not be loaded');
            }

            $collator = new \Collator($locale);
            $languages = array();

            foreach ($bundle->get('Languages') as $code => $name) {
                // "mul" is the code for multiple languages
                if ($code !== 'mul') {
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
    public static function getLanguages()
    {
        return array_keys(self::getDisplayLanguages(self::getDefault()));
    }
}