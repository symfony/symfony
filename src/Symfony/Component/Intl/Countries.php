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
 * Gives access to region-related ICU data.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
final class Countries extends ResourceBundle
{
    private static bool $withUserAssigned;

    /**
     * Returns all available countries.
     *
     * Countries are returned as uppercase ISO 3166 two-letter country codes.
     *
     * A full table of ISO 3166 country codes can be found here:
     * https://en.wikipedia.org/wiki/List_of_ISO_3166_country_codes
     *
     * This list only contains "officially assigned ISO 3166-1 alpha-2" country codes.
     *
     * @return string[]
     */
    public static function getCountryCodes(): array
    {
        if (!self::withUserAssigned()) {
            return self::readEntry(['Regions'], 'meta');
        }

        return array_merge(self::readEntry(['Regions'], 'meta'), self::readEntry(['UserAssignedRegions'], 'meta'));
    }

    /**
     * Returns all available countries (3 letters).
     *
     * Countries are returned as uppercase ISO 3166 three-letter country codes.
     *
     * This list only contains "officially assigned ISO 3166-1 alpha-3" country codes.
     *
     * @return string[]
     */
    public static function getAlpha3Codes(): array
    {
        if (!self::withUserAssigned()) {
            return self::readEntry(['Alpha2ToAlpha3'], 'meta');
        }

        return array_merge(self::readEntry(['Alpha2ToAlpha3'], 'meta'), self::readEntry(['UserAssignedAlpha2ToAlpha3'], 'meta'));
    }

    /**
     * Returns all available numeric country codes (3 digits).
     *
     * Countries are returned as ISO 3166 numeric three-digit country codes.
     *
     * This list only contains "officially assigned ISO 3166-1 numeric" country codes.
     *
     * Returns an array with Alpha2 country codes as keys, and numeric codes as values.
     *
     * @return array<string, string>
     */
    public static function getNumericCodes(): array
    {
        if (!self::withUserAssigned()) {
            return self::readEntry(['Alpha2ToNumeric'], 'meta');
        }

        return array_merge(self::readEntry(['Alpha2ToNumeric'], 'meta'), self::readEntry(['UserAssignedAlpha2ToNumeric'], 'meta'));
    }

    public static function getAlpha3Code(string $alpha2Code): string
    {
        if (self::withUserAssigned()) {
            try {
                return self::readEntry(['UserAssignedAlpha2ToAlpha3', $alpha2Code], 'meta');
            } catch (MissingResourceException) {
            }
        }

        return self::readEntry(['Alpha2ToAlpha3', $alpha2Code], 'meta');
    }

    public static function getAlpha2Code(string $alpha3Code): string
    {
        if (self::withUserAssigned()) {
            try {
                return self::readEntry(['UserAssignedAlpha3ToAlpha2', $alpha3Code], 'meta');
            } catch (MissingResourceException) {
            }
        }

        return self::readEntry(['Alpha3ToAlpha2', $alpha3Code], 'meta');
    }

    public static function getNumericCode(string $alpha2Code): string
    {
        if (self::withUserAssigned()) {
            try {
                return self::readEntry(['UserAssignedAlpha2ToNumeric', $alpha2Code], 'meta');
            } catch (MissingResourceException) {
            }
        }

        return self::readEntry(['Alpha2ToNumeric', $alpha2Code], 'meta');
    }

    public static function getAlpha2FromNumeric(string $numericCode): string
    {
        if (self::withUserAssigned()) {
            try {
                return self::readEntry(['UserAssignedNumericToAlpha2', '_'.$numericCode], 'meta');
            } catch (MissingResourceException) {
            }
        }

        // Use an underscore prefix to force numeric strings with leading zeros to remain as strings
        return self::readEntry(['NumericToAlpha2', '_'.$numericCode], 'meta');
    }

    public static function exists(string $alpha2Code): bool
    {
        try {
            self::getAlpha3Code($alpha2Code);

            return true;
        } catch (MissingResourceException) {
            return false;
        }
    }

    public static function alpha3CodeExists(string $alpha3Code): bool
    {
        try {
            self::getAlpha2Code($alpha3Code);

            return true;
        } catch (MissingResourceException) {
            return false;
        }
    }

    public static function numericCodeExists(string $numericCode): bool
    {
        try {
            self::getAlpha2FromNumeric($numericCode);

            return true;
        } catch (MissingResourceException $e) {
            return false;
        }
    }

    /**
     * Gets the country name from its alpha2 code.
     *
     * @throws MissingResourceException if the country code does not exist
     */
    public static function getName(string $country, ?string $displayLocale = null): string
    {
        if (self::withUserAssigned()) {
            try {
                return self::readEntry(['UserAssignedNames', $country], $displayLocale);
            } catch (MissingResourceException) {
            }
        }

        return self::readEntry(['Names', $country], $displayLocale);
    }

    /**
     * Gets the country name from its alpha3 code.
     *
     * @throws MissingResourceException if the country code does not exist
     */
    public static function getAlpha3Name(string $alpha3Code, ?string $displayLocale = null): string
    {
        return self::getName(self::getAlpha2Code($alpha3Code), $displayLocale);
    }

    /**
     * Gets the list of country names indexed with alpha2 codes as keys.
     *
     * @return array<string, string>
     */
    public static function getNames(?string $displayLocale = null): array
    {
        if (!self::withUserAssigned()) {
            return self::asort(self::readEntry(['Names'], $displayLocale), $displayLocale);
        }

        return self::asort(array_merge(self::readEntry(['Names'], $displayLocale), self::readEntry(['UserAssignedNames'], $displayLocale)), $displayLocale);
    }

    /**
     * Gets the list of country names indexed with alpha3 codes as keys.
     *
     * Same as method getNames, but with alpha3 codes instead of alpha2 codes as keys.
     *
     * @return array<string, string>
     */
    public static function getAlpha3Names(?string $displayLocale = null): array
    {
        $alpha2Names = self::getNames($displayLocale);
        $alpha3Names = [];
        foreach ($alpha2Names as $alpha2Code => $name) {
            $alpha3Names[self::getAlpha3Code($alpha2Code)] = $name;
        }

        return $alpha3Names;
    }

    /**
     * Sets the internal `withUserAssigned` flag, overriding the default `SYMFONY_INTL_WITH_USER_ASSIGNED` env var.
     *
     * The ISO 3166/MA has received information that the CE Commission has allocated the alpha-2 user-assigned code "XK"
     * to represent Kosovo in the interim of being recognized by the UN as a member state.
     *
     * Set `$withUserAssigned` to true to have `XK`, `XKK` and `983` available in the other functions of this class.
     */
    public static function withUserAssigned(?bool $withUserAssigned = null): bool
    {
        if (null === $withUserAssigned) {
            return self::$withUserAssigned ??= filter_var($_ENV['SYMFONY_INTL_WITH_USER_ASSIGNED'] ?? $_SERVER['SYMFONY_INTL_WITH_USER_ASSIGNED'] ?? getenv('SYMFONY_INTL_WITH_USER_ASSIGNED'), \FILTER_VALIDATE_BOOLEAN);
        }

        return self::$withUserAssigned = $withUserAssigned;
    }

    protected static function getPath(): string
    {
        return Intl::getDataDirectory().'/'.Intl::REGION_DIR;
    }
}
