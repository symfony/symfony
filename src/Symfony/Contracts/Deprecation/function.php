<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

if (!function_exists('trigger_deprecation')) {
    /**
     * Triggers a silenced deprecation notice.
     *
     * @param $package The name of the Composer package that is triggering the deprecation
     * @param $version The version of the package that introduced the deprecation
     * @param $message The message of the deprecation
     * @param ...$args Values to insert in the message using printf() formatting
     *
     * @author Nicolas Grekas <p@tchwork.com>
     */
    function trigger_deprecation(string $package, string $version, string $message, mixed ...$args): void
    {
        @trigger_error(($package || $version ? "Since $package $version: " : '').($args ? vsprintf($message, $args) : $message), \E_USER_DEPRECATED);
    }
}
