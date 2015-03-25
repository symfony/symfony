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
            $knownString = self::castAsString($knownString);
        }

        if (!is_string($userInput)) {
            $userInput = self::castAsString($userInput);
        }

        if (function_exists('hash_equals')) {
            return hash_equals($knownString, $userInput);
        }

        if (function_exists('mb_strlen')) {
            $knownLen = mb_strlen($knownString, '8bit');
            $userLen = mb_strlen($userInput, '8bit');
        } else {
            $knownLen = strlen($knownString);
            $userLen = strlen($userInput);
        }

        // Leaking the length of $knownString is unavoidable.
        // Instead of trying to be smarter than the original,
        // this fallback implementation of hash_equals is as close
        // as possible to its C version.
        // See http://lxr.php.net/xref/PHP_TRUNK/ext/hash/hash.c

        if ($userLen !== $knownLen) {
            return false;
        }

        $result = 0;

        for ($i = 0; $i < $knownLen; $i++) {
            $result |= (ord($knownString[$i]) ^ ord($userInput[$i]));
        }

        // They are only identical strings if $result is exactly 0...
        return 0 === $result;
    }

    /**
     * Preserves copy-on-write references while casting to string
     *
     * @param mixed $value The value to cast as string
     *
     * @return string The value casted as string
     */
    private static function castAsString($value)
    {
        if (is_object($value) && method_exists($value, '__toString')) {
            return $value->__toString();
        }

        return (string) $value;
    }
}
