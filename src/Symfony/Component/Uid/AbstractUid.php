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
 * @author Nicolas Grekas <p@tchwork.com>
 */
abstract class AbstractUid implements \JsonSerializable
{
    /**
     * The identifier in its canonic representation.
     */
    protected $uid;

    /**
     * Whether the passed value is valid for the constructor of the current class.
     */
    abstract public static function isValid(string $uid): bool;

    /**
     * Creates an AbstractUid from an identifier represented in any of the supported formats.
     *
     * @return static
     *
     * @throws \InvalidArgumentException When the passed value is not valid
     */
    abstract public static function fromString(string $uid): self;

    /**
     * @return static
     *
     * @throws \InvalidArgumentException When the passed value is not valid
     */
    public static function fromBinary(string $uid): self
    {
        if (16 !== \strlen($uid)) {
            throw new \InvalidArgumentException('Invalid binary uid provided.');
        }

        return static::fromString($uid);
    }

    /**
     * @return static
     *
     * @throws \InvalidArgumentException When the passed value is not valid
     */
    public static function fromBase58(string $uid): self
    {
        if (22 !== \strlen($uid)) {
            throw new \InvalidArgumentException('Invalid base-58 uid provided.');
        }

        return static::fromString($uid);
    }

    /**
     * @return static
     *
     * @throws \InvalidArgumentException When the passed value is not valid
     */
    public static function fromBase32(string $uid): self
    {
        if (26 !== \strlen($uid)) {
            throw new \InvalidArgumentException('Invalid base-32 uid provided.');
        }

        return static::fromString($uid);
    }

    /**
     * @param string $uid A valid RFC 9562/4122 uid
     *
     * @return static
     *
     * @throws \InvalidArgumentException When the passed value is not valid
     */
    public static function fromRfc4122(string $uid): self
    {
        if (36 !== \strlen($uid)) {
            throw new \InvalidArgumentException('Invalid RFC4122 uid provided.');
        }

        return static::fromString($uid);
    }

    /**
     * Returns the identifier as a raw binary string.
     */
    abstract public function toBinary(): string;

    /**
     * Returns the identifier as a base58 case sensitive string.
     */
    public function toBase58(): string
    {
        return strtr(sprintf('%022s', BinaryUtil::toBase($this->toBinary(), BinaryUtil::BASE58)), '0', '1');
    }

    /**
     * Returns the identifier as a base32 case insensitive string.
     */
    public function toBase32(): string
    {
        $uid = bin2hex($this->toBinary());
        $uid = sprintf('%02s%04s%04s%04s%04s%04s%04s',
            base_convert(substr($uid, 0, 2), 16, 32),
            base_convert(substr($uid, 2, 5), 16, 32),
            base_convert(substr($uid, 7, 5), 16, 32),
            base_convert(substr($uid, 12, 5), 16, 32),
            base_convert(substr($uid, 17, 5), 16, 32),
            base_convert(substr($uid, 22, 5), 16, 32),
            base_convert(substr($uid, 27, 5), 16, 32)
        );

        return strtr($uid, 'abcdefghijklmnopqrstuv', 'ABCDEFGHJKMNPQRSTVWXYZ');
    }

    /**
     * Returns the identifier as a RFC 9562/4122 case insensitive string.
     */
    public function toRfc4122(): string
    {
        // don't use uuid_unparse(), it's slower
        $uuid = bin2hex($this->toBinary());
        $uuid = substr_replace($uuid, '-', 8, 0);
        $uuid = substr_replace($uuid, '-', 13, 0);
        $uuid = substr_replace($uuid, '-', 18, 0);

        return substr_replace($uuid, '-', 23, 0);
    }

    /**
     * Returns whether the argument is an AbstractUid and contains the same value as the current instance.
     */
    public function equals($other): bool
    {
        if (!$other instanceof self) {
            return false;
        }

        return $this->uid === $other->uid;
    }

    public function compare(self $other): int
    {
        return (\strlen($this->uid) - \strlen($other->uid)) ?: ($this->uid <=> $other->uid);
    }

    public function __toString(): string
    {
        return $this->uid;
    }

    public function jsonSerialize(): string
    {
        return $this->uid;
    }
}
