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
            try {
                new \DateTimeZone($timezone);

                return true;
            } catch (\Exception $e) {
                return false;
            }
        }
    }

    /**
     * @throws MissingResourceException if the timezone identifier does not exist or is an alias
     */
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

    /**
     * @throws \Exception       if the timezone identifier does not exist
     * @throws RuntimeException if there's no timezone DST transition information available
     */
    public static function getRawOffset(string $timezone, int $timestamp = null): int
    {
        $dateTimeImmutable = new \DateTimeImmutable(date('Y-m-d H:i:s', $timestamp ?? time()), new \DateTimeZone($timezone));

        return $dateTimeImmutable->getOffset();
    }

    public static function getGmtOffset(string $timezone, int $timestamp = null, string $displayLocale = null): string
    {
        $offset = self::getRawOffset($timezone, $timestamp);
        $abs = abs($offset);

        return sprintf(self::readEntry(['Meta', 'GmtFormat'], $displayLocale), sprintf(self::readEntry(['Meta', 'HourFormat'.(0 <= $offset ? 'Pos' : 'Neg')], $displayLocale), $abs / 3600, $abs / 60 % 60));
    }

    /**
     * @throws MissingResourceException if the timezone identifier has no associated country code
     */
    public static function getCountryCode(string $timezone): string
    {
        return self::readEntry(['ZoneToCountry', $timezone], 'meta');
    }

    /**
     * @throws MissingResourceException if the country code does not exist
     */
    public static function forCountryCode(string $country): array
    {
        try {
            return self::readEntry(['CountryToZone', $country], 'meta');
        } catch (MissingResourceException $e) {
            if (Countries::exists($country)) {
                return [];
            }

            if (Countries::exists(strtoupper($country))) {
                throw new MissingResourceException(sprintf('Country codes must be in uppercase, but "%s" was passed. Try with "%s" country code instead.', $country, strtoupper($country)));
            }

            throw $e;
        }
    }

    protected static function getPath(): string
    {
        return Intl::getDataDirectory().'/'.Intl::TIMEZONE_DIR;
    }
}
