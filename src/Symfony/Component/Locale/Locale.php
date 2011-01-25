<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Locale;

class Locale
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
    public static function getDisplayCountries($locale)
    {
        if (!isset(self::$countries[$locale])) {
            $bundle = new \ResourceBundle($locale, __DIR__.'/Resources/data/region');

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
    public static function getLanguages()
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
    public static function getDisplayLocales($locale)
    {
        if (!isset(self::$locales[$locale])) {
            $bundle = new \ResourceBundle($locale, __DIR__.'/Resources/data/names');

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
    public static function getLocales()
    {
        return array_keys(self::getDisplayLocales(self::getDefault()));
    }

    /**
     * Returns the best available locale based on HTTP "Accept-Language" header according to RFC 2616
     *
     * @param  string $header     The string containing the "Accept-Language" header value
     * @return string             The corresponding locale code
     * @see    http://www.php.net/manual/en/locale.acceptfromhttp.php
     * @throws RuntimeException   When the intl extension is not loaded
     */
    public static function acceptFromHttp($header)
    {
        self::isIntlExtensionAvailable();
        return \Locale::acceptFromHttp($header);
    }

    /**
     * Returns a correctly ordered and delimited locale code
     *
     * @param  array   $subtags   A keyed array where the keys identify the particular locale code subtag
     * @return string             The corresponding locale code
     * @see    http://www.php.net/manual/en/locale.composelocale.php
     * @throws RuntimeException   When the intl extension is not loaded
     */
    public static function composeLocale(array $subtags)
    {
        self::isIntlExtensionAvailable();
        return \Locale::composeLocale($subtags);
    }

    /**
     * Checks if a language tag filter matches with locale
     *
     * @param  string   $langtag  The language tag to check
     * @param  string   $locale   The language range to check against
     * @return string             The corresponding locale code
     * @see    http://www.php.net/manual/en/locale.filtermatches.php
     * @throws RuntimeException   When the intl extension is not loaded
     */
    public static function filterMatches($langtag, $locale, $canonicalize = false)
    {
        self::isIntlExtensionAvailable();
        return \Locale::filterMatches($langtag, $locale, $canonicalize);
    }

    /**
     * Returns the variants for the input locale
     *
     * @param  string   $locale   The locale to extract the variants from
     * @return array              The locale variants
     * @see    http://www.php.net/manual/en/locale.getallvariants.php
     * @throws RuntimeException   When the intl extension is not loaded
     */
    public static function getAllVariants($locale)
    {
        self::isIntlExtensionAvailable();
        return \Locale::getAllVariants($locale);
    }

    /**
     * Returns the default locale
     *
     * @return string             The default locale code
     * @see    http://www.php.net/manual/en/locale.getdefault.php
     * @throws RuntimeException   When the intl extension is not loaded
     */
    public static function getDefault()
    {
        self::isIntlExtensionAvailable();
        return \Locale::getDefault();
    }

    /**
     * Returns the localized display name for the locale language
     *
     * @param  string  $locale    The locale code to return the display language from
     * @param  string  $inLocale  Optional format locale code to use to display the language name
     * @return string             The localized language display name
     * @see    http://www.php.net/manual/en/locale.getdisplaylanguage.php
     * @throws RuntimeException   When the intl extension is not loaded
     */
    public static function getDisplayLanguage($locale, $inLocale = null)
    {
        self::isIntlExtensionAvailable();
        return \Locale::getDisplayLanguage($locale, $inLocale);
    }

    /**
     * Returns the localized display name for the locale
     *
     * @param  string  $locale    The locale code to return the display locale name from
     * @param  string  $inLocale  Optional format locale code to use to display the locale name
     * @return string             The localized locale display name
     * @see    http://www.php.net/manual/en/locale.getdisplayname.php
     * @throws RuntimeException   When the intl extension is not loaded
     */
    public static function getDisplayName($locale, $inLocale = null)
    {
        self::isIntlExtensionAvailable();
        return \Locale::getDisplayName($locale, $inLocale);
    }

    /**
     * Returns the localized display name for the locale region
     *
     * @param  string  $locale    The locale code to return the display region from
     * @param  string  $inLocale  Optional format locale code to use to display the region name
     * @return string             The localized region display name
     * @see    http://www.php.net/manual/en/locale.getdisplayregion.php
     * @throws RuntimeException   When the intl extension is not loaded
     */
    public static function getDisplayRegion($locale, $inLocale = null)
    {
        self::isIntlExtensionAvailable();
        return \Locale::getDisplayRegion($locale, $inLocale);
    }

    /**
     * Returns the localized display name for the locale script
     *
     * @param  string  $locale    The locale code to return the display scrit from
     * @param  string  $inLocale  Optional format locale code to use to display the script name
     * @return string             The localized script display name
     * @see    http://www.php.net/manual/en/locale.getdisplayscript.php
     * @throws RuntimeException   When the intl extension is not loaded
     */
    public static function getDisplayScript($locale, $inLocale = null)
    {
        self::isIntlExtensionAvailable();
        return \Locale::getDisplayScript($locale, $inLocale);
    }

    /**
     * Returns the localized display name for the locale variant
     *
     * @param  string  $locale    The locale code to return the display variant from
     * @param  string  $inLocale  Optional format locale code to use to display the variant name
     * @return string             The localized variant display name
     * @see    http://www.php.net/manual/en/locale.getdisplayvariant.php
     * @throws RuntimeException   When the intl extension is not loaded
     */
    public static function getDisplayVariant($locale, $inLocale = null)
    {
        self::isIntlExtensionAvailable();
        return \Locale::getDisplayVariant($locale, $inLocale);
    }

    /**
     * Returns the keywords for the locale
     *
     * @param  string  $locale    The locale code to extract the keywords from
     * @return array              Associative array with the extracted variants
     * @see    http://www.php.net/manual/en/locale.getkeywords.php
     * @throws RuntimeException   When the intl extension is not loaded
     */
    public static function getKeywords($locale)
    {
        self::isIntlExtensionAvailable();
        return \Locale::getKeywords($locale);
    }

    /**
     * Returns the primary language for the locale
     *
     * @param  string  $locale    The locale code to extract the language code from
     * @return string|null        The extracted language code or null in case of error
     * @see    http://www.php.net/manual/en/locale.getprimarylanguage.php
     * @throws RuntimeException   When the intl extension is not loaded
     */
    public static function getPrimaryLanguage($locale)
    {
        self::isIntlExtensionAvailable();
        return \Locale::getPrimaryLanguage($locale);
    }

    /**
     * Returns the region for the locale
     *
     * @param  string  $locale    The locale code to extract the region code from
     * @return string|null        The extracted region code or null if not present
     * @see    http://www.php.net/manual/en/locale.getregion.php
     * @throws RuntimeException   When the intl extension is not loaded
     */
    public static function getRegion($locale)
    {
        self::isIntlExtensionAvailable();
        return \Locale::getRegion($locale);
    }

    /**
     * Returns the script for the locale
     *
     * @param  string  $locale    The locale code to extract the script code from
     * @return string|null        The extracted script code or null if not present
     * @see    http://www.php.net/manual/en/locale.getscript.php
     * @throws RuntimeException   When the intl extension is not loaded
     */
    public static function getScript($locale)
    {
        self::isIntlExtensionAvailable();
        return \Locale::getScript($locale);
    }

    /**
     * Returns the closest language tag for the locale
     *
     * @param  array   $langtag       A list of the language tags to compare to locale
     * @param  string  $locale        The locale to use as the language range when matching
     * @param  bool    $canonicalize  If true, the arguments will be converted to canonical form before matching
     * @param  string  $default       The locale to use if no match is found
     * @see    http://www.php.net/manual/en/locale.lookup.php
     * @throws RuntimeException       When the intl extension is not loaded
     */
    public static function lookup(array $langtag, $locale, $canonicalize = false, $default = null)
    {
        self::isIntlExtensionAvailable();
        return \Locale::lookup($langtag, $locale, $canonicalize, $default);
    }

    /**
     * Returns an associative array of locale identifier subtags
     *
     * @param  string  $locale    The locale code to extract the subtag array from
     * @return array              Associative arrat with the extracted subtags
     * @see    http://www.php.net/manual/en/locale.parselocale.php
     * @throws RuntimeException   When the intl extension is not loaded
     */
    public static function parseLocale($locale)
    {
        self::isIntlExtensionAvailable();
        return \Locale::parseLocale($locale);
    }

    /**
     * Sets the default runtime locale
     *
     * @param  string  $locale    The locale code
     * @see    http://www.php.net/manual/en/locale.parselocale.php
     * @throws RuntimeException   When the intl extension is not loaded
     */
    public static function setDefault($locale)
    {
        self::isIntlExtensionAvailable();
        return \Locale::setDefault($locale);
    }

    /**
     * Check if the intl extension is loaded.
     *
     * @throws RuntimeException  If the intl extension is not loaded
     */
    private static function isIntlExtensionAvailable()
    {
        if (!extension_loaded('intl')) {
            throw new \RuntimeException('The intl extension is not available.');
        }

        return true;
    }
}
