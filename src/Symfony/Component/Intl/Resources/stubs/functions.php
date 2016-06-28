<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\Intl\Globals\IntlGlobals;

if (!function_exists('intl_is_failure')) {
    /**
     * Stub implementation for the {@link intl_is_failure()} function of the intl
     * extension.
     *
     * @author Bernhard Schussek <bschussek@gmail.com>
     *
     * @param int $errorCode The error code returned by intl_get_error_code()
     *
     * @return bool Whether the error code indicates an error
     *
     * @see IntlGlobals::isFailure()
     */
    function intl_is_failure($errorCode)
    {
        return IntlGlobals::isFailure($errorCode);
    }

    /**
     * Stub implementation for the {@link intl_get_error_code()} function of the
     * intl extension.
     *
     * @author Bernhard Schussek <bschussek@gmail.com>
     *
     * @return bool The error code of the last intl function call or
     *              IntlGlobals::U_ZERO_ERROR if no error occurred.
     *
     * @see IntlGlobals::getErrorCode()
     */
    function intl_get_error_code()
    {
        return IntlGlobals::getErrorCode();
    }

    /**
     * Stub implementation for the {@link intl_get_error_code()} function of the
     * intl extension.
     *
     * @author Bernhard Schussek <bschussek@gmail.com>
     *
     * @return bool The error message of the last intl function call or
     *              "U_ZERO_ERROR" if no error occurred.
     *
     * @see IntlGlobals::getErrorMessage()
     */
    function intl_get_error_message()
    {
        return IntlGlobals::getErrorMessage();
    }

    /**
     * Stub implementation for the {@link intl_error_name()} function of the intl
     * extension.
     *
     * @param int $errorCode The error code
     *
     * @return string The name of the error code constant
     *
     * @see IntlGlobals::getErrorName()
     */
    function intl_error_name($errorCode)
    {
        return IntlGlobals::getErrorName($errorCode);
    }
}
