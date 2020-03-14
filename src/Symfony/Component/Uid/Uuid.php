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
 * @experimental in 5.1
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class Uuid implements \JsonSerializable
{
    protected const TYPE = UUID_TYPE_DEFAULT;

    protected $uuid;

    public function __construct(string $uuid)
    {
        if (static::TYPE !== uuid_type($uuid)) {
            throw new \InvalidArgumentException(sprintf('Invalid UUID%s: "%s".', static::TYPE ? 'v'.static::TYPE : '', $uuid));
        }

        $this->uuid = strtr($uuid, 'ABCDEF', 'abcdef');
    }

    /**
     * @return static
     */
    public static function fromString(string $uuid): self
    {
        if (16 === \strlen($uuid)) {
            $uuid = uuid_unparse($uuid);
        }

        if (__CLASS__ !== static::class) {
            return new static($uuid);
        }

        switch (uuid_type($uuid)) {
            case UuidV1::TYPE: return new UuidV1($uuid);
            case UuidV3::TYPE: return new UuidV3($uuid);
            case UuidV4::TYPE: return new UuidV4($uuid);
            case UuidV5::TYPE: return new UuidV5($uuid);
            case UuidV6::TYPE: return new UuidV6($uuid);
            case NullUuid::TYPE: return new NullUuid();
            case self::TYPE: return new self($uuid);
        }

        throw new \InvalidArgumentException(sprintf('Invalid UUID: "%s".', $uuid));
    }

    final public static function v1(): UuidV1
    {
        return new UuidV1();
    }

    final public static function v3(self $namespace, string $name): UuidV3
    {
        return new UuidV3(uuid_generate_md5($namespace->uuid, $name));
    }

    final public static function v4(): UuidV4
    {
        return new UuidV4();
    }

    final public static function v5(self $namespace, string $name): UuidV5
    {
        return new UuidV5(uuid_generate_sha1($namespace->uuid, $name));
    }

    final public static function v6(): UuidV6
    {
        return new UuidV6();
    }

    public static function isValid(string $uuid): bool
    {
        if (__CLASS__ === static::class) {
            return uuid_is_valid($uuid);
        }

        return static::TYPE === uuid_type($uuid);
    }

    public function toBinary(): string
    {
        return uuid_parse($this->uuid);
    }

    /**
     * Returns whether the argument is of class Uuid and contains the same value as the current instance.
     */
    public function equals($other): bool
    {
        if (!$other instanceof self) {
            return false;
        }

        return 0 === uuid_compare($this->uuid, $other->uuid);
    }

    public function compare(self $other): int
    {
        return uuid_compare($this->uuid, $other->uuid);
    }

    public function __toString(): string
    {
        return $this->uuid;
    }

    public function jsonSerialize(): string
    {
        return $this->uuid;
    }
}
