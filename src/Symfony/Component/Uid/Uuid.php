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
class Uuid extends AbstractUid
{
    protected const TYPE = UUID_TYPE_DEFAULT;

    public function __construct(string $uuid)
    {
        $type = uuid_type($uuid);

        if (false === $type || UUID_TYPE_INVALID === $type || (static::TYPE ?: $type) !== $type) {
            throw new \InvalidArgumentException(sprintf('Invalid UUID%s: "%s".', static::TYPE ? 'v'.static::TYPE : '', $uuid));
        }

        $this->uid = strtr($uuid, 'ABCDEF', 'abcdef');
    }

    /**
     * @return static
     */
    public static function fromString(string $uuid): parent
    {
        if (22 === \strlen($uuid) && 22 === strspn($uuid, BinaryUtil::BASE58[''])) {
            $uuid = BinaryUtil::fromBase($uuid, BinaryUtil::BASE58);
        }

        if (16 === \strlen($uuid)) {
            // don't use uuid_unparse(), it's slower
            $uuid = bin2hex($uuid);
            $uuid = substr_replace($uuid, '-', 8, 0);
            $uuid = substr_replace($uuid, '-', 13, 0);
            $uuid = substr_replace($uuid, '-', 18, 0);
            $uuid = substr_replace($uuid, '-', 23, 0);
        } elseif (26 === \strlen($uuid) && Ulid::isValid($uuid)) {
            $uuid = (new Ulid($uuid))->toRfc4122();
        }

        if (__CLASS__ !== static::class || 36 !== \strlen($uuid)) {
            return new static($uuid);
        }

        switch (uuid_type($uuid)) {
            case UuidV1::TYPE: return new UuidV1($uuid);
            case UuidV3::TYPE: return new UuidV3($uuid);
            case UuidV4::TYPE: return new UuidV4($uuid);
            case UuidV5::TYPE: return new UuidV5($uuid);
            case UuidV6::TYPE: return new UuidV6($uuid);
            case NilUuid::TYPE: return new NilUuid();
        }

        return new self($uuid);
    }

    final public static function v1(): UuidV1
    {
        return new UuidV1();
    }

    final public static function v3(self $namespace, string $name): UuidV3
    {
        // don't use uuid_generate_md5(), some versions are buggy
        $uuid = md5(hex2bin(str_replace('-', '', $namespace->uid)).$name, true);

        return new UuidV3(self::format($uuid, '-3'));
    }

    final public static function v4(): UuidV4
    {
        return new UuidV4();
    }

    final public static function v5(self $namespace, string $name): UuidV5
    {
        // don't use uuid_generate_sha1(), some versions are buggy
        $uuid = substr(sha1(hex2bin(str_replace('-', '', $namespace->uid)).$name, true), 0, 16);

        return new UuidV5(self::format($uuid, '-5'));
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
        return uuid_parse($this->uid);
    }

    public function toRfc4122(): string
    {
        return $this->uid;
    }

    public function compare(parent $other): int
    {
        if (false !== $cmp = uuid_compare($this->uid, $other->uid)) {
            return $cmp;
        }

        return parent::compare($other);
    }

    private static function format(string $uuid, string $version): string
    {
        $uuid[8] = $uuid[8] & "\x3F" | "\x80";
        $uuid = substr_replace(bin2hex($uuid), '-', 8, 0);
        $uuid = substr_replace($uuid, $version, 13, 1);
        $uuid = substr_replace($uuid, '-', 18, 0);

        return substr_replace($uuid, '-', 23, 0);
    }
}
