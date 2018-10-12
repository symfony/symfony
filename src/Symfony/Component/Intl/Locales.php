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
 * Gives access to locale-related ICU data.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
final class Locales extends ResourceBundle
{
    /**
     * @return string[]
     */
    public static function getLocales(): array
    {
        return self::readEntry(['Locales'], 'meta');
    }

    /**
     * @return string[]
     */
    public static function getAliases(): array
    {
        return self::readEntry(['Aliases'], 'meta');
    }

    public static function exists(string $locale): bool
    {
        try {
            self::readEntry(['Names', $locale]);

            return true;
        } catch (MissingResourceException $e) {
            return false;
        }
    }

    public static function getName(string $locale, string $displayLocale = null): string
    {
        return self::readEntry(['Names', $locale], $displayLocale);
    }

    /**
     * @return string[]
     */
    public static function getNames($displayLocale = null)
    {
        return self::asort(self::readEntry(['Names'], $displayLocale), $displayLocale);
    }

    protected static function getPath(): string
    {
        return Intl::getDataDirectory().'/'.Intl::LOCALE_DIR;
    }
}
