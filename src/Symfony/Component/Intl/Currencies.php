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
 * Gives access to currency-related ICU data.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
final class Currencies extends ResourceBundle
{
    private const INDEX_SYMBOL = 0;
    private const INDEX_NAME = 1;
    private const INDEX_FRACTION_DIGITS = 0;
    private const INDEX_ROUNDING_INCREMENT = 1;

    /**
     * @return string[]
     */
    public static function getCurrencyCodes(): array
    {
        return self::readEntry(['Currencies'], 'meta');
    }

    public static function exists(string $currency): bool
    {
        try {
            self::readEntry(['Names', $currency, self::INDEX_NAME]);

            return true;
        } catch (MissingResourceException $e) {
            return false;
        }
    }

    /**
     * @throws MissingResourceException if the currency code does not exist
     */
    public static function getName(string $currency, string $displayLocale = null): string
    {
        return self::readEntry(['Names', $currency, self::INDEX_NAME], $displayLocale);
    }

    /**
     * @return string[]
     */
    public static function getNames(string $displayLocale = null): array
    {
        // ====================================================================
        // For reference: It is NOT possible to return names indexed by
        // numeric code here, because some numeric codes map to multiple
        // 3-letter codes (e.g. 32 => "ARA", "ARP", "ARS")
        // ====================================================================

        $names = self::readEntry(['Names'], $displayLocale);

        if ($names instanceof \Traversable) {
            $names = iterator_to_array($names);
        }

        array_walk($names, function (&$value) {
            $value = $value[self::INDEX_NAME];
        });

        return self::asort($names, $displayLocale);
    }

    /**
     * @throws MissingResourceException if the currency code does not exist
     */
    public static function getSymbol(string $currency, string $displayLocale = null): string
    {
        return self::readEntry(['Names', $currency, self::INDEX_SYMBOL], $displayLocale);
    }

    public static function getFractionDigits(string $currency): int
    {
        try {
            return self::readEntry(['Meta', $currency, self::INDEX_FRACTION_DIGITS], 'meta');
        } catch (MissingResourceException $e) {
            return self::readEntry(['Meta', 'DEFAULT', self::INDEX_FRACTION_DIGITS], 'meta');
        }
    }

    /**
     * @return float|int
     */
    public static function getRoundingIncrement(string $currency)
    {
        try {
            return self::readEntry(['Meta', $currency, self::INDEX_ROUNDING_INCREMENT], 'meta');
        } catch (MissingResourceException $e) {
            return self::readEntry(['Meta', 'DEFAULT', self::INDEX_ROUNDING_INCREMENT], 'meta');
        }
    }

    /**
     * @throws MissingResourceException if the currency code has no numeric code
     */
    public static function getNumericCode(string $currency): int
    {
        return self::readEntry(['Alpha3ToNumeric', $currency], 'meta');
    }

    /**
     * @throws MissingResourceException if the numeric code does not exist
     */
    public static function forNumericCode(int $numericCode): array
    {
        return self::readEntry(['NumericToAlpha3', (string) $numericCode], 'meta');
    }

    protected static function getPath(): string
    {
        return Intl::getDataDirectory().'/'.Intl::CURRENCY_DIR;
    }
}
