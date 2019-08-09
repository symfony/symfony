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

use Symfony\Component\Intl\Exception\MissingResourceException;

/**
 * Gives access to language-related ICU data.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
final class Languages extends ResourceBundle
{
    /**
     * Returns all available languages as two-letter codes.
     *
     * Languages are returned as lowercase ISO 639-1 two-letter language codes.
     * For languages that don't have a two-letter code, the ISO 639-2
     * three-letter code is used instead.
     *
     * A full table of ISO 639 language codes can be found here:
     * http://www-01.sil.org/iso639-3/codes.asp
     *
     * @return string[] an array of canonical ISO 639-1 language codes
     */
    public static function getLanguageCodes(): array
    {
        return self::readEntry(['Languages'], 'meta');
    }

    public static function exists(string $language): bool
    {
        try {
            self::readEntry(['Names', $language]);

            return true;
        } catch (MissingResourceException $e) {
            return false;
        }
    }

    /**
     * Gets the language name from its alpha2 code.
     *
     * @throws MissingResourceException if the language code does not exist
     */
    public static function getName(string $language, string $displayLocale = null): string
    {
        return self::readEntry(['Names', $language], $displayLocale);
    }

    /**
     * Gets the list of language names indexed with alpha2 codes as keys.
     *
     * @return string[]
     */
    public static function getNames(string $displayLocale = null): array
    {
        return self::asort(self::readEntry(['Names'], $displayLocale), $displayLocale);
    }

    /**
     * Returns the ISO 639-2 three-letter code of a language, given a two-letter code.
     *
     * @throws MissingResourceException if the language has no corresponding three-letter code
     */
    public static function getAlpha3Code(string $language): string
    {
        return self::readEntry(['Alpha2ToAlpha3', $language], 'meta');
    }

    /**
     * Returns the ISO 639-1 two-letter code of a language, given a three letter code.
     *
     * @throws MissingResourceException if the language has no corresponding three-letter code
     */
    public static function getAlpha2Code(string $language): string
    {
        return self::readEntry(['Alpha3ToAlpha2', $language], 'meta');
    }

    /**
     * Returns all available languages as three-letter codes.
     *
     * Languages are returned as lowercase ISO 639-2 three-letter language codes.
     *
     * @return string[] an array of canonical ISO 639-2 language codes
     */
    public static function getAlpha3Codes(): array
    {
        return self::readEntry(['Alpha2ToAlpha3'], 'meta');
    }

    /**
     * @param string $language ISO 639-2 three-letter language code
     */
    public static function alpha3CodeExists(string $language): bool
    {
        try {
            self::getAlpha2Code($language);

            return true;
        } catch (MissingResourceException $e) {
            return false;
        }
    }

    /**
     * Gets the language name from its ISO 639-2 three-letter code.
     *
     * @throws MissingResourceException if the country code does not exists
     */
    public static function getAlpha3Name(string $language, string $displayLocale = null): string
    {
        return self::getName(self::getAlpha2Code($language), $displayLocale);
    }

    /**
     * Gets the list of language names indexed with ISO 639-2 three-letter codes as keys.
     *
     * Same as method getNames, but with ISO 639-2 three-letter codes instead of ISO 639-1 codes as keys.
     *
     * @return string[]
     */
    public static function getAlpha3Names($displayLocale = null): array
    {
        $alpha2Names = self::getNames($displayLocale);
        $alpha3Names = [];
        foreach ($alpha2Names as $alpha2Code => $name) {
            try {
                $alpha3Names[self::getAlpha3Code($alpha2Code)] = $name;
            } catch (MissingResourceException $e) {
            }
        }

        return $alpha3Names;
    }

    protected static function getPath(): string
    {
        return Intl::getDataDirectory().'/'.Intl::LANGUAGE_DIR;
    }
}
