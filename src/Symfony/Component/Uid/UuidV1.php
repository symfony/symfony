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
class UuidV1 extends Uuid
{
    protected const TYPE = 1;

    public function __construct(string $uuid = null)
    {
        if (null === $uuid) {
            $this->uid = uuid_create(static::TYPE);
        } else {
            parent::__construct($uuid);
        }
    }

    public function getDateTime(): \DateTimeImmutable
    {
        return BinaryUtil::hexToDateTime('0'.substr($this->uid, 15, 3).substr($this->uid, 9, 4).substr($this->uid, 0, 8));
    }

    public function getNode(): string
    {
        return uuid_mac($this->uid);
    }

    public static function generate(\DateTimeInterface $time = null, Uuid $node = null): string
    {
        $uuid = uuid_create(static::TYPE);

        if (null !== $time) {
            $time = BinaryUtil::dateTimeToHex($time);
            $uuid = substr($time, 8).'-'.substr($time, 4, 4).'-1'.substr($time, 1, 3).substr($uuid, 18);
        }

        if ($node) {
            $uuid = substr($uuid, 0, 24).substr($node->uid, 24);
        }

        return $uuid;
    }
}
