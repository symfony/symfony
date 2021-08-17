<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Locale;

use Symfony\Component\Intl\Exception\MethodNotImplementedException;

/**
 * Replacement for PHP's native {@link \Locale} class.
 *
 * The only methods supported in this class are `getDefault` and `canonicalize`.
 * All other methods will throw an exception when used.
 *
 * @author Eriksen Costa <eriksen.costa@infranology.com.br>
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @internal
 *
 * @deprecated since Symfony 5.3, use symfony/polyfill-intl-icu ^1.21 instead
 */
abstract class Locale
{
    public const DEFAULT_LOCALE = null;

    /* Locale method constants */
    public const ACTUAL_LOCALE = 0;
    public const VALID_LOCALE = 1;

    /* Language tags constants */
    public const LANG_TAG = 'language';
    public const EXTLANG_TAG = 'extlang';
    public const SCRIPT_TAG = 'script';
    public const REGION_TAG = 'region';
    public const VARIANT_TAG = 'variant';
    public const GRANDFATHERED_LANG_TAG = 'grandfathered';
    public const PRIVATE_TAG = 'private';

    /**
     * Not supported. Returns the best available locale based on HTTP "Accept-Language" header according to RFC 2616.
     *
     * @param string $header The string containing the "Accept-Language" header value
     *
     * @return string
     *
     * @see https://php.net/locale.acceptfromhttp
     *
     * @throws MethodNotImplementedException
     */
    public static function acceptFromHttp(string $header)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    /**
     * Returns a canonicalized locale string.
     *
     * This polyfill doesn't implement the full-spec algorithm. It only
     * canonicalizes locale strings handled by the `LocaleBundle` class.
     *
     * @return string
     */
    public static function canonicalize(string $locale)
    {
        if ('' === $locale || '.' === $locale[0]) {
            return self::getDefault();
        }

        if (!preg_match('/^([a-z]{2})[-_]([a-z]{2})(?:([a-z]{2})(?:[-_]([a-z]{2}))?)?(?:\..*)?$/i', $locale, $m)) {
            return $locale;
        }

        if (!empty($m[4])) {
            return strtolower($m[1]).'_'.ucfirst(strtolower($m[2].$m[3])).'_'.strtoupper($m[4]);
        }

        if (!empty($m[3])) {
            return strtolower($m[1]).'_'.ucfirst(strtolower($m[2].$m[3]));
        }

        return strtolower($m[1]).'_'.strtoupper($m[2]);
    }

    /**
     * Not supported. Returns a correctly ordered and delimited locale code.
     *
     * @param array $subtags A keyed array where the keys identify the particular locale code subtag
     *
     * @return string
     *
     * @see https://php.net/locale.composelocale
     *
     * @throws MethodNotImplementedException
     */
    public static function composeLocale(array $subtags)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    /**
     * Not supported. Checks if a language tag filter matches with locale.
     *
     * @param string $langtag The language tag to check
     * @param string $locale  The language range to check against
     *
     * @return string
     *
     * @see https://php.net/locale.filtermatches
     *
     * @throws MethodNotImplementedException
     */
    public static function filterMatches(string $langtag, string $locale, bool $canonicalize = false)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    /**
     * Not supported. Returns the variants for the input locale.
     *
     * @param string $locale The locale to extract the variants from
     *
     * @return array
     *
     * @see https://php.net/locale.getallvariants
     *
     * @throws MethodNotImplementedException
     */
    public static function getAllVariants(string $locale)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    /**
     * Returns the default locale, which is always "en".
     *
     * @return string
     *
     * @see https://php.net/locale.getdefault
     */
    public static function getDefault()
    {
        return 'en';
    }

    /**
     * Not supported. Returns the localized display name for the locale language.
     *
     * @param string $locale   The locale code to return the display language from
     * @param string $inLocale Optional format locale code to use to display the language name
     *
     * @return string
     *
     * @see https://php.net/locale.getdisplaylanguage
     *
     * @throws MethodNotImplementedException
     */
    public static function getDisplayLanguage(string $locale, string $inLocale = null)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    /**
     * Not supported. Returns the localized display name for the locale.
     *
     * @param string $locale   The locale code to return the display locale name from
     * @param string $inLocale Optional format locale code to use to display the locale name
     *
     * @return string
     *
     * @see https://php.net/locale.getdisplayname
     *
     * @throws MethodNotImplementedException
     */
    public static function getDisplayName(string $locale, string $inLocale = null)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    /**
     * Not supported. Returns the localized display name for the locale region.
     *
     * @param string $locale   The locale code to return the display region from
     * @param string $inLocale Optional format locale code to use to display the region name
     *
     * @return string
     *
     * @see https://php.net/locale.getdisplayregion
     *
     * @throws MethodNotImplementedException
     */
    public static function getDisplayRegion(string $locale, string $inLocale = null)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    /**
     * Not supported. Returns the localized display name for the locale script.
     *
     * @param string $locale   The locale code to return the display script from
     * @param string $inLocale Optional format locale code to use to display the script name
     *
     * @return string
     *
     * @see https://php.net/locale.getdisplayscript
     *
     * @throws MethodNotImplementedException
     */
    public static function getDisplayScript(string $locale, string $inLocale = null)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    /**
     * Not supported. Returns the localized display name for the locale variant.
     *
     * @param string $locale   The locale code to return the display variant from
     * @param string $inLocale Optional format locale code to use to display the variant name
     *
     * @return string
     *
     * @see https://php.net/locale.getdisplayvariant
     *
     * @throws MethodNotImplementedException
     */
    public static function getDisplayVariant(string $locale, string $inLocale = null)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    /**
     * Not supported. Returns the keywords for the locale.
     *
     * @param string $locale The locale code to extract the keywords from
     *
     * @return array
     *
     * @see https://php.net/locale.getkeywords
     *
     * @throws MethodNotImplementedException
     */
    public static function getKeywords(string $locale)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    /**
     * Not supported. Returns the primary language for the locale.
     *
     * @param string $locale The locale code to extract the language code from
     *
     * @return string|null
     *
     * @see https://php.net/locale.getprimarylanguage
     *
     * @throws MethodNotImplementedException
     */
    public static function getPrimaryLanguage(string $locale)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    /**
     * Not supported. Returns the region for the locale.
     *
     * @param string $locale The locale code to extract the region code from
     *
     * @return string|null
     *
     * @see https://php.net/locale.getregion
     *
     * @throws MethodNotImplementedException
     */
    public static function getRegion(string $locale)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    /**
     * Not supported. Returns the script for the locale.
     *
     * @param string $locale The locale code to extract the script code from
     *
     * @return string|null
     *
     * @see https://php.net/locale.getscript
     *
     * @throws MethodNotImplementedException
     */
    public static function getScript(string $locale)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    /**
     * Not supported. Returns the closest language tag for the locale.
     *
     * @param array  $langtag      A list of the language tags to compare to locale
     * @param string $locale       The locale to use as the language range when matching
     * @param bool   $canonicalize If true, the arguments will be converted to canonical form before matching
     * @param string $default      The locale to use if no match is found
     *
     * @see https://php.net/locale.lookup
     *
     * @throws MethodNotImplementedException
     */
    public static function lookup(array $langtag, string $locale, bool $canonicalize = false, string $default = null)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    /**
     * Not supported. Returns an associative array of locale identifier subtags.
     *
     * @param string $locale The locale code to extract the subtag array from
     *
     * @return array
     *
     * @see https://php.net/locale.parselocale
     *
     * @throws MethodNotImplementedException
     */
    public static function parseLocale(string $locale)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    /**
     * Not supported. Sets the default runtime locale.
     *
     * @return bool
     *
     * @see https://php.net/locale.setdefault
     *
     * @throws MethodNotImplementedException
     */
    public static function setDefault(string $locale)
    {
        if ('en' !== $locale) {
            throw new MethodNotImplementedException(__METHOD__);
        }

        return true;
    }
}
