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
    private const TIME_OFFSET_COM = "\xfe\x4d\xe2\x2d\xec\x7e\xc0\x00";

    public function __construct(string $uuid = null)
    {
        if (null === $uuid) {
            $this->uid = uuid_create(static::TYPE);
        } else {
            parent::__construct($uuid);
        }
    }

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
}
