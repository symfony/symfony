<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

if (!function_exists('frankenphp_handle_request')) {
    function frankenphp_handle_request(callable $callable): bool
    {
        $callable();

        return false;
    }
}
