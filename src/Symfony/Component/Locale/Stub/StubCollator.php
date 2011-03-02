<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
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

    const ON = 17;
    const OFF = 16;
    const DEFAULT_VALUE = -1;
    const FRENCH_COLLATION = 0;
    const ALTERNATE_HANDLING = 1;
    const NON_IGNORABLE = 21;
    const SHIFTED = 20;
    const CASE_FIRST = 2;
    const LOWER_FIRST = 24;
    const UPPER_FIRST = 25;
    const CASE_LEVEL = 3;
    const NORMALIZATION_MODE = 4;
    const STRENGTH = 5;
    const PRIMARY = 0;
    const SECONDARY = 1;
    const TERTIARY = 2;
    const QUATERNARY = 3;
    const IDENTICAL = 15;
    const HIRAGANA_QUATERNARY_MODE = 6;
    const NUMERIC_COLLATION = 7;
    const DEFAULT_STRENGTH = 2;
    const SORT_REGULAR = 0;
    const SORT_NUMERIC = 2;
    const SORT_STRING = 1;

    public function __construct($locale)
    {
        if ('en' != $locale) {
            throw new MethodArgumentValueNotImplementedException(__METHOD__, 'locale', $locale, 'Only the \'en\' locale is supported');
        }
    }

    /**
     * Sort array maintaining index association
     *
     * @param array $array Input array
     * @param array $sortFlag Flags for sorting, can be one of the following:
     *                        StubCollator::SORT_REGULAR - compare items normally (don't change types)
     *                        StubCollator::SORT_NUMERIC - compare items numerically
     *                        StubCollator::SORT_STRING - compare items as strings
     * @return True on success or false on failure.
     */
    public function asort(&$array, $sortFlag = self::SORT_REGULAR)
    {
        $intlToPlainFlagMap = array(
            self::SORT_REGULAR  => \SORT_REGULAR,
            self::SORT_NUMERIC  => \SORT_NUMERIC,
            self::SORT_STRING   => \SORT_STRING,
        );

        $plainSortFlag = isset($intlToPlainFlagMap[$sortFlag]) ? $intlToPlainFlagMap[$sortFlag] : self::SORT_REGULAR;

        return asort($array);
    }

    public function compare($a, $b)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

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

    public function getSortKey($string)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    public function getStrength()
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    public function setAttribute($attr, $val)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    public function setStrength($strength)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    public function sortWithSortKeys(&$arr)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    public function sort(&$arr, $sort_flag = null)
    {
        throw new MethodNotImplementedException(__METHOD__);
    }

    static public function create($locale)
    {
        return new self($locale);
    }
}
