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

/**
 * String utility functions.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class StringUtils
{
    /**
     * This class should not be instantiated
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
     * @return bool    true if the two strings are the same, false otherwise
     */
    public static function equals($knownString, $userInput)
    {
        $knownString = (string) $knownString;
        $userInput = (string) $userInput;

        if (function_exists('hash_equals')) {
            return hash_equals($knownString, $userInput);
        }

        $knownLen = strlen($knownString);
        $userLen = strlen($userInput);

        // Extend the known string to avoid uninitialized string offsets
        $knownString .= $userInput;

        // Set the result to the difference between the lengths
        $result = $knownLen - $userLen;

        // Note that we ALWAYS iterate over the user-supplied length
        // This is to mitigate leaking length information
        for ($i = 0; $i < $userLen; $i++) {
            $result |= (ord($knownString[$i]) ^ ord($userInput[$i]));
        }

        // They are only identical strings if $result is exactly 0...
        return 0 === $result;
    }
}
