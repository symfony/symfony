<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Collator;

use Symfony\Component\Intl\Exception\MethodArgumentValueNotImplementedException;
use Symfony\Component\Intl\Exception\MethodNotImplementedException;
use Symfony\Component\Intl\Globals\IntlGlobals;
use Symfony\Component\Intl\Locale\Locale;

/**
 * Replacement for PHP's native {@link \Collator} class.
 *
 * The only methods currently supported in this class are:
 *
 *  - {@link \__construct}
 *  - {@link create}
 *  - {@link asort}
 *  - {@link getErrorCode}
 *  - {@link getErrorMessage}
 *  - {@link getLocale}
 *
 * @author Igor Wiedler <igor@wiedler.ch>
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @internal
 */
abstract class Collator
{
    /* Attribute constants */
    public const FRENCH_COLLATION = 0;
    public const ALTERNATE_HANDLING = 1;
    public const CASE_FIRST = 2;
    public const CASE_LEVEL = 3;
    public const NORMALIZATION_MODE = 4;
    public const STRENGTH = 5;
    public const HIRAGANA_QUATERNARY_MODE = 6;
    public const NUMERIC_COLLATION = 7;

    /* Attribute constants values */
    public const DEFAULT_VALUE = -1;

    public const PRIMARY = 0;
    public const SECONDARY = 1;
    public const TERTIARY = 2;
    public const DEFAULT_STRENGTH = 2;
    public const QUATERNARY = 3;
    public const IDENTICAL = 15;

    public const OFF = 16;
    public const ON = 17;

    public const SHIFTED = 20;
    public const NON_IGNORABLE = 21;

    public const LOWER_FIRST = 24;
    public const UPPER_FIRST = 25;

    /* Sorting options */
    public const SORT_REGULAR = 0;
    public const SORT_NUMERIC = 2;
    public const SORT_STRING = 1;

    /**
     * @param string|null $locale The locale code. The only currently supported locale is "en" (or null using the default locale, i.e. "en")
     *
     * @throws MethodArgumentValueNotImplementedException When $locale different than "en" or null is passed
     */
    public function __construct(?string $locale)
    {
        if ('en' !== $locale && null !== $locale) {
            throw new MethodArgumentValueNotImplementedException(__METHOD__, 'locale', $locale, 'Only the locale "en" is supported');
        }
    }

    /**
     * Static constructor.
     *
     * @param string|null $locale The locale code. The only currently supported locale is "en" (or null using the default locale, i.e. "en")
     *
     * @return static
     *
     * @throws MethodArgumentValueNotImplementedException When $locale different than "en" or null is passed
     */
    public static function create(?string $locale)
    {
        return new static($locale);
    }

    /**
     * Sort array maintaining index association.
     *
     * @param array &$array   Input array
     * @param int   $sortFlag Flags for sorting, can be one of the following:
     *                        Collator::SORT_REGULAR - compare items normally (don't change types)
     *                        Collator::SORT_NUMERIC - compare items numerically
     *                        Collator::SORT_STRING - compare items as strings
     *
     * @return bool True on success or false on failure
     */
    public function asort(array &$array, int $sortFlag = self::SORT_REGULAR)
    {
        $intlToPlainFlagMap = [
            self::SORT_REGULAR => \SORT_REGULAR,
            self::SORT_NUMERIC => \SORT_NUMERIC,
            self::SORT_STRING => \SORT_STRING,
        ];

        $plainSortFlag = isset($intlToPlainFlagMap[$sortFlag]) ? $intlToPlainFlagMap[$sortFlag] : self::SORT_REGULAR;

        return asort($array, $plainSortFlag);
    }

    /**
     * Not supported. Compare two Unicode strings.
     *
     * @param string $str1 The first string to compare
     * @param string $str2 The second string to compare
     *
     * @return bool|int Return the comparison result or false on failure:
     *                  1 if $str1 is greater than $str2
     *                  0 if $str1 is equal than $str2
     *                  -1 if $str1 is less than $str2
     *
     * @see https://php.net/collator.compare
     *
     * @throws MethodNotImplementedException
     */
    public function compare(string $str1, string $str2)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    /**
     * Not supported. Get a value of an integer collator attribute.
     *
     * @param int $attr An attribute specifier, one of the attribute constants
     *
     * @return bool|int The attribute value on success or false on error
     *
     * @see https://php.net/collator.getattribute
     *
     * @throws MethodNotImplementedException
     */
    public function getAttribute(int $attr)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    /**
     * Returns collator's last error code. Always returns the U_ZERO_ERROR class constant value.
     *
     * @return int The error code from last collator call
     */
    public function getErrorCode()
    {
        return IntlGlobals::U_ZERO_ERROR;
    }

    /**
     * Returns collator's last error message. Always returns the U_ZERO_ERROR_MESSAGE class constant value.
     *
     * @return string The error message from last collator call
     */
    public function getErrorMessage()
    {
        return 'U_ZERO_ERROR';
    }

    /**
     * Returns the collator's locale.
     *
     * @param int $type Not supported. The locale name type to return (Locale::VALID_LOCALE or Locale::ACTUAL_LOCALE)
     *
     * @return string The locale used to create the collator. Currently always
     *                returns "en".
     */
    public function getLocale(int $type = Locale::ACTUAL_LOCALE)
    {
        return 'en';
    }

    /**
     * Not supported. Get sorting key for a string.
     *
     * @param string $string The string to produce the key from
     *
     * @return string The collation key for $string
     *
     * @see https://php.net/collator.getsortkey
     *
     * @throws MethodNotImplementedException
     */
    public function getSortKey(string $string)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    /**
     * Not supported. Get current collator's strength.
     *
     * @return bool|int The current collator's strength or false on failure
     *
     * @see https://php.net/collator.getstrength
     *
     * @throws MethodNotImplementedException
     */
    public function getStrength()
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    /**
     * Not supported. Set a collator's attribute.
     *
     * @param int $attr An attribute specifier, one of the attribute constants
     * @param int $val  The attribute value, one of the attribute value constants
     *
     * @return bool True on success or false on failure
     *
     * @see https://php.net/collator.setattribute
     *
     * @throws MethodNotImplementedException
     */
    public function setAttribute(int $attr, int $val)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    /**
     * Not supported. Set the collator's strength.
     *
     * @param int $strength Strength to set, possible values:
     *                      Collator::PRIMARY
     *                      Collator::SECONDARY
     *                      Collator::TERTIARY
     *                      Collator::QUATERNARY
     *                      Collator::IDENTICAL
     *                      Collator::DEFAULT
     *
     * @return bool True on success or false on failure
     *
     * @see https://php.net/collator.setstrength
     *
     * @throws MethodNotImplementedException
     */
    public function setStrength(int $strength)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    /**
     * Not supported. Sort array using specified collator and sort keys.
     *
     * @param array &$arr Array of strings to sort
     *
     * @return bool True on success or false on failure
     *
     * @see https://php.net/collator.sortwithsortkeys
     *
     * @throws MethodNotImplementedException
     */
    public function sortWithSortKeys(array &$arr)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    /**
     * Not supported. Sort array using specified collator.
     *
     * @param array &$arr     Array of string to sort
     * @param int   $sortFlag Optional sorting type, one of the following:
     *                        Collator::SORT_REGULAR
     *                        Collator::SORT_NUMERIC
     *                        Collator::SORT_STRING
     *
     * @return bool True on success or false on failure
     *
     * @see https://php.net/collator.sort
     *
     * @throws MethodNotImplementedException
     */
    public function sort(array &$arr, int $sortFlag = self::SORT_REGULAR)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }
}
