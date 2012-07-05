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
final class String
{
    private final function __construct()
    {
    }

    /**
     * Compares two strings.
     *
     * This method implements a constant-time algorithm to compare strings.
     *
     * @param string $str1 The first string
     * @param string $str2 The second string
     *
     * @return Boolean true if the two strings are the same, false otherwise
     */
    public static function equals($str1, $str2)
    {
        if (strlen($str1) !== $c = strlen($str2)) {
            return false;
        }

        $result = 0;
        for ($i = 0; $i < $c; $i++) {
            $result |= ord($str1[$i]) ^ ord($str2[$i]);
        }

        return 0 === $result;
    }
}
