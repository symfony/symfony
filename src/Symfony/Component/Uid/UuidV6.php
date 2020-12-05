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
 * A v6 UUID is lexicographically sortable and contains a 60-bit timestamp and 62 extra unique bits.
 *
 * Unlike UUIDv1, this implementation of UUIDv6 doesn't leak the MAC address of the host.
 *
 * @experimental in 5.2
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class UuidV6 extends Uuid
{
    protected const TYPE = 6;

    private static $seed;

    public function __construct(string $uuid = null)
    {
        if (null === $uuid) {
            $uuid = uuid_create(\UUID_TYPE_TIME);
            $this->uid = substr($uuid, 15, 3).substr($uuid, 9, 4).$uuid[0].'-'.substr($uuid, 1, 4).'-6'.substr($uuid, 5, 3).substr($uuid, 18, 6);

            // uuid_create() returns a stable "node" that can leak the MAC of the host, but
            // UUIDv6 prefers a truly random number here, let's XOR both to preserve the entropy

            if (null === self::$seed) {
                self::$seed = [random_int(0, 0xffffff), random_int(0, 0xffffff)];
            }

            $node = unpack('N2', hex2bin('00'.substr($uuid, 24, 6)).hex2bin('00'.substr($uuid, 30)));

            $this->uid .= sprintf('%06x%06x',
                (self::$seed[0] ^ $node[1]) | 0x010000,
                self::$seed[1] ^ $node[2]
            );
        } else {
            parent::__construct($uuid);
        }
    }

    public function getTime(): float
    {
        $time = '0'.substr($this->uid, 0, 8).substr($this->uid, 9, 4).substr($this->uid, 15, 3);

        return BinaryUtil::timeToFloat($time);
    }

    public function getNode(): string
    {
        return substr($this->uid, 24);
    }
}
