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
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 *
 * @see https://tools.ietf.org/html/rfc4122#appendix-C for details about namespaces
 */
class Uuid extends AbstractUid
{
    public const NAMESPACE_DNS = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';
    public const NAMESPACE_URL = '6ba7b811-9dad-11d1-80b4-00c04fd430c8';
    public const NAMESPACE_OID = '6ba7b812-9dad-11d1-80b4-00c04fd430c8';
    public const NAMESPACE_X500 = '6ba7b814-9dad-11d1-80b4-00c04fd430c8';

    protected const TYPE = 0;
    protected const NIL = '00000000-0000-0000-0000-000000000000';

    public function __construct(string $uuid, bool $checkVariant = false)
    {
        $type = preg_match('{^[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}$}Di', $uuid) ? (int) $uuid[14] : false;

        if (false === $type || (static::TYPE ?: $type) !== $type) {
            throw new \InvalidArgumentException(sprintf('Invalid UUID%s: "%s".', static::TYPE ? 'v'.static::TYPE : '', $uuid));
        }

        $this->uid = strtolower($uuid);

        if ($checkVariant && !\in_array($this->uid[19], ['8', '9', 'a', 'b'], true)) {
            throw new \InvalidArgumentException(sprintf('Invalid UUID%s: "%s".', static::TYPE ? 'v'.static::TYPE : '', $uuid));
        }
    }

    public static function fromString(string $uuid): static
    {
        if (22 === \strlen($uuid) && 22 === strspn($uuid, BinaryUtil::BASE58[''])) {
            $uuid = str_pad(BinaryUtil::fromBase($uuid, BinaryUtil::BASE58), 16, "\0", \STR_PAD_LEFT);
        }

        if (16 === \strlen($uuid)) {
            // don't use uuid_unparse(), it's slower
            $uuid = bin2hex($uuid);
            $uuid = substr_replace($uuid, '-', 8, 0);
            $uuid = substr_replace($uuid, '-', 13, 0);
            $uuid = substr_replace($uuid, '-', 18, 0);
            $uuid = substr_replace($uuid, '-', 23, 0);
        } elseif (26 === \strlen($uuid) && Ulid::isValid($uuid)) {
            $ulid = new Ulid('00000000000000000000000000');
            $ulid->uid = strtoupper($uuid);
            $uuid = $ulid->toRfc4122();
        }

        if (__CLASS__ !== static::class || 36 !== \strlen($uuid)) {
            return new static($uuid);
        }

        if (self::NIL === $uuid) {
            return new NilUuid();
        }

        if (\in_array($uuid[19], ['8', '9', 'a', 'b', 'A', 'B'], true)) {
            switch ($uuid[14]) {
                case UuidV1::TYPE: return new UuidV1($uuid);
                case UuidV3::TYPE: return new UuidV3($uuid);
                case UuidV4::TYPE: return new UuidV4($uuid);
                case UuidV5::TYPE: return new UuidV5($uuid);
                case UuidV6::TYPE: return new UuidV6($uuid);
            }
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
        if (!preg_match('{^[0-9a-f]{8}(?:-[0-9a-f]{4}){2}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$}Di', $uuid)) {
            return false;
        }

        return __CLASS__ === static::class || static::TYPE === (int) $uuid[14];
    }

    public function toBinary(): string
    {
        return uuid_parse($this->uid);
    }

    public function toRfc4122(): string
    {
        return $this->uid;
    }

    public function compare(AbstractUid $other): int
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
