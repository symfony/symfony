<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Encryption\Envelope;

use Symfony\Component\Encryption\Engine\SymmetricEngineInterface;
use Symfony\Component\Encryption\Exception\InvalidArgumentException;

/**
 * Self-describing, versioned frame for a symmetric ciphertext.
 *
 * Carries the AEAD algorithm id, the optional key-derivation parameters, the
 * nonce, and the ciphertext (with appended tag), so any environment can read a
 * value produced anywhere. Binary; the public cipher base64-encodes it.
 *
 * @author David Gebler <me@davegebler.com>
 */
final class Envelope
{
    public const KDF_NONE = 0;
    public const KDF_ARGON2ID = 1;
    public const KDF_PBKDF2_SHA256 = 2;

    public const SALT_BYTES = 16;
    public const AEAD_CHACHA20_POLY1305_IETF = 1;

    private const MAGIC = 'SYE';
    private const VERSION = 1;

    private function __construct(
        private readonly int $aeadId,
        private readonly int $kdfId,
        private readonly string $salt,
        private readonly int $argon2OpsLimit,
        private readonly int $argon2MemLimit,
        private readonly int $pbkdf2Iterations,
        private readonly string $nonce,
        private readonly string $ciphertext,
    ) {
    }

    public static function forRawKey(string $nonce, string $ciphertext): self
    {
        return new self(self::AEAD_CHACHA20_POLY1305_IETF, self::KDF_NONE, '', 0, 0, 0, $nonce, $ciphertext);
    }

    public static function forArgon2id(string $salt, int $opsLimit, int $memLimit, string $nonce, string $ciphertext): self
    {
        return new self(self::AEAD_CHACHA20_POLY1305_IETF, self::KDF_ARGON2ID, $salt, $opsLimit, $memLimit, 0, $nonce, $ciphertext);
    }

    public static function forPbkdf2(string $salt, int $iterations, string $nonce, string $ciphertext): self
    {
        return new self(self::AEAD_CHACHA20_POLY1305_IETF, self::KDF_PBKDF2_SHA256, $salt, 0, 0, $iterations, $nonce, $ciphertext);
    }

    public function serialize(): string
    {
        $header = self::MAGIC
            .pack('C', self::VERSION)
            .pack('C', $this->aeadId)
            .pack('C', $this->kdfId);

        $kdf = match ($this->kdfId) {
            self::KDF_NONE => '',
            self::KDF_ARGON2ID => $this->salt.pack('J', $this->argon2OpsLimit).pack('J', $this->argon2MemLimit),
            self::KDF_PBKDF2_SHA256 => $this->salt.pack('N', $this->pbkdf2Iterations),
            default => throw new \LogicException('Unknown KDF id.'),
        };

        return $header.$kdf.$this->nonce.$this->ciphertext;
    }

    public static function deserialize(string $raw): self
    {
        $length = \strlen($raw);
        if ($length < 6 || self::MAGIC !== substr($raw, 0, 3)) {
            throw new InvalidArgumentException('Malformed encryption envelope.');
        }
        if (self::VERSION !== \ord($raw[3])) {
            throw new InvalidArgumentException('Unsupported envelope version.');
        }

        $aeadId = \ord($raw[4]);
        if (self::AEAD_CHACHA20_POLY1305_IETF !== $aeadId) {
            throw new InvalidArgumentException('Unsupported AEAD algorithm in envelope.');
        }

        $kdfId = \ord($raw[5]);
        $offset = 6;
        $salt = '';
        $ops = 0;
        $mem = 0;
        $iterations = 0;

        if (self::KDF_ARGON2ID === $kdfId) {
            if ($length < $offset + self::SALT_BYTES + 16) {
                throw new InvalidArgumentException('Malformed encryption envelope.');
            }
            $salt = substr($raw, $offset, self::SALT_BYTES);
            $offset += self::SALT_BYTES;
            $ops = self::readUInt64(substr($raw, $offset, 8));
            $offset += 8;
            $mem = self::readUInt64(substr($raw, $offset, 8));
            $offset += 8;
        } elseif (self::KDF_PBKDF2_SHA256 === $kdfId) {
            if ($length < $offset + self::SALT_BYTES + 4) {
                throw new InvalidArgumentException('Malformed encryption envelope.');
            }
            $salt = substr($raw, $offset, self::SALT_BYTES);
            $offset += self::SALT_BYTES;
            $iterations = self::readUInt32(substr($raw, $offset, 4));
            $offset += 4;
        } elseif (self::KDF_NONE !== $kdfId) {
            throw new InvalidArgumentException('Unsupported KDF in envelope.');
        }

        if ($length < $offset + SymmetricEngineInterface::NONCE_BYTES + SymmetricEngineInterface::TAG_BYTES) {
            throw new InvalidArgumentException('Malformed encryption envelope.');
        }

        $nonce = substr($raw, $offset, SymmetricEngineInterface::NONCE_BYTES);
        $offset += SymmetricEngineInterface::NONCE_BYTES;
        $ciphertext = substr($raw, $offset);

        return new self($aeadId, $kdfId, $salt, $ops, $mem, $iterations, $nonce, $ciphertext);
    }

    public function kdfId(): int
    {
        return $this->kdfId;
    }

    public function salt(): string
    {
        return $this->salt;
    }

    public function argon2OpsLimit(): int
    {
        return $this->argon2OpsLimit;
    }

    public function argon2MemLimit(): int
    {
        return $this->argon2MemLimit;
    }

    public function pbkdf2Iterations(): int
    {
        return $this->pbkdf2Iterations;
    }

    public function nonce(): string
    {
        return $this->nonce;
    }

    public function ciphertext(): string
    {
        return $this->ciphertext;
    }

    private static function readUInt64(string $bytes): int
    {
        /** @var array{1: int} $unpacked */
        $unpacked = unpack('J', $bytes);

        return $unpacked[1];
    }

    private static function readUInt32(string $bytes): int
    {
        /** @var array{1: int} $unpacked */
        $unpacked = unpack('N', $bytes);

        return $unpacked[1];
    }
}
