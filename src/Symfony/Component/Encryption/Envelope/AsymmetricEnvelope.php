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

namespace Symfony\Component\Encryption\Envelope;

use Symfony\Component\Encryption\Exception\InvalidArgumentException;

/**
 * Self-describing, versioned frame for an asymmetric ciphertext.
 *
 * Binary; the public cipher base64-encodes it. Layout:
 * magic("SYA") | version | algorithmId | mode | [nonce(24) if authenticated] | body
 *
 * @author David Gebler <me@davegebler.com>
 */
final class AsymmetricEnvelope
{
    public const MODE_ANONYMOUS = 1;
    public const MODE_AUTHENTICATED = 2;

    public const ALGORITHM_X25519 = 1;
    public const ALGORITHM_RSA = 2;

    private const MAGIC = 'SYA';
    private const VERSION = 1;
    private const AUTH_NONCE_BYTES = 24;

    /** @var array<string, int> */
    private const ALGORITHM_IDS = ['x25519' => self::ALGORITHM_X25519, 'rsa' => self::ALGORITHM_RSA];

    private function __construct(
        private readonly string $algorithm,
        private readonly int $mode,
        private readonly string $nonce,
        private readonly string $body,
    ) {
    }

    public static function anonymous(string $algorithm, string $body): self
    {
        return new self($algorithm, self::MODE_ANONYMOUS, '', $body);
    }

    public static function authenticated(string $algorithm, string $nonce, string $body): self
    {
        return new self($algorithm, self::MODE_AUTHENTICATED, $nonce, $body);
    }

    public function serialize(): string
    {
        if (!isset(self::ALGORITHM_IDS[$this->algorithm])) {
            throw new InvalidArgumentException(\sprintf('Unsupported asymmetric algorithm "%s".', $this->algorithm));
        }

        $header = self::MAGIC
            . pack('C', self::VERSION)
            . pack('C', self::ALGORITHM_IDS[$this->algorithm])
            . pack('C', $this->mode);

        return $header . (self::MODE_AUTHENTICATED === $this->mode ? $this->nonce : '') . $this->body;
    }

    public static function deserialize(string $raw): self
    {
        if (\strlen($raw) < 6 || self::MAGIC !== substr($raw, 0, 3)) {
            throw new InvalidArgumentException('Malformed asymmetric envelope.');
        }
        if (self::VERSION !== \ord($raw[3])) {
            throw new InvalidArgumentException('Unsupported asymmetric envelope version.');
        }

        $algorithm = array_search(\ord($raw[4]), self::ALGORITHM_IDS, true);
        if (false === $algorithm) {
            throw new InvalidArgumentException('Unsupported algorithm in asymmetric envelope.');
        }

        $mode = \ord($raw[5]);
        $offset = 6;
        $nonce = '';

        if (self::MODE_AUTHENTICATED === $mode) {
            if (\strlen($raw) < $offset + self::AUTH_NONCE_BYTES) {
                throw new InvalidArgumentException('Malformed asymmetric envelope.');
            }
            $nonce = substr($raw, $offset, self::AUTH_NONCE_BYTES);
            $offset += self::AUTH_NONCE_BYTES;
        } elseif (self::MODE_ANONYMOUS !== $mode) {
            throw new InvalidArgumentException('Unsupported mode in asymmetric envelope.');
        }

        return new self($algorithm, $mode, $nonce, substr($raw, $offset));
    }

    public function algorithm(): string
    {
        return $this->algorithm;
    }

    public function mode(): int
    {
        return $this->mode;
    }

    public function nonce(): string
    {
        return $this->nonce;
    }

    public function body(): string
    {
        return $this->body;
    }
}
