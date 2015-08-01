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

use Symfony\Component\Intl\Exception\MethodNotImplementedException;
use Symfony\Component\Intl\Exception\MethodArgumentValueNotImplementedException;
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
 */
class Collator
{
    /* Attribute constants */
    const FRENCH_COLLATION = 0;
    const ALTERNATE_HANDLING = 1;
    const CASE_FIRST = 2;
    const CASE_LEVEL = 3;
    const NORMALIZATION_MODE = 4;
    const STRENGTH = 5;
    const HIRAGANA_QUATERNARY_MODE = 6;
    const NUMERIC_COLLATION = 7;

    /* Attribute constants values */
    const DEFAULT_VALUE = -1;

    const PRIMARY = 0;
    const SECONDARY = 1;
    const TERTIARY = 2;
    const DEFAULT_STRENGTH = 2;
    const QUATERNARY = 3;
    const IDENTICAL = 15;

    const OFF = 16;
    const ON = 17;

    const SHIFTED = 20;
    const NON_IGNORABLE = 21;

    const LOWER_FIRST = 24;
    const UPPER_FIRST = 25;

    /* Sorting options */
    const SORT_REGULAR = 0;
    const SORT_NUMERIC = 2;
    const SORT_STRING = 1;

    /**
     * Constructor.
     *
     * @param string $locale The locale code. The only currently supported locale is "en" (or null using the default locale, i.e. "en").
     *
     * @throws MethodArgumentValueNotImplementedException When $locale different than "en" or null is passed
     */
    public function __construct($locale)
    {
        if ('en' !== $locale && null !== $locale) {
            throw new MethodArgumentValueNotImplementedException(__METHOD__, 'locale', $locale, 'Only the locale "en" is supported');
        }
    }

    /**
     * Static constructor.
     *
     * @param string $locale The locale code. The only currently supported locale is "en" (or null using the default locale, i.e. "en").
     *
     * @return Collator
     *
     * @throws MethodArgumentValueNotImplementedException When $locale different than "en" or null is passed
     */
    public static function create($locale)
    {
        return new self($locale);
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
    public function asort(&$array, $sortFlag = self::SORT_REGULAR)
    {
        $intlToPlainFlagMap = array(
            self::SORT_REGULAR => \SORT_REGULAR,
            self::SORT_NUMERIC => \SORT_NUMERIC,
            self::SORT_STRING => \SORT_STRING,
        );

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
     * @see http://www.php.net/manual/en/collator.compare.php
     *
     * @throws MethodNotImplementedException
     */
    public function compare($str1, $str2)
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
     * @see http://www.php.net/manual/en/collator.getattribute.php
     *
     * @throws MethodNotImplementedException
     */
    public function getAttribute($attr)
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
    public function getLocale($type = Locale::ACTUAL_LOCALE)
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
     * @see http://www.php.net/manual/en/collator.getsortkey.php
     *
     * @throws MethodNotImplementedException
     */
    public function getSortKey($string)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    /**
     * Not supported. Get current collator's strength.
     *
     * @return bool|int The current collator's strength or false on failure
     *
     * @see http://www.php.net/manual/en/collator.getstrength.php
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
     * @see http://www.php.net/manual/en/collator.setattribute.php
     *
     * @throws MethodNotImplementedException
     */
    public function setAttribute($attr, $val)
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
     * @see http://www.php.net/manual/en/collator.setstrength.php
     *
     * @throws MethodNotImplementedException
     */
    public function setStrength($strength)
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
     * @see http://www.php.net/manual/en/collator.sortwithsortkeys.php
     *
     * @throws MethodNotImplementedException
     */
    public function sortWithSortKeys(&$arr)
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
     * @see http://www.php.net/manual/en/collator.sort.php
     *
     * @throws MethodNotImplementedException
     */
    public function sort(&$arr, $sortFlag = self::SORT_REGULAR)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }
}
