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
class BinaryUtil
{
    public const BASE10 = [
        '' => '0123456789',
        0, 1, 2, 3, 4, 5, 6, 7, 8, 9,
    ];

    public static function toBase(string $bytes, array $map): string
    {
        $base = \strlen($alphabet = $map['']);
        $bytes = array_values(unpack(\PHP_INT_SIZE >= 8 ? 'n*' : 'C*', $bytes));
        $digits = '';

        while ($count = \count($bytes)) {
            $quotient = [];
            $remainder = 0;

            for ($i = 0; $i !== $count; ++$i) {
                $carry = $bytes[$i] + ($remainder << (\PHP_INT_SIZE >= 8 ? 16 : 8));
                $digit = intdiv($carry, $base);
                $remainder = $carry % $base;

                if ($digit || $quotient) {
                    $quotient[] = $digit;
                }
            }

            $digits = $alphabet[$remainder].$digits;
            $bytes = $quotient;
        }

        return $digits;
    }

    public static function fromBase(string $digits, array $map): string
    {
        $base = \strlen($map['']);
        $count = \strlen($digits);
        $bytes = [];

        while ($count) {
            $quotient = [];
            $remainder = 0;

            for ($i = 0; $i !== $count; ++$i) {
                $carry = ($bytes ? $digits[$i] : $map[$digits[$i]]) + $remainder * $base;

                if (\PHP_INT_SIZE >= 8) {
                    $digit = $carry >> 16;
                    $remainder = $carry & 0xFFFF;
                } else {
                    $digit = $carry >> 8;
                    $remainder = $carry & 0xFF;
                }

                if ($digit || $quotient) {
                    $quotient[] = $digit;
                }
            }

            $bytes[] = $remainder;
            $count = \count($digits = $quotient);
        }

        return pack(\PHP_INT_SIZE >= 8 ? 'n*' : 'C*', ...array_reverse($bytes));
    }

    public static function add(string $a, string $b): string
    {
        $carry = 0;
        for ($i = 7; 0 <= $i; --$i) {
            $carry += \ord($a[$i]) + \ord($b[$i]);
            $a[$i] = \chr($carry & 0xFF);
            $carry >>= 8;
        }

        return $a;
    }
}
