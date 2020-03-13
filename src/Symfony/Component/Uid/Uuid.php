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
    public const TYPE_1 = UUID_TYPE_TIME;
    public const TYPE_3 = UUID_TYPE_MD5;
    public const TYPE_4 = UUID_TYPE_RANDOM;
    public const TYPE_5 = UUID_TYPE_SHA1;

    public const VARIANT_NCS = UUID_VARIANT_NCS;
    public const VARIANT_DCE = UUID_VARIANT_DCE;
    public const VARIANT_MICROSOFT = UUID_VARIANT_MICROSOFT;
    public const VARIANT_OTHER = UUID_VARIANT_OTHER;

    private $uuid;

    public function __construct(string $uuid = null)
    {
        if (null === $uuid) {
            $this->uuid = uuid_create(self::TYPE_4);

            return;
        }

        if (!uuid_is_valid($uuid)) {
            throw new \InvalidArgumentException(sprintf('Invalid UUID: "%s".', $uuid));
        }

        $this->uuid = strtr($uuid, 'ABCDEF', 'abcdef');
    }

    public static function v1(): self
    {
        return new self(uuid_create(self::TYPE_1));
    }

    public static function v3(self $uuidNamespace, string $name): self
    {
        return new self(uuid_generate_md5($uuidNamespace->uuid, $name));
    }

    public static function v4(): self
    {
        return new self(uuid_create(self::TYPE_4));
    }

    public static function v5(self $uuidNamespace, string $name): self
    {
        return new self(uuid_generate_sha1($uuidNamespace->uuid, $name));
    }

    public static function fromBinary(string $uuidAsBinary): self
    {
        return new self(uuid_unparse($uuidAsBinary));
    }

    public static function isValid(string $uuid): bool
    {
        return uuid_is_valid($uuid);
    }

    public function toBinary(): string
    {
        return uuid_parse($this->uuid);
    }

    public function isNull(): bool
    {
        return uuid_is_null($this->uuid);
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

    public function getType(): int
    {
        return uuid_type($this->uuid);
    }

    public function getVariant(): int
    {
        return uuid_variant($this->uuid);
    }

    public function getTime(): int
    {
        if (self::TYPE_1 !== $t = uuid_type($this->uuid)) {
            throw new \LogicException("UUID of type $t doesn't contain a time.");
        }

        return uuid_time($this->uuid);
    }

    public function getMac(): string
    {
        if (self::TYPE_1 !== $t = uuid_type($this->uuid)) {
            throw new \LogicException("UUID of type $t doesn't contain a MAC.");
        }

        return uuid_mac($this->uuid);
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
