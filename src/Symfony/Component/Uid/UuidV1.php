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
 * A v1 UUID contains a 60-bit timestamp and 62 extra unique bits.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class UuidV1 extends Uuid implements TimeBasedUidInterface
{
    protected const TYPE = 1;

    private static int|false $pid = 0;
    private static int|string $time = 0;
    private static string $seq;
    private static string $clockSeq;
    private static string $node;

    public function __construct(?string $uuid = null)
    {
        if (null === $uuid) {
            $this->uid = static::generate();
        } else {
            parent::__construct($uuid, true);
        }
    }

    public function getDateTime(): \DateTimeImmutable
    {
        return BinaryUtil::hexToDateTime('0'.substr($this->uid, 15, 3).substr($this->uid, 9, 4).substr($this->uid, 0, 8));
    }

    public function getNode(): string
    {
        return substr($this->uid, -12);
    }

    public function toV6(): UuidV6
    {
        $uuid = $this->uid;

        return new UuidV6(substr($uuid, 15, 3).substr($uuid, 9, 4).$uuid[0].'-'.substr($uuid, 1, 4).'-6'.substr($uuid, 5, 3).substr($uuid, 18, 6).substr($uuid, 24));
    }

    public function toV7(): UuidV7
    {
        return $this->toV6()->toV7();
    }

    public static function generate(?\DateTimeInterface $time = null, ?Uuid $node = null): string
    {
        $tick = null;

        if (\PHP_INT_SIZE >= 8 && !$time) {
            // microtime(true) is faster than microtime(false), and precise enough to give the exact microsecond
            $tick = 10 * (int) (microtime(true) * 1000000 + .5) + BinaryUtil::TIME_OFFSET_INT;
        }

        // Forking takes much longer than 10µs: checking the pid only when the clock moved further than that since the previous UUID is enough to detect forks
        if ((null === $tick || 100 < abs($tick - self::$time)) && self::$pid !== $pid = getmypid()) {
            // Each process draws its own clock sequence and node, forks included, so that they never collide.
            // UUIDs generated for a given time use another clock sequence than the ones generated for the current time.
            // The multicast bit of the node tells that it is not a MAC address.
            self::$pid = $pid;
            self::$seq = \sprintf('%04x', random_int(0, 0x3FFF) | 0x8000);

            do {
                self::$clockSeq = \sprintf('%04x', random_int(0, 0x3FFF) | 0x8000);
            } while (self::$seq === self::$clockSeq);

            self::$node = \sprintf('%06x%06x', random_int(0, 0xFFFFFF) | 0x010000, random_int(0, 0xFFFFFF));
        }

        if ($time) {
            $time = BinaryUtil::dateTimeToHex($time);
            $seq = $node ? substr($node->uid, 19, 4) : self::$clockSeq;
        } else {
            $seq = self::$seq;

            // The timestamp moves 100ns past the previous one when the clock did not move, or when it went backwards
            if (\PHP_INT_SIZE >= 8) {
                self::$time = $tick = $tick > self::$time ? $tick : self::$time + 1;
                $time = \sprintf('%016x', $tick);
            } else {
                $time = microtime(false);
                $time = BinaryUtil::dateTimeToHex(\DateTimeImmutable::createFromFormat('U.u', substr($time, 11).'.'.substr($time, 2, 6)));

                if (0 >= strcmp($time, self::$time)) {
                    $time = bin2hex(BinaryUtil::add(hex2bin(self::$time), "\0\0\0\0\0\0\0\1"));
                }
                self::$time = $time;
            }
        }

        return substr($time, 8).'-'.substr($time, 4, 4).'-1'.substr($time, 1, 3).'-'.$seq.'-'.($node ? substr($node->uid, 24) : self::$node);
    }
}
