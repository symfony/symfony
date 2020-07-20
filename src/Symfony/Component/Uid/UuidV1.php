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

        return BinaryUtil::timeToFloat($time);
    }

    public function getNode(): string
    {
        return uuid_mac($this->uid);
    }
}
