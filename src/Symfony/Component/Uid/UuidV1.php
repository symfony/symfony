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
 * Use UidFactory::uuidV1() to compute one.
 *
 * @experimental in 5.1
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class UuidV1 extends Uuid
{
    protected const TYPE = UUID_TYPE_TIME;

    // https://tools.ietf.org/html/rfc4122#section-4.1.4
    // 0x01b21dd213814000 is the number of 100-ns intervals between the
    // UUID epoch 1582-10-15 00:00:00 and the Unix epoch 1970-01-01 00:00:00.
    private const TIME_OFFSET_INT = 0x01b21dd213814000;
    private const TIME_OFFSET_BIN = "\x01\xb2\x1d\xd2\x13\x81\x40\x00";
    private const TIME_OFFSET_COM = "\xfe\x4d\xe2\x2d\xec\x7e\xc0\x00";

    public function getTime(): float
    {
        $time = '0'.substr($this->uid, 15, 3).substr($this->uid, 9, 4).substr($this->uid, 0, 8);

        if (\PHP_INT_SIZE >= 8) {
            return (hexdec($time) - self::TIME_OFFSET_INT) / 10000000;
        }

        $time = str_pad(hex2bin($time), 8, "\0", STR_PAD_LEFT);
        $time = BinaryUtil::add($time, self::TIME_OFFSET_COM);
        $time[0] = $time[0] & "\x7F";

        return BinaryUtil::toBase($time, BinaryUtil::BASE10) / 10000000;
    }

    public function getNode(): string
    {
        return uuid_mac($this->uid);
    }

    /**
     * @internal
     */
    public static function generate(callable $entropySource = null, callable $timeSource = null): string
    {
        if (!$timeSource || !$entropySource) {
            $uuid = uuid_create(self::TYPE);
        } else {
            $uuid = '00000000-0000-0000-0000-000000000000';
        }

        if ($timeSource) {
            if (!is_numeric($time = $timeSource()) || 8 > \strlen($time)) {
                throw new \LogicException('The time source must return the time as a decimal string of tenth of microseconds.');
            }

            if (\PHP_INT_SIZE >= 8) {
                $time = str_pad(dechex($time + self::TIME_OFFSET_INT), 16, '0', STR_PAD_LEFT);
            } else {
                $time = str_pad(BinaryUtil::fromBase($time, BinaryUtil::BASE10), 8, "\0", STR_PAD_LEFT);
                $time = BinaryUtil::add($time, self::TIME_OFFSET_BIN);
                $time = bin2hex($time);
            }

            $uuid = substr($time, 8).'-'.substr($time, 4, 4).'-1'.substr($time, 1, 3).substr($uuid, 18);
        }

        if ($entropySource) {
            if (!\is_string($entropy = $entropySource(8)) || 8 !== \strlen($entropy)) {
                throw new \LogicException('The entropy source must return 8 bytes.');
            }

            $entropy[0] = $entropy[0] & "\x3F" | "\x80";
            $entropy = bin2hex($entropy);
            $uuid = substr($uuid, 0, 19).substr($entropy, 0, 4).'-'.substr($entropy, 4);
        }

        return $uuid;
    }
}
