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
 * Gives access to timezone-related ICU data.
 *
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
final class Timezones extends ResourceBundle
{
    /**
     * @return string[]
     */
    public static function getIds(): array
    {
        return self::readEntry(['Zones'], 'meta');
    }

    public static function exists(string $timezone): bool
    {
        try {
            self::readEntry(['Names', $timezone]);

            return true;
        } catch (MissingResourceException $e) {
            return false;
        }
    }

    public static function getName(string $timezone, string $displayLocale = null): string
    {
        return self::readEntry(['Names', $timezone], $displayLocale);
    }

    /**
     * @return string[]
     */
    public static function getNames(string $displayLocale = null): array
    {
        return self::asort(self::readEntry(['Names'], $displayLocale), $displayLocale);
    }

    protected static function getPath(): string
    {
        return Intl::getDataDirectory().'/'.Intl::TIMEZONE_DIR;
    }
}
