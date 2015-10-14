<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Polyfill\Util;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
class BinaryNoFuncOverload
{
    static function strlen($s)
    {
        return strlen($s);
    }

    static function strpos($haystack, $needle, $offset = 0)
    {
        return strpos($haystack, $needle, $offset);
    }

    static function strrpos($haystack, $needle, $offset = 0)
    {
        return strrpos($haystack, $needle, $offset);
    }

    static function substr($string, $start, $length = PHP_INT_MAX)
    {
        return substr($string, $start, $length);
    }

    static function stripos($s, $needle, $offset = 0)
    {
        return stripos($s, $needle, $offset);
    }

    static function stristr($s, $needle, $part = false)
    {
        return stristr($s, $needle, $part);
    }

    static function strrchr($s, $needle, $part = false)
    {
        return strrchr($s, $needle, $part);
    }

    static function strripos($s, $needle, $offset = 0)
    {
        return strripos($s, $needle, $offset);
    }

    static function strstr($s, $needle, $part = false)
    {
        return strstr($s, $needle, $part);
    }
}
