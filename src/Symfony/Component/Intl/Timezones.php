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
use Symfony\Component\Intl\Exception\RuntimeException;

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

    public static function getRawOffset(string $timezone, int $timestamp = null): int
    {
        if (null === $timestamp) {
            $timestamp = time();
        }

        $transitions = (new \DateTimeZone($timezone))->getTransitions($timestamp, $timestamp);

        if (!isset($transitions[0]['offset'])) {
            throw new RuntimeException('No timezone transitions available.');
        }

        return $transitions[0]['offset'];
    }

    public static function getGmtOffset(string $timezone, int $timestamp = null, string $displayLocale = null): string
    {
        $offset = self::getRawOffset($timezone, $timestamp);
        $abs = abs($offset);

        return sprintf(self::readEntry(['Meta', 'GmtFormat'], $displayLocale), sprintf(self::readEntry(['Meta', 'HourFormat', 0 <= $offset ? 0 : 1], $displayLocale), $abs / 3600, $abs / 60 % 60));
    }

    public static function getCountryCode(string $timezone): string
    {
        return self::readEntry(['ZoneToCountry', $timezone], 'meta');
    }

    public static function forCountryCode(string $country): array
    {
        return self::readEntry(['CountryToZone', $country], 'meta');
    }

    protected static function getPath(): string
    {
        return Intl::getDataDirectory().'/'.Intl::TIMEZONE_DIR;
    }
}
