<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Polyfill\Functions;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
class Xml
{
    public static function utf8_encode($s)
    {
        $s .= $s;
        $len = strlen($s);

        for ($i = $len >> 1, $j = 0; $i < $len; ++$i, ++$j) {
            switch (true) {
                case $s[$i] < "\x80": $s[$j] = $s[$i]; break;
                case $s[$i] < "\xC0": $s[$j] = "\xC2"; $s[++$j] = $s[$i]; break;
                default: $s[$j] = "\xC3"; $s[++$j] = chr(ord($s[$i]) - 64); break;
            }
        }

        return substr($s, 0, $j);
    }

    public static function utf8_decode($s)
    {
        $s .= '';
        $len = strlen($s);

        for ($i = 0, $j = 0; $i < $len; ++$i, ++$j) {
            switch ($s[$i] & "\xF0") {
                case "\xC0":
                case "\xD0":
                    $c = (ord($s[$i] & "\x1F") << 6) | ord($s[++$i] & "\x3F");
                    $s[$j] = $c < 256 ? chr($c) : '?';
                    break;

                case "\xF0": ++$i;
                case "\xE0":
                    $s[$j] = '?';
                    $i += 2;
                    break;

                default:
                    $s[$j] = $s[$i];
            }
        }

        return substr($s, 0, $j);
    }
}
