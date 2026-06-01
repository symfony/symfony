<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Symfony\Component\Encryption\Tests\Envelope;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Encryption\Envelope\Envelope;
use Symfony\Component\Encryption\Exception\InvalidArgumentException;

final class EnvelopeTest extends TestCase
{
    public function testRawKeyRoundTrip(): void
    {
        $nonce = random_bytes(12);
        $ciphertext = random_bytes(40);

        $envelope = Envelope::deserialize(Envelope::forRawKey($nonce, $ciphertext)->serialize());

        self::assertSame(Envelope::KDF_NONE, $envelope->kdfId());
        self::assertSame($nonce, $envelope->nonce());
        self::assertSame($ciphertext, $envelope->ciphertext());
    }

    public function testArgon2idRoundTrip(): void
    {
        $salt = random_bytes(16);
        $nonce = random_bytes(12);
        $ciphertext = random_bytes(40);

        $envelope = Envelope::deserialize(Envelope::forArgon2id($salt, 3, 67108864, $nonce, $ciphertext)->serialize());

        self::assertSame(Envelope::KDF_ARGON2ID, $envelope->kdfId());
        self::assertSame($salt, $envelope->salt());
        self::assertSame(3, $envelope->argon2OpsLimit());
        self::assertSame(67108864, $envelope->argon2MemLimit());
        self::assertSame($nonce, $envelope->nonce());
        self::assertSame($ciphertext, $envelope->ciphertext());
    }

    public function testPbkdf2RoundTrip(): void
    {
        $salt = random_bytes(16);
        $nonce = random_bytes(12);
        $ciphertext = random_bytes(40);

        $envelope = Envelope::deserialize(Envelope::forPbkdf2($salt, 600000, $nonce, $ciphertext)->serialize());

        self::assertSame(Envelope::KDF_PBKDF2_SHA256, $envelope->kdfId());
        self::assertSame($salt, $envelope->salt());
        self::assertSame(600000, $envelope->pbkdf2Iterations());
        self::assertSame($nonce, $envelope->nonce());
        self::assertSame($ciphertext, $envelope->ciphertext());
    }

    public function testSerializedFormatBeginsWithMagicAndVersion(): void
    {
        $raw = Envelope::forRawKey(random_bytes(12), random_bytes(40))->serialize();

        self::assertSame('SYE', substr($raw, 0, 3));
        self::assertSame(1, \ord($raw[3])); // version
        self::assertSame(1, \ord($raw[4])); // aeadId
        self::assertSame(0, \ord($raw[5])); // kdfId none
    }

    public function testRejectsBadMagic(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Envelope::deserialize('XXX' . str_repeat("\x00", 40));
    }

    public function testRejectsUnsupportedVersion(): void
    {
        $raw = Envelope::forRawKey(random_bytes(12), random_bytes(40))->serialize();
        $raw[3] = "\x02";

        $this->expectException(InvalidArgumentException::class);

        Envelope::deserialize($raw);
    }

    public function testRejectsTruncatedEnvelope(): void
    {
        $raw = Envelope::forArgon2id(random_bytes(16), 3, 67108864, random_bytes(12), random_bytes(40))->serialize();

        $this->expectException(InvalidArgumentException::class);

        Envelope::deserialize(substr($raw, 0, 10));
    }

    public function testRejectsUnsupportedAeadId(): void
    {
        $raw = Envelope::forRawKey(random_bytes(12), random_bytes(40))->serialize();
        $raw[4] = "\xFF"; // replace aeadId with an unknown value

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported AEAD algorithm in envelope.');

        Envelope::deserialize($raw);
    }

    public function testRejectsUnsupportedKdfId(): void
    {
        $raw = Envelope::forRawKey(random_bytes(12), random_bytes(40))->serialize();
        $raw[5] = "\xFF"; // replace kdfId with an unknown value

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported KDF in envelope.');

        Envelope::deserialize($raw);
    }
}
