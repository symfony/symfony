<?php

namespace Symfony\Component\Security\Core\Util;

/**
 * String utility functions.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
final class String
{
    private final function __construct() {}

    /**
     * Whether two strings are equal.
     *
     * This function uses a constant-time algorithm to compare the strings.
     *
     * @param string $str1
     * @param string $str2
     * @return Boolean
     */
    public static function equals($str1, $str2)
    {
        if (strlen($str1) !== $c = strlen($str2)) {
            return false;
        }

        $result = 0;
        for ($i=0; $i<$c; $i++) {
            $result |= ord($str1[$i]) ^ ord($str2[$i]);
        }

        return 0 === $result;
    }
}