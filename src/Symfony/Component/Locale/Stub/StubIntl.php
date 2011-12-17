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

/**
 * Provides fake static versions of the global functions in the intl extension
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
abstract class StubIntl
{
    /**
     * Indicates that no error occurred
     *
     * @var integer
     */
    const U_ZERO_ERROR = 0;

    /**
     * Indicates that an invalid argument was passed
     *
     * @var integer
     */
    const U_ILLEGAL_ARGUMENT_ERROR = 1;

    /**
     * Indicates that the parse() operation failed
     *
     * @var integer
     */
    const U_PARSE_ERROR = 9;

    /**
     * All known error codes
     *
     * @var array
     */
    private static $errorCodes = array(
        self::U_ZERO_ERROR,
        self::U_ILLEGAL_ARGUMENT_ERROR,
        self::U_PARSE_ERROR,
    );

    /**
     * The error messages of all known error codes
     *
     * @var array
     */
    private static $errorMessages = array(
        self::U_ZERO_ERROR => 'U_ZERO_ERROR',
        self::U_ILLEGAL_ARGUMENT_ERROR => 'datefmt_format: takes either an array  or an integer timestamp value : U_ILLEGAL_ARGUMENT_ERROR',
        self::U_PARSE_ERROR => 'Date parsing failed: U_PARSE_ERROR',
    );

    /**
     * The error code of the last operation
     *
     * @var integer
     */
    private static $errorCode = self::U_ZERO_ERROR;

    /**
     * Returns whether the error code indicates a failure
     *
     * @param  integer $errorCode The error code returned by StubIntl::getErrorCode()
     *
     * @return Boolean
     */
    static public function isFailure($errorCode)
    {
        return in_array($errorCode, self::$errorCodes, true)
            && $errorCode !== self::U_ZERO_ERROR;
    }

    /**
     * Returns the error code of the last operation
     *
     * Returns StubIntl::U_ZERO_ERROR if no error occurred.
     *
     * @return integer
     */
    static public function getErrorCode()
    {
        return self::$errorCode;
    }

    /**
     * Returns the error message of the last operation
     *
     * Returns "U_ZERO_ERROR" if no error occurred.
     *
     * @return string
     */
    static public function getErrorMessage()
    {
        return self::$errorMessages[self::$errorCode];
    }

    /**
     * Sets the current error code
     *
     * @param  integer $code  One of the error constants in this class
     *
     * @throws \InvalidArgumentException If the code is not one of the error constants in this class
     */
    static public function setErrorCode($code)
    {
        if (!isset(self::$errorMessages[$code])) {
            throw new \InvalidArgumentException(sprintf('No such error code: "%s"', $code));
        }

        self::$errorCode = $code;
    }
}
