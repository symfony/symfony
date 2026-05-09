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

use Symfony\Component\Uid\Exception\InvalidArgumentException;
use Symfony\Component\Uid\Exception\LogicException;

/**
 * Converts between UUIDv7 and UUIDv4 using SipHash-2-4 timestamp masking.
 *
 * This allows storing time-ordered UUIDv7 in databases while emitting
 * UUIDv4-looking identifiers at API boundaries, hiding timing information.
 *
 * The 48-bit timestamp of a UUIDv7 is XOR-masked with a keyed SipHash-2-4
 * digest derived from the UUID's own random bits, producing a valid UUIDv4.
 * The transformation is reversible with the same key.
 *
 * @see https://github.com/n2p5/uuid47
 */
class Uuid47Transformer
{
    private string $secret;

    /**
     * @param string $secret A binary secret of at least 16 bytes
     */
    public function __construct(
        #[\SensitiveParameter]
        string $secret,
    ) {
        if (!\extension_loaded('sodium')) {
            throw new LogicException('The "sodium" PHP extension is required to use Uuid47.');
        }
        if (16 > \strlen($secret)) {
            throw new InvalidArgumentException('The secret must be at least 16 bytes.');
        }

        $this->secret = 16 === \strlen($secret) ? $secret : substr(hash('sha256', $secret, true), 0, 16);
    }

    /**
     * Encodes a UUIDv7 into a UUIDv4-looking UUID.
     */
    public function encode(UuidV7 $uuid): UuidV4
    {
        return new UuidV4(self::transform($uuid->toRfc4122(), $this->secret, '4'));
    }

    /**
     * Decodes a UUIDv4-looking UUID back into the original UUIDv7.
     */
    public function decode(UuidV4 $uuid): UuidV7
    {
        return new UuidV7(self::transform($uuid->toRfc4122(), $this->secret, '7'));
    }

    private static function transform(string $rfc4122, string $secret, string $targetVersion): string
    {
        $bytes = hex2bin(str_replace('-', '', $rfc4122));

        // Build 10-byte SipHash input from the 74 random bits (version and variant masked out).
        // These bits are identical in both the UUIDv7 and UUIDv4 representations,
        // ensuring the same digest is produced in both directions.
        $sipInput = ($bytes[6] & "\x0F").$bytes[7].($bytes[8] & "\x3F").substr($bytes, 9, 7);

        // sodium_crypto_shorthash is SipHash-2-4, returns 8 bytes in little-endian order.
        // XOR the 48-bit timestamp (first 6 big-endian bytes) with the low 48 bits of the hash.
        // The hash is little-endian (LSB first) while the timestamp is big-endian (MSB first),
        // so we XOR in reverse byte order.
        $hash = sodium_crypto_shorthash($sipInput, $secret);

        $bytes[0] = $bytes[0] ^ $hash[5];
        $bytes[1] = $bytes[1] ^ $hash[4];
        $bytes[2] = $bytes[2] ^ $hash[3];
        $bytes[3] = $bytes[3] ^ $hash[2];
        $bytes[4] = $bytes[4] ^ $hash[1];
        $bytes[5] = $bytes[5] ^ $hash[0];
        $hex = bin2hex($bytes);

        return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-'.$targetVersion.substr($rfc4122, 15);
    }
}
