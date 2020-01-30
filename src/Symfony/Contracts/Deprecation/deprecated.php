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
 * Triggers a deprecation.
 *
 * As recommended for prod, turn the "zend.assertions" ini setting to 0 or -1 to disable deprecation notices.
 * Alternatively, provide your own implementation of the function and list "symfony/deprecation-contracts"
 * in the "replace" section of your root composer.json if you need any custom behavior.
 *
 * The function doesn't use type hints to make it as fast as possible.
 *
 * @param string $package The name of the Composer package that is triggering the deprecation
 * @param string $version The version of the package that introduced the deprecation
 * @param string $message The message of the deprecation
 * @param scalar ...$args Values to insert in the message using printf() formatting
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
function deprecated($package, $version, $message, ...$args)
{
    assert(@trigger_error(
        ($package || $version ? "Since $package $version: " : '')
        .($args ? vsprintf($message, $args) : $message),
        E_USER_DEPRECATED
    ));
}
