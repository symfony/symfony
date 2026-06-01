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

namespace Symfony\Component\Encryption\Key;

use Symfony\Component\Encryption\Encoding;
use Symfony\Component\Encryption\Engine\SymmetricEngineInterface;
use Symfony\Component\Encryption\Exception\InvalidArgumentException;
use Symfony\Component\Encryption\Exception\InvalidKeyException;

/**
 * A 32-byte symmetric key for ChaCha20-Poly1305 (IETF).
 *
 * @author David Gebler <me@davegebler.com>
 *
 * @api
 */
final class SecretKey implements KeyInterface
{
    public const ALGORITHM = 'chacha20-poly1305-ietf';

    private const MAGIC = 'SYK';
    private const VERSION = 1;
    private const ALGORITHM_ID = 1;
    private const HEADER_BYTES = 5; // magic(3) + version(1) + algorithmId(1)

    private function __construct(
        private readonly string $bytes,
    ) {
        if (SymmetricEngineInterface::KEY_BYTES !== \strlen($bytes)) {
            throw new InvalidKeyException(\sprintf(
                'Secret key must be exactly %d bytes; got %d.',
                SymmetricEngineInterface::KEY_BYTES,
                \strlen($bytes),
            ));
        }
    }

    public static function generate(): self
    {
        return new self(random_bytes(SymmetricEngineInterface::KEY_BYTES));
    }

    public static function fromBytes(string $bytes): self
    {
        return new self($bytes);
    }

    public function bytes(): string
    {
        return $this->bytes;
    }

    #[\Override]
    public function algorithm(): string
    {
        return self::ALGORITHM;
    }

    #[\Override]
    public function export(): string
    {
        return Encoding::toBase64(
            self::MAGIC . pack('C', self::VERSION) . pack('C', self::ALGORITHM_ID) . $this->bytes,
        );
    }

    public static function import(string $exported): self
    {
        try {
            $raw = Encoding::fromBase64($exported);
        } catch (InvalidArgumentException $e) {
            throw new InvalidKeyException('Malformed exported secret key.', 0, $e);
        }

        if (self::HEADER_BYTES + SymmetricEngineInterface::KEY_BYTES !== \strlen($raw)) {
            throw new InvalidKeyException('Malformed exported secret key.');
        }
        if (self::MAGIC !== substr($raw, 0, 3)) {
            throw new InvalidKeyException('Unrecognized secret key format.');
        }
        if (self::VERSION !== \ord($raw[3]) || self::ALGORITHM_ID !== \ord($raw[4])) {
            throw new InvalidKeyException('Unsupported secret key version or algorithm.');
        }

        return new self(substr($raw, self::HEADER_BYTES));
    }
}
