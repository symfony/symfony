<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Process;

/**
 * ProcessUtils is a bunch of utility methods.
 *
 * This class contains static methods only and is not meant to be instantiated.
 *
 * @author Martin Hasoň <martin.hason@gmail.com>
 */
class ProcessUtils
{
    /**
     * This class should not be instantiated
     */
    private function __construct()
    {
    }

    /**
     * Escapes a string to be used as a shell argument.
     *
     * @param string $argument The argument that will be escaped
     *
     * @return string The escaped argument
     */
    public static function escapeArgument($argument)
    {
        //Fix for PHP bug #43784 escapeshellarg removes % from given string
        //Fix for PHP bug #49446 escapeshellarg dosn`t work on windows
        //@see https://bugs.php.net/bug.php?id=43784
        //@see https://bugs.php.net/bug.php?id=49446
        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            $escapedArgument = '';
            foreach (preg_split('/([%"])/i', $argument, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE) as $part) {
                if ('"' == $part) {
                    $escapedArgument .= '\\"';
                } elseif ('%' == $part) {
                    $escapedArgument .= '^%';
                } else {
                    $escapedArgument .= escapeshellarg($part);
                }
            }

            return $escapedArgument;
        }

        return escapeshellarg($argument);
    }
}
