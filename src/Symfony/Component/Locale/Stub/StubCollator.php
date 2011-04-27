<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Locale\Stub;

use Symfony\Component\Locale\Exception\NotImplementedException;
use Symfony\Component\Locale\Exception\MethodNotImplementedException;
use Symfony\Component\Locale\Exception\MethodArgumentValueNotImplementedException;

/**
 * Provides a stub Collator for the 'en' locale.
 *
 * @author Igor Wiedler <igor@wiedler.ch>
 */
class StubCollator
{
    /**
     * Constants defined by the intl extension, not class constants in IntlDateFormatter
     * TODO: remove if the Form component drop the call to the intl_is_failure() function
     *
     * @see StubIntlDateFormatter::getErrorCode()
     * @see StubIntlDateFormatter::getErrorMessage()
     */
    const U_ZERO_ERROR = 0;
    const U_ZERO_ERROR_MESSAGE = 'U_ZERO_ERROR';

    /** Attribute constants */
    const FRENCH_COLLATION = 0;
    const ALTERNATE_HANDLING = 1;
    const CASE_FIRST = 2;
    const CASE_LEVEL = 3;
    const NORMALIZATION_MODE = 4;
    const STRENGTH = 5;
    const HIRAGANA_QUATERNARY_MODE = 6;
    const NUMERIC_COLLATION = 7;

    /** Attribute constants values */
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

    /** Sorting options */
    const SORT_REGULAR = 0;
    const SORT_NUMERIC = 2;
    const SORT_STRING = 1;

    /**
     * Constructor
     *
     * @param  string  $locale   The locale code
     * @throws MethodArgumentValueNotImplementedException  When $locale different than 'en' is passed
     */
    public function __construct($locale)
    {
        if ('en' != $locale) {
            throw new MethodArgumentValueNotImplementedException(__METHOD__, 'locale', $locale, 'Only the \'en\' locale is supported');
        }
    }

    /**
     * Static constructor
     *
     * @param  string  $locale   The locale code
     * @throws MethodArgumentValueNotImplementedException  When $locale different than 'en' is passed
     */
    static public function create($locale)
    {
        return new self($locale);
    }

    /**
     * Sort array maintaining index association
     *
     * @param  array  &$array    Input array
     * @param  array  $sortFlag  Flags for sorting, can be one of the following:
     *                           StubCollator::SORT_REGULAR - compare items normally (don't change types)
     *                           StubCollator::SORT_NUMERIC - compare items numerically
     *                           StubCollator::SORT_STRING - compare items as strings
     * @return Boolean           True on success or false on failure
     */
    public function asort(&$array, $sortFlag = self::SORT_REGULAR)
    {
        $intlToPlainFlagMap = array(
            self::SORT_REGULAR => \SORT_REGULAR,
            self::SORT_NUMERIC => \SORT_NUMERIC,
            self::SORT_STRING  => \SORT_STRING,
        );

        $plainSortFlag = isset($intlToPlainFlagMap[$sortFlag]) ? $intlToPlainFlagMap[$sortFlag] : self::SORT_REGULAR;

        return asort($array, $plainSortFlag);
    }

    /**
     * Compare two Unicode strings
     *
     * @param  string  $str1   The first string to compare
     * @param  string  $str2   The second string to compare
     * @return Boolean|int     Return the comparison result or false on failure:
     *                         1 if $str1 is greater than $str2
     *                         0 if $str1 is equal than $str2
     *                         -1 if $str1 is less than $str2
     * @see    http://www.php.net/manual/en/collator.compare.php
     * @throws MethodNotImplementedException
     */
    public function compare($str1, $str2)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    /**
     * Get a value of an integer collator attribute
     *
     * @param  int   $attr   An attribute specifier, one of the attribute constants
     * @return Boolean|int   The attribute value on success or false on error
     * @see    http://www.php.net/manual/en/collator.getattribute.php
     * @throws MethodNotImplementedException
     */
    public function getAttribute($attr)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    /**
     * Returns collator's last error code. Always returns the U_ZERO_ERROR class constant value
     *
     * @return int  The error code from last collator call
     */
    public function getErrorCode()
    {
        return self::U_ZERO_ERROR;
    }

    /**
     * Returns collator's last error message. Always returns the U_ZERO_ERROR_MESSAGE class constant value
     *
     * @return string  The error message from last collator call
     */
    public function getErrorMessage()
    {
        return self::U_ZERO_ERROR_MESSAGE;
    }

    /**
     * Returns the collator's locale
     *
     * @param  int      $type     The locale name type to return between valid or actual (StubLocale::VALID_LOCALE or StubLocale::ACTUAL_LOCALE, respectively)
     * @return string             The locale name used to create the collator
     */
    public function getLocale($type = StubLocale::ACTUAL_LOCALE)
    {
        return 'en';
    }

    /**
     * Get sorting key for a string
     *
     * @param  string   $string   The string to produce the key from
     * @return string             The collation key for $string
     * @see    http://www.php.net/manual/en/collator.getsortkey.php
     * @throws MethodNotImplementedException
     */
    public function getSortKey($string)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    /**
     * Get current collator's strenght
     *
     * @return Boolean|int   The current collator's strenght or false on failure
     * @see    http://www.php.net/manual/en/collator.getstrength.php
     * @throws MethodNotImplementedException
     */
    public function getStrength()
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    /**
     * Set a collator's attribute
     *
     * @param  int   $attr   An attribute specifier, one of the attribute constants
     * @param  int   $val    The attribute value, one of the attribute value constants
     * @return Boolean       True on success or false on failure
     * @see    http://www.php.net/manual/en/collator.setattribute.php
     * @throws MethodNotImplementedException
     */
    public function setAttribute($attr, $val)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    /**
     * Set the collator's strength
     *
     * @param  int    $strength  Strength to set, possible values:
     *                           StubCollator::PRIMARY
     *                           StubCollator::SECONDARY
     *                           StubCollator::TERTIARY
     *                           StubCollator::QUATERNARY
     *                           StubCollator::IDENTICAL
     *                           StubCollator::DEFAULT
     * @return Boolean           True on success or false on failure
     * @see    http://www.php.net/manual/en/collator.setstrength.php
     * @throws MethodNotImplementedException
     */
    public function setStrength($strength)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    /**
     * Sort array using specified collator and sort keys
     *
     * @param  array   &$arr   Array of strings to sort
     * @return Boolean         True on success or false on failure
     * @see    http://www.php.net/manual/en/collator.sortwithsortkeys.php
     * @throws MethodNotImplementedException
     */
    public function sortWithSortKeys(&$arr)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    /**
     * Sort array using specified collator
     *
     * @param  array   &$arr       Array of string to sort
     * @param  int     $sortFlag   Optional sorting type, one of the following:
     *                             StubCollator::SORT_REGULAR
     *                             StubCollator::SORT_NUMERIC
     *                             StubCollator::SORT_STRING
     * @return Boolean             True on success or false on failure
     * @see    http://www.php.net/manual/en/collator.sort.php
     * @throws MethodNotImplementedException
     */
    public function sort(&$arr, $sortFlag = self::SORT_REGULAR)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }
}
