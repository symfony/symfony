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
 * A factory to create several kind of unique identifiers.
 *
 * @experimental in 5.1
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class UidFactory
{
    private $entropySource;
    private $timeSource;
    private $randomSource;

    public function __construct(callable $entropySource = null, callable $timeSource = null, callable $randomSource = null)
    {
        $this->entropySource = $entropySource;
        $this->timeSource = $timeSource;
        $this->randomSource = $randomSource;
    }

    public function ulid(): Ulid
    {
        return new Ulid(Ulid::generate($this->timeSource, $this->randomSource));
    }

    public function uuidV1(): UuidV1
    {
        return new UuidV1(UuidV1::generate($this->entropySource, $this->timeSource));
    }

    public function uuidV3(Uuid $namespace, string $name): UuidV3
    {
        return new UuidV3(uuid_generate_md5($namespace, $name));
    }

    public function uuidV4(): UuidV4
    {
        if (!$this->randomSource) {
            $uuid = random_bytes(16);
        } elseif (!\is_string($uuid = ($this->randomSource)(16)) || 16 !== \strlen($uuid)) {
            throw new \LogicException('The random source must return 8 bytes.');
        }

        $uuid[6] = $uuid[6] & "\x0F" | "\x40";
        $uuid[8] = $uuid[8] & "\x3F" | "\x80";
        $uuid = bin2hex($uuid);
        $uuid = substr($uuid, 0, 8).'-'.substr($uuid, 8, 4).'-'.substr($uuid, 12, 4).'-'.substr($uuid, 16, 4).'-'.substr($uuid, 20, 12);

        return new UuidV4($uuid);
    }

    public function uuidV5(Uuid $namespace, string $name): UuidV5
    {
        return new UuidV5(uuid_generate_sha1($namespace, $name));
    }

    public function uuidV6(): UuidV6
    {
        $uuid = UuidV1::generate($this->entropySource, $this->timeSource);
        $uuid = substr($uuid, 15, 3).substr($uuid, 9, 4).$uuid[0].'-'.substr($uuid, 1, 4).'-6'.substr($uuid, 5, 3).substr($uuid, 18);

        return new UuidV6($uuid);
    }
}
