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

use Symfony\Component\Uid\Exception\InvalidArgumentException;

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

    public const BASE58 = [
        '' => '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz',
        1 => 0, 1, 2, 3, 4, 5, 6, 7, 8, 'A' => 9,
        'B' => 10, 'C' => 11, 'D' => 12, 'E' => 13, 'F' => 14, 'G' => 15,
        'H' => 16, 'J' => 17, 'K' => 18, 'L' => 19, 'M' => 20, 'N' => 21,
        'P' => 22, 'Q' => 23, 'R' => 24, 'S' => 25, 'T' => 26, 'U' => 27,
        'V' => 28, 'W' => 29, 'X' => 30, 'Y' => 31, 'Z' => 32, 'a' => 33,
        'b' => 34, 'c' => 35, 'd' => 36, 'e' => 37, 'f' => 38, 'g' => 39,
        'h' => 40, 'i' => 41, 'j' => 42, 'k' => 43, 'm' => 44, 'n' => 45,
        'o' => 46, 'p' => 47, 'q' => 48, 'r' => 49, 's' => 50, 't' => 51,
        'u' => 52, 'v' => 53, 'w' => 54, 'x' => 55, 'y' => 56, 'z' => 57,
    ];

    // https://datatracker.ietf.org/doc/html/rfc9562#section-5.1
    // 0x01b21dd213814000 is the number of 100-ns intervals between the
    // UUID epoch 1582-10-15 00:00:00 and the Unix epoch 1970-01-01 00:00:00.
    public const TIME_OFFSET_INT = 0x01B21DD213814000;
    private const TIME_OFFSET_BIN = "\x01\xb2\x1d\xd2\x13\x81\x40\x00";
    private const TIME_OFFSET_COM1 = "\xfe\x4d\xe2\x2d\xec\x7e\xbf\xff";
    private const TIME_OFFSET_COM2 = "\xfe\x4d\xe2\x2d\xec\x7e\xc0\x00";

    public static function toBase(string $bytes, array $map): string
    {
        $base = \strlen($alphabet = $map['']);
        $digits = '';

        if (\PHP_INT_SIZE >= 8) {
            // Dividing 32-bit limbs by the largest power of the base below 2^31 yields several digits per division
            for ($power = $base, $n = 1; $power * $base < 0x80000000; ++$n) {
                $power *= $base;
            }

            $limbs = unpack('N*', str_pad($bytes, (\strlen($bytes) + 3) & ~3, "\0", \STR_PAD_LEFT));

            while ($limbs) {
                $quotient = [];
                $remainder = 0;

                foreach ($limbs as $limb) {
                    $carry = $limb | $remainder << 32;
                    $remainder = $carry % $power;

                    if (($digit = intdiv($carry, $power)) || $quotient) {
                        $quotient[] = $digit;
                    }
                }

                $limbs = $quotient;
                $i = $n;

                do {
                    $digits = $alphabet[$remainder % $base].$digits;
                    $remainder = intdiv($remainder, $base);
                } while ($limbs ? --$i : $remainder);
            }

            return $digits;
        }

        $bytes = array_values(unpack('C*', $bytes));

        while ($count = \count($bytes)) {
            $quotient = [];
            $remainder = 0;

            for ($i = 0; $i !== $count; ++$i) {
                $carry = $bytes[$i] + ($remainder << 8);
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

        if (\PHP_INT_SIZE >= 8) {
            // Reading the digits by chunks that fit in 31 bits means one multiplication of the 32-bit limbs per chunk
            for ($power = $base, $n = 1; $power * $base < 0x80000000; ++$n) {
                $power *= $base;
            }

            $limbs = [];

            for ($i = 0, $j = $count % $n ?: $n; $i < $count; $i += $j, $j = $n) {
                for ($carry = 0, $k = $i; $k < $i + $j; ++$k) {
                    $carry = $carry * $base + $map[$digits[$k]];
                }

                $multiplier = $n === $j ? $power : $base ** $j;

                foreach ($limbs as $k => $limb) {
                    $carry += $limb * $multiplier;
                    $limbs[$k] = $carry & 0xFFFFFFFF;
                    $carry >>= 32;
                }

                if ($carry) {
                    $limbs[] = $carry;
                }
            }

            return pack('N*', ...array_reverse($limbs ?: [0]));
        }

        $bytes = [];

        while ($count) {
            $quotient = [];
            $remainder = 0;

            for ($i = 0; $i !== $count; ++$i) {
                $carry = ($bytes ? $digits[$i] : $map[$digits[$i]]) + $remainder * $base;
                $digit = $carry >> 8;
                $remainder = $carry & 0xFF;

                if ($digit || $quotient) {
                    $quotient[] = $digit;
                }
            }

            $bytes[] = $remainder;
            $count = \count($digits = $quotient);
        }

        return pack('C*', ...array_reverse($bytes));
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

    /**
     * @param string $time Count of 100-nanosecond intervals since the UUID epoch 1582-10-15 00:00:00 in hexadecimal
     *
     * @return string Count of 100-nanosecond intervals since the UUID epoch 1582-10-15 00:00:00 as a numeric string
     */
    public static function hexToNumericString(string $time): string
    {
        if (\PHP_INT_SIZE >= 8) {
            $time = (string) (hexdec($time) - self::TIME_OFFSET_INT);
        } else {
            $time = str_pad(hex2bin($time), 8, "\0", \STR_PAD_LEFT);

            if (self::TIME_OFFSET_BIN <= $time) {
                $time = self::add($time, self::TIME_OFFSET_COM2);
                $time[0] = $time[0] & "\x7F";
                $time = self::toBase($time, self::BASE10);
            } else {
                $time = self::add($time, self::TIME_OFFSET_COM1);
                $time = '-'.self::toBase($time ^ "\xff\xff\xff\xff\xff\xff\xff\xff", self::BASE10);
            }
        }

        if (9 > \strlen($time)) {
            $time = '-' === $time[0] ? '-'.str_pad(substr($time, 1), 8, '0', \STR_PAD_LEFT) : str_pad($time, 8, '0', \STR_PAD_LEFT);
        }

        return $time;
    }

    /**
     * Sub-microseconds are lost since they are not handled by \DateTimeImmutable.
     *
     * @param string $time Count of 100-nanosecond intervals since the UUID epoch 1582-10-15 00:00:00 in hexadecimal
     */
    public static function hexToDateTime(string $time): \DateTimeImmutable
    {
        if (\PHP_INT_SIZE >= 8 && 0 <= $ticks = hexdec($time) - self::TIME_OFFSET_INT) {
            return \DateTimeImmutable::createFromTimestamp(intdiv($ticks, 10000000))->setMicrosecond(intdiv($ticks % 10000000, 10));
        }

        return \DateTimeImmutable::createFromFormat('U.u?', substr_replace(self::hexToNumericString($time), '.', -7, 0));
    }

    /**
     * @return string Count of 100-nanosecond intervals since the UUID epoch 1582-10-15 00:00:00 in hexadecimal
     */
    public static function dateTimeToHex(\DateTimeInterface $time): string
    {
        if (\PHP_INT_SIZE >= 8) {
            if (-self::TIME_OFFSET_INT > $time = (int) $time->format('Uu0')) {
                throw new InvalidArgumentException('The given UUID date cannot be earlier than 1582-10-15.');
            }

            return str_pad(dechex(self::TIME_OFFSET_INT + $time), 16, '0', \STR_PAD_LEFT);
        }

        $time = $time->format('Uu0');
        $negative = '-' === $time[0];
        if ($negative && self::TIME_OFFSET_INT < $time = substr($time, 1)) {
            throw new InvalidArgumentException('The given UUID date cannot be earlier than 1582-10-15.');
        }
        $time = self::fromBase($time, self::BASE10);
        $time = str_pad($time, 8, "\0", \STR_PAD_LEFT);

        if ($negative) {
            $time = self::add($time, self::TIME_OFFSET_COM1) ^ "\xff\xff\xff\xff\xff\xff\xff\xff";
        } else {
            $time = self::add($time, self::TIME_OFFSET_BIN);
        }

        return bin2hex($time);
    }
}

// @php-cs-fixer-ignore long_to_shorthand_operator To prevent false positive causing "Cannot use assign-op operators with string offsets" error
