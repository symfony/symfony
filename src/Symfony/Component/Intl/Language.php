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

use Symfony\Component\Icu\LanguageDataProvider;
use Symfony\Component\Intl\Exception\InvalidArgumentException;

/**
 * Provides access to language-related data.
 *
 * @since  2.5
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @api
 */
class Language
{
    /**
     * @var LanguageDataProvider
     */
    private static $dataProvider;

    /**
     * @var string[]|null
     */
    private static $languages;

    /**
     * @var integer[]|null
     */
    private static $lookupTable;

    /**
     * @var string[]|null
     */
    private static $aliases;

    /**
     * Returns all available languages.
     *
     * Languages are returned as lowercase ISO 639-1 two-letter language codes.
     * For languages that don't have a two-letter code, the ISO 639-2
     * three-letter code is used instead.
     *
     * A full table of ISO 639 language codes can be found here:
     * http://www-01.sil.org/iso639-3/codes.asp
     *
     * @return string[] An array of canonical ISO 639 language codes
     *
     * @api
     */
    public static function getLanguages()
    {
        if (null === self::$languages) {
            self::$languages = self::getDataProvider()->getLanguages();
        }

        return self::$languages;
    }

    /**
     * Returns whether the given ISO 639 language code exists.
     *
     * This method does not canonicalize the given language code. Specifically,
     * it will return false if the language code is not correctly cased or uses
     * hyphens ("-") as separators between the subtags instead of underscores
     * ("_"). For example, this method returns false for "en-GB", but true
     * for "en_GB".
     *
     * This method also returns false if an ISO 639-2 three-letter code is
     * provided where an equivalent ISO 639-1 two-letter code exists.
     *
     * If you want to support the above cases, you should manually canonicalize
     * the language code prior to calling this method.
     *
     * @param string $language A canonical ISO 639 language code (e.g. "en")
     *
     * @return Boolean Whether the language code exists
     *
     * @see canonicalize
     *
     * @api
     */
    public static function exists($language)
    {
        if (null === self::$lookupTable) {
            self::$lookupTable = array_flip(static::getLanguages());
        }

        return isset(self::$lookupTable[$language]);
    }

    /**
     * Canonicalizes the given ISO 639 language code.
     *
     * Canonicalization performs the following steps:
     *
     *   1. Hyphens ("-") are replaced by underscores ("_")
     *   2. The first subtag is interpreted as language code. The language
     *      code is lowercased. If a corresponding ISO 639-1 two-letter code
     *      exists for a given ISO 639-2 three-letter code, the two-letter code
     *      is used instead. For example, "DEU" is converted to "de".
     *   3. The second subtag is interpreted as region code. The region code
     *      is uppercased. If the region code is an alias, it is replaced by
     *      the aliased region code. For example "aut" is converted to "AT".
     *
     * Canonicalization does not check whether a given language or region
     * actually exists. In case of doubt, you should pass the canonicalized
     * language to {@link exists()}.
     *
     * @param string $language A language code (e.g. "en")
     *
     * @return string The canonicalized ISO 639 language codde
     *
     * @see exists
     *
     * @api
     */
    public static function canonicalize($language)
    {
        if (static::exists($language)) {
            return $language;
        }

        if (null === self::$aliases) {
            self::$aliases = self::getDataProvider()->getAliases();
        }

        $parts = preg_split('/[-_]/', $language);

        // The language code is always in lower case
        $parts[0] = strtolower($parts[0]);

        if (isset(self::$aliases[$parts[0]])) {
            $parts[0] = self::$aliases[$parts[0]];
        }

        if (isset($parts[1])) {
            // TODO: Uncomment once Region::canonicalize() is implemented
            //$parts[1] = Region::canonicalize($parts[1]);
        }

        // TODO: change index to 2 once Region::canonicalize() is implemented
        for ($i = 1; $i < count($parts); ++$i) {
            $parts[$i] = strtoupper($parts[$i]);
        }

        return implode('_', $parts);
    }

    /**
     * Returns the name for a language in the given locale.
     *
     * For example, the name of British English ("en_GB") in the locale "ru_RU"
     * is "британский английский". If the resource data for the given locale
     * contains no entry for the given language, then the ISO 639 language code
     * is returned.
     *
     * If <code>null</code> is passed as locale, the result of
     * {@link \Locale::getDefault()} is used instead.
     *
     * @param string $language      A canonical ISO 639 language code
     *                              (e.g. "en")
     * @param string $displayLocale The ICU locale code to return the name in
     *
     * @return string The name of the language
     *
     * @throws InvalidArgumentException If the language or the locale is invalid
     *
     * @api
     */
    public static function getName($language, $displayLocale = null)
    {
        if (!static::exists($language)) {
            throw new InvalidArgumentException('The language "' . $language . '" does not exist.');
        }

        if (null !== $displayLocale && !Locale::exists($displayLocale)) {
            throw new InvalidArgumentException('The locale "' . $displayLocale . '" does not exist.');
        }

        if (null === $displayLocale) {
            $displayLocale = \Locale::getDefault();
        }

        return self::getDataProvider()->getName($language, $displayLocale);
    }

    /**
     * Returns the names of all known languages in the specified locale.
     *
     * If the resource data for the given locale contains no entry for a
     * language, then the canonical ISO 639 language code is used instead.
     *
     * If <code>null</code> is passed as locale, the result of
     * {@link \Locale::getDefault()} is used instead.
     *
     * @param string $displayLocale The ICU locale code to return the names in
     *
     * @return string[] An array of language names indexed by their
     *                  canonical ISO 639 language codes
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
     * Returns the ISO 639-2 three-letter code of a language.
     *
     * @param string $language A canonical ISO 639 language code (e.g. "en")
     *
     * @return string The ISO 639-2 three-letter code of the language
     *
     * @throws Exception\InvalidArgumentException If the language is invalid
     * @throws Exception\MissingResourceException If the language has no
     *                                            corresponding three-letter code
     *
     * @api
     */
    public static function getAlpha3Code($language)
    {
        if (!static::exists($language)) {
            throw new InvalidArgumentException('The language "' . $language . '" does not exist.');
        }

        return self::getDataProvider()->getAlpha3Code($language);
    }

    /**
     * @return LanguageDataProvider
     */
    private static function getDataProvider()
    {
        if (null === self::$dataProvider) {
            self::$dataProvider = new LanguageDataProvider(
                LanguageDataProvider::getResourceDirectory(),
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
