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
     * Returns all available languages.
     *
     * Languages are returned as lowercase ISO 639-1 two-letter language codes.
     * For languages that don't have a two-letter code, the ISO 639-2
     * three-letter code is used instead.
     *
     * A full table of ISO 639 language codes can be found here:
     * http://www-01.sil.org/iso639-3/codes.asp
     *
     * @return string[] an array of canonical ISO 639 language codes
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
     * @throws MissingResourceException if the language code does not exists
     */
    public static function getName(string $language, string $displayLocale = null): string
    {
        return self::readEntry(['Names', $language], $displayLocale);
    }

    /**
     * @return string[]
     */
    public static function getNames(string $displayLocale = null): array
    {
        return self::asort(self::readEntry(['Names'], $displayLocale), $displayLocale);
    }

    /**
     * Returns the ISO 639-2 three-letter code of a language.
     *
     * @throws MissingResourceException if the language has no corresponding three-letter code
     */
    public static function getAlpha3Code(string $language): string
    {
        return self::readEntry(['Alpha2ToAlpha3', $language], 'meta');
    }

    protected static function getPath(): string
    {
        return Intl::getDataDirectory().'/'.Intl::LANGUAGE_DIR;
    }
}
