<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Locale\Stub;

use Symfony\Component\Locale\Locale;
use Symfony\Component\Locale\Exception\NotImplementedException;
use Symfony\Component\Locale\Exception\MethodNotImplementedException;

/**
 * Provides a stub Locale for the 'en' locale.
 *
 * @author Eriksen Costa <eriksen.costa@infranology.com.br>
 */
class StubLocale
{
    const DEFAULT_LOCALE = null;

    /** Locale method constants */
    const ACTUAL_LOCALE = 0;
    const VALID_LOCALE  = 1;

    /** Language tags constants */
    const LANG_TAG               = 'language';
    const EXTLANG_TAG            = 'extlang';
    const SCRIPT_TAG             = 'script';
    const REGION_TAG             = 'region';
    const VARIANT_TAG            = 'variant';
    const GRANDFATHERED_LANG_TAG = 'grandfathered';
    const PRIVATE_TAG            = 'private';

    /**
     * Caches the countries
     *
     * @var array
     */
    protected static $countries = array();

    /**
     * Caches the languages
     *
     * @var array
     */
    protected static $languages = array();

    /**
     * Caches the locales
     *
     * @var array
     */
    protected static $locales = array();

    /**
     * Caches the currencies
     *
     * @var array
     */
    protected static $currencies = array();

    /**
     * Caches the currencies names
     *
     * @var array
     */
    protected static $currenciesNames = array();

    /**
     * Returns the country names for a locale
     *
     * @param string $locale The locale to use for the country names
     *
     * @return array                     The country names with their codes as keys
     *
     * @throws \InvalidArgumentException  When the locale is different than 'en'
     */
    public static function getDisplayCountries($locale)
    {
        return self::getStubData($locale, 'countries', 'region');
    }

    /**
     * Returns all available country codes
     *
     * @return array  The country codes
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
     * @return array                     The language names with their codes as keys
     *
     * @throws \InvalidArgumentException  When the locale is different than 'en'
     */
    public static function getDisplayLanguages($locale)
    {
        return self::getStubData($locale, 'languages', 'lang');
    }

    /**
     * Returns all available language codes
     *
     * @return array  The language codes
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
     * @return array                     The locale names with their codes as keys
     *
     * @throws \InvalidArgumentException  When the locale is different than 'en'
     */
    public static function getDisplayLocales($locale)
    {
        return self::getStubData($locale, 'locales', 'names');
    }

    /**
     * Returns all available locale codes
     *
     * @return array  The locale codes
     */
    public static function getLocales()
    {
        return array_keys(self::getDisplayLocales(self::getDefault()));
    }

    /**
     * Returns the currencies data
     *
     * @param string $locale
     *
     * @return array  The currencies data
     */
    public static function getCurrenciesData($locale)
    {
        return self::getStubData($locale, 'currencies', 'curr');
    }

    /**
     *  Returns the currencies names for a locale
     *
     * @param string $locale The locale to use for the currencies names
     *
     * @return array                     The currencies names with their codes as keys
     *
     * @throws \InvalidArgumentException  When the locale is different than 'en'
     */
    public static function getDisplayCurrencies($locale)
    {
        $currencies = self::getCurrenciesData($locale);

        if (!empty(self::$currenciesNames)) {
            return self::$currenciesNames;
        }

        foreach ($currencies as $code => $data) {
            self::$currenciesNames[$code] = $data['name'];
        }

        return self::$currenciesNames;
    }

    /**
     * Returns all available currencies codes
     *
     * @return array  The currencies codes
     */
    public static function getCurrencies()
    {
        return array_keys(self::getCurrenciesData(self::getDefault()));
    }

    /**
     * Returns the best available locale based on HTTP "Accept-Language" header according to RFC 2616
     *
     * @param string $header The string containing the "Accept-Language" header value
     *
     * @return string             The corresponding locale code
     *
     * @see    http://www.php.net/manual/en/locale.acceptfromhttp.php
     *
     * @throws MethodNotImplementedException
     */
    public static function acceptFromHttp($header)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    /**
     * Returns a correctly ordered and delimited locale code
     *
     * @param array $subtags A keyed array where the keys identify the particular locale code subtag
     *
     * @return string             The corresponding locale code
     *
     * @see    http://www.php.net/manual/en/locale.composelocale.php
     *
     * @throws MethodNotImplementedException
     */
    public static function composeLocale(array $subtags)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    /**
     * Checks if a language tag filter matches with locale
     *
     * @param string  $langtag      The language tag to check
     * @param string  $locale       The language range to check against
     * @param Boolean $canonicalize
     *
     * @return string             The corresponding locale code
     *
     * @see    http://www.php.net/manual/en/locale.filtermatches.php
     *
     * @throws MethodNotImplementedException
     */
    public static function filterMatches($langtag, $locale, $canonicalize = false)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    /**
     * Returns the variants for the input locale
     *
     * @param string $locale The locale to extract the variants from
     *
     * @return array              The locale variants
     *
     * @see    http://www.php.net/manual/en/locale.getallvariants.php
     *
     * @throws MethodNotImplementedException
     */
    public static function getAllVariants($locale)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    /**
     * Returns the default locale
     *
     * @return string             The default locale code. Always returns 'en'
     *
     * @see    http://www.php.net/manual/en/locale.getdefault.php
     *
     * @throws MethodNotImplementedException
     */
    public static function getDefault()
    {
        return 'en';
    }

    /**
     * Returns the localized display name for the locale language
     *
     * @param string $locale   The locale code to return the display language from
     * @param string $inLocale Optional format locale code to use to display the language name
     *
     * @return string             The localized language display name
     *
     * @see    http://www.php.net/manual/en/locale.getdisplaylanguage.php
     *
     * @throws MethodNotImplementedException
     */
    public static function getDisplayLanguage($locale, $inLocale = null)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    /**
     * Returns the localized display name for the locale
     *
     * @param string $locale   The locale code to return the display locale name from
     * @param string $inLocale Optional format locale code to use to display the locale name
     *
     * @return string             The localized locale display name
     *
     * @see    http://www.php.net/manual/en/locale.getdisplayname.php
     *
     * @throws MethodNotImplementedException
     */
    public static function getDisplayName($locale, $inLocale = null)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    /**
     * Returns the localized display name for the locale region
     *
     * @param string $locale   The locale code to return the display region from
     * @param string $inLocale Optional format locale code to use to display the region name
     *
     * @return string             The localized region display name
     *
     * @see    http://www.php.net/manual/en/locale.getdisplayregion.php
     *
     * @throws MethodNotImplementedException
     */
    public static function getDisplayRegion($locale, $inLocale = null)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    /**
     * Returns the localized display name for the locale script
     *
     * @param string $locale   The locale code to return the display script from
     * @param string $inLocale Optional format locale code to use to display the script name
     *
     * @return string             The localized script display name
     *
     * @see    http://www.php.net/manual/en/locale.getdisplayscript.php
     *
     * @throws MethodNotImplementedException
     */
    public static function getDisplayScript($locale, $inLocale = null)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    /**
     * Returns the localized display name for the locale variant
     *
     * @param string $locale   The locale code to return the display variant from
     * @param string $inLocale Optional format locale code to use to display the variant name
     *
     * @return string             The localized variant display name
     *
     * @see    http://www.php.net/manual/en/locale.getdisplayvariant.php
     *
     * @throws MethodNotImplementedException
     */
    public static function getDisplayVariant($locale, $inLocale = null)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    /**
     * Returns the keywords for the locale
     *
     * @param string $locale The locale code to extract the keywords from
     *
     * @return array              Associative array with the extracted variants
     *
     * @see    http://www.php.net/manual/en/locale.getkeywords.php
     *
     * @throws MethodNotImplementedException
     */
    public static function getKeywords($locale)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    /**
     * Returns the primary language for the locale
     *
     * @param string $locale The locale code to extract the language code from
     *
     * @return string|null        The extracted language code or null in case of error
     *
     * @see    http://www.php.net/manual/en/locale.getprimarylanguage.php
     *
     * @throws MethodNotImplementedException
     */
    public static function getPrimaryLanguage($locale)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    /**
     * Returns the region for the locale
     *
     * @param string $locale The locale code to extract the region code from
     *
     * @return string|null        The extracted region code or null if not present
     *
     * @see    http://www.php.net/manual/en/locale.getregion.php
     *
     * @throws MethodNotImplementedException
     */
    public static function getRegion($locale)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    /**
     * Returns the script for the locale
     *
     * @param string $locale The locale code to extract the script code from
     *
     * @return string|null        The extracted script code or null if not present
     *
     * @see    http://www.php.net/manual/en/locale.getscript.php
     *
     * @throws MethodNotImplementedException
     */
    public static function getScript($locale)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    /**
     * Returns the closest language tag for the locale
     *
     * @param array   $langtag      A list of the language tags to compare to locale
     * @param string  $locale       The locale to use as the language range when matching
     * @param Boolean $canonicalize If true, the arguments will be converted to canonical form before matching
     * @param string  $default      The locale to use if no match is found
     *
     * @see    http://www.php.net/manual/en/locale.lookup.php
     *
     * @throws \RuntimeException       When the intl extension is not loaded
     */
    public static function lookup(array $langtag, $locale, $canonicalize = false, $default = null)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    /**
     * Returns an associative array of locale identifier subtags
     *
     * @param string $locale The locale code to extract the subtag array from
     *
     * @return array              Associative array with the extracted subtags
     *
     * @see    http://www.php.net/manual/en/locale.parselocale.php
     *
     * @throws MethodNotImplementedException
     */
    public static function parseLocale($locale)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    /**
     * Sets the default runtime locale
     *
     * @param string $locale The locale code
     *
     * @return Boolean            true on success or false on failure
     *
     * @see    http://www.php.net/manual/en/locale.parselocale.php
     *
     * @throws MethodNotImplementedException
     */
    public static function setDefault($locale)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    public static function getDataDirectory()
    {
        static $dataDirectory;

        if (null === $dataDirectory) {
            $dataDirectory = 'current';

            if (getenv('ICU_DATA_VERSION')) {
                $dataDirectory = getenv('ICU_DATA_VERSION');
            } elseif (file_exists(__DIR__.'/../Resources/data/data-version.php')) {
                $dataDirectory = include __DIR__.'/../Resources/data/data-version.php';
            }
        }

        return __DIR__.'/../Resources/data/'.$dataDirectory;
    }

    /**
     * Returns the stub ICU data
     *
     * @param string $locale        The locale code
     * @param string $cacheVariable The name of a static attribute to cache the data to
     * @param string $stubDataDir   The stub data directory name
     *
     * @return array
     *
     * @throws \InvalidArgumentException  When the locale is different than 'en'
     */
    private static function getStubData($locale, $cacheVariable, $stubDataDir)
    {
        $dataDirectory = Locale::getIcuDataDirectory();

        if ('en' !== $locale) {
            throw new \InvalidArgumentException(sprintf('Only the \'en\' locale is supported. %s', NotImplementedException::INTL_INSTALL_MESSAGE));
        }

        if (empty(self::${$cacheVariable})) {
            self::${$cacheVariable} = include $dataDirectory.'/stub/'.$stubDataDir.'/en.php';
        }

        return self::${$cacheVariable};
    }
}
