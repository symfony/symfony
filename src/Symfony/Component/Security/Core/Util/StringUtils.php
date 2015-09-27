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

        if (function_exists('hash_equals')) {
            return hash_equals($knownString, $userInput);
        }

        $knownLen = self::safeStrlen($knownString);
        $userLen = self::safeStrlen($userInput);

        if ($userLen !== $knownLen) {
            return false;
        }

        $result = 0;

        for ($i = 0; $i < $knownLen; ++$i) {
            $result |= (ord($knownString[$i]) ^ ord($userInput[$i]));
        }

        // They are only identical strings if $result is exactly 0...
        return 0 === $result;
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
        // Premature optimization
        // Since this cannot be changed at runtime, we can cache it
        static $funcExists = null;
        if (null === $funcExists) {
            $funcExists = function_exists('mb_strlen');
        }

        if ($funcExists) {
            return mb_strlen($string, '8bit');
        }

        return strlen($string);
    }
}
