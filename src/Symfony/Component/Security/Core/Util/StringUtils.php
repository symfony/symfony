<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Util;

@trigger_error('The '.__NAMESPACE__.'\\StringUtils class is deprecated since version 2.8 and will be removed in 3.0. Use hash_equals() instead.', E_USER_DEPRECATED);

use Symfony\Polyfill\Util\Binary;

/**
 * String utility functions.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated since 2.8, to be removed in 3.0.
 */
class StringUtils
{
    /**
     * This class should not be instantiated.
     */
    private function __construct()
    {
    }

    /**
     * Compares two strings.
     *
     * This method implements a constant-time algorithm to compare strings.
     * Regardless of the used implementation, it will leak length information.
     *
     * @param string $knownString The string of known length to compare against
     * @param string $userInput   The string that the user can control
     *
     * @return bool true if the two strings are the same, false otherwise
     */
    public static function equals($knownString, $userInput)
    {
        // Avoid making unnecessary duplications of secret data
        if (!is_string($knownString)) {
            $knownString = (string) $knownString;
        }

        if (!is_string($userInput)) {
            $userInput = (string) $userInput;
        }

        return hash_equals($knownString, $userInput);
    }

    /**
     * Returns the number of bytes in a string.
     *
     * @param string $string The string whose length we wish to obtain
     *
     * @return int
     */
    public static function safeStrlen($string)
    {
        return Binary::strlen($string);
    }
}
