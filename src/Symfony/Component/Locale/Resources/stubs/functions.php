<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Stub implementation for the intl_is_failure function of the intl extension
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 * @param  integer $errorCode  The error code returned by intl_get_error_code()
 * @return Boolean Whether the error code indicates an error
 * @see    Symfony\Component\Locale\Stub\StubIntl::isFailure
 */
function intl_is_failure($errorCode) {
    return \Symfony\Component\Locale\Stub\StubIntl::isFailure($errorCode);
}

/**
 * Stub implementation for the intl_get_error_code function of the intl extension
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 * @return Boolean The error code of the last intl function call or
 *                 StubIntl::U_ZERO_ERROR if no error occurred
 * @see    Symfony\Component\Locale\Stub\StubIntl::getErrorCode
 */
function intl_get_error_code() {
    return \Symfony\Component\Locale\Stub\StubIntl::getErrorCode();
}
/**
 * Stub implementation for the intl_get_error_code function of the intl extension
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 * @return Boolean The error message of the last intl function call or
 *                 "U_ZERO_ERROR" if no error occurred
 * @see    Symfony\Component\Locale\Stub\StubIntl::getErrorMessage
 */
function intl_get_error_message() {
    return \Symfony\Component\Locale\Stub\StubIntl::getErrorMessage();
}
