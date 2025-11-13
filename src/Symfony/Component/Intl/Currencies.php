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
    private const INDEX_CASH_FRACTION_DIGITS = 2;
    private const INDEX_CASH_ROUNDING_INCREMENT = 3;

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
        } catch (MissingResourceException) {
            return false;
        }
    }

    /**
     * @throws MissingResourceException if the currency code does not exist
     */
    public static function getName(string $currency, ?string $displayLocale = null): string
    {
        return self::readEntry(['Names', $currency, self::INDEX_NAME], $displayLocale);
    }

    /**
     * @return string[]
     */
    public static function getNames(?string $displayLocale = null): array
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
    public static function getSymbol(string $currency, ?string $displayLocale = null): string
    {
        return self::readEntry(['Names', $currency, self::INDEX_SYMBOL], $displayLocale);
    }

    public static function getFractionDigits(string $currency): int
    {
        try {
            return self::readEntry(['Meta', $currency, self::INDEX_FRACTION_DIGITS], 'meta');
        } catch (MissingResourceException) {
            return self::readEntry(['Meta', 'DEFAULT', self::INDEX_FRACTION_DIGITS], 'meta');
        }
    }

    public static function getRoundingIncrement(string $currency): int
    {
        try {
            return self::readEntry(['Meta', $currency, self::INDEX_ROUNDING_INCREMENT], 'meta');
        } catch (MissingResourceException) {
            return self::readEntry(['Meta', 'DEFAULT', self::INDEX_ROUNDING_INCREMENT], 'meta');
        }
    }

    public static function getCashFractionDigits(string $currency): int
    {
        try {
            return self::readEntry(['Meta', $currency, self::INDEX_CASH_FRACTION_DIGITS], 'meta');
        } catch (MissingResourceException) {
            return self::readEntry(['Meta', 'DEFAULT', self::INDEX_CASH_FRACTION_DIGITS], 'meta');
        }
    }

    public static function getCashRoundingIncrement(string $currency): int
    {
        try {
            return self::readEntry(['Meta', $currency, self::INDEX_CASH_ROUNDING_INCREMENT], 'meta');
        } catch (MissingResourceException) {
            return self::readEntry(['Meta', 'DEFAULT', self::INDEX_CASH_ROUNDING_INCREMENT], 'meta');
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

    /**
     * @param string             $country     e.g. 'FR'
     * @param ?bool              $legalTender If the currency must be a legal tender; null to not filter anything
     * @param ?bool              $active      Indicates whether the currency should always be active for the given $date; null to not filter anything
     * @param \DateTimeInterface $date        The date on which the check will be performed
     *
     * @return list<string> a list of unique currencies
     *
     * @throws MissingResourceException if the given $country does not exist
     */
    public static function forCountry(string $country, ?bool $legalTender = true, ?bool $active = true, \DateTimeInterface $date = new \DateTimeImmutable('today', new \DateTimeZone('Etc/UTC')), bool $includeUndated = true): array
    {
        $currencies = [];

        foreach (self::readEntry(['Map', $country], 'meta') as $currency => $currencyMetadata) {
            if (null !== $legalTender && $legalTender !== self::isLegalTender($currencyMetadata)) {
                continue;
            }

            if (null === $active) {
                $currencies[] = $currency;

                continue;
            }

            if (self::isDateActive($currencyMetadata, $date, $includeUndated) !== $active) {
                continue;
            }

            $currencies[] = $currency;
        }

        return $currencies;
    }

    /**
     * @param string             $country     e.g. 'FR'
     * @param string             $currency    e.g. 'USD'
     * @param ?bool              $legalTender If the currency must be a legal tender; null to not filter anything
     * @param ?bool              $active      Indicates whether the currency should always be active for the given $date; null to not filter anything
     * @param \DateTimeInterface $date        The date that will be checked when $active is set to true
     */
    public static function isValidInCountry(string $country, string $currency, ?bool $legalTender = true, ?bool $active = true, \DateTimeInterface $date = new \DateTimeImmutable('today', new \DateTimeZone('Etc/UTC')), bool $includeUndated = true): bool
    {
        if (!self::exists($currency)) {
            throw new \InvalidArgumentException("The currency $currency does not exist.");
        }

        try {
            $currencyMetadata = self::readEntry(['Map', $country, $currency], 'meta');
        } catch (MissingResourceException) {
            return false;
        }

        if (null !== $legalTender && $legalTender !== self::isLegalTender($currencyMetadata)) {
            return false;
        }

        if (null === $active) {
            return true;
        }

        return self::isDateActive($currencyMetadata, $date, $includeUndated) === $active;
    }

    /**
     * @param array{tender?: bool} $currencyMetadata When the `tender` property does not exist, it means it is a legal tender
     */
    private static function isLegalTender(array $currencyMetadata): bool
    {
        return !\array_key_exists('tender', $currencyMetadata) || false !== $currencyMetadata['tender'];
    }

    /**
     * @param array{from?: string, to?: string} $currencyMetadata
     * @param \DateTimeInterface                $date             The date on which the check will be performed
     * @param bool                              $includeUndated   Whether the currency should be included or not when there are no validity dates
     */
    private static function isDateActive(array $currencyMetadata, \DateTimeInterface $date, bool $includeUndated): bool
    {
        if (!\array_key_exists('from', $currencyMetadata)) {
            // Note: currencies that are not legal tender don't have often validity dates.
            return $includeUndated;
        }

        $from = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', $currencyMetadata['from'], new \DateTimeZone('Etc/UTC'));

        if (\array_key_exists('to', $currencyMetadata)) {
            $to = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', $currencyMetadata['to'], new \DateTimeZone('Etc/UTC'));
        } else {
            $to = null;
        }

        return $from <= $date && (null === $to || $to >= $date);
    }

    /**
     * @param string             $currency    e.g. 'USD'
     * @param ?bool              $legalTender If the currency must be a legal tender; null to not filter anything
     * @param ?bool              $active      Indicates whether the currency should always be active for the given $date; null to not filter anything
     * @param \DateTimeInterface $date        the date on which the check will be performed if $active is set to true
     */
    public static function isValidInAnyCountry(string $currency, ?bool $legalTender = true, ?bool $active = true, \DateTimeInterface $date = new \DateTimeImmutable('today', new \DateTimeZone('Etc/UTC')), bool $includeUndated = true): bool
    {
        if (!self::exists($currency)) {
            throw new \InvalidArgumentException("The currency $currency does not exist.");
        }

        foreach (self::readEntry(['Map'], 'meta') as $countryCode => $country) {
            foreach ($country as $currencyCode => $currencyMetadata) {
                if ($currencyCode !== $currency) {
                    continue;
                }

                if (null !== $legalTender && $legalTender !== self::isLegalTender($currencyMetadata)) {
                    continue;
                }

                if (null === $active) {
                    return true;
                }

                if (self::isDateActive($currencyMetadata, $date, $includeUndated) !== $active) {
                    continue;
                }

                return true;
            }
        }

        return false;
    }

    protected static function getPath(): string
    {
        return Intl::getDataDirectory().'/'.Intl::CURRENCY_DIR;
    }
}
