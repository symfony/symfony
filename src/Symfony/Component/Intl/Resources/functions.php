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
    function intl_is_failure($error_code)
    {
        return IntlGlobals::isFailure($error_code);
    }
}
if (!function_exists('intl_get_error_code')) {
    function intl_get_error_code()
    {
        return IntlGlobals::getErrorCode();
    }
}
if (!function_exists('intl_get_error_message')) {
    function intl_get_error_message()
    {
        return IntlGlobals::getErrorMessage();
    }
}
if (!function_exists('intl_error_name')) {
    function intl_error_name($error_code)
    {
        return IntlGlobals::getErrorName($error_code);
    }
}
