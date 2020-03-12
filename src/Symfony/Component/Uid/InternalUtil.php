<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Uid;

/**
 * @internal
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class InternalUtil
{
    public static function toBinary(string $digits): string
    {
        $bytes = '';
        $len = \strlen($digits);

        while ($len > $i = strspn($digits, '0')) {
            for ($j = 2, $r = 0; $i < $len; $i += $j, $j = 0) {
                do {
                    $r *= 10;
                    $d = (int) substr($digits, $i, ++$j);
                } while ($i + $j < $len && $r + $d < 256);

                $j = \strlen((string) $d);
                $q = str_pad(($d += $r) >> 8, $j, '0', STR_PAD_LEFT);
                $digits = substr_replace($digits, $q, $i, $j);
                $r = $d % 256;
            }

            $bytes .= \chr($r);
        }

        return strrev($bytes);
    }

    public static function toDecimal(string $bytes): string
    {
        $digits = '';
        $len = \strlen($bytes);

        while ($len > $i = strspn($bytes, "\0")) {
            for ($r = 0; $i < $len; $i += $j) {
                $j = $d = 0;
                do {
                    $r <<= 8;
                    $d = ($d << 8) + \ord($bytes[$i + $j]);
                } while ($i + ++$j < $len && $r + $d < 10);

                if (256 < $d) {
                    $q = intdiv($d += $r, 10);
                    $bytes[$i] = \chr($q >> 8);
                    $bytes[1 + $i] = \chr($q & 0xFF);
                } else {
                    $bytes[$i] = \chr(intdiv($d += $r, 10));
                }
                $r = $d % 10;
            }

            $digits .= (string) $r;
        }

        return strrev($digits);
    }

    public static function binaryAdd(string $a, string $b): string
    {
        $sum = 0;
        for ($i = 7; 0 <= $i; --$i) {
            $sum += \ord($a[$i]) + \ord($b[$i]);
            $a[$i] = \chr($sum & 0xFF);
            $sum >>= 8;
        }

        return $a;
    }
}
