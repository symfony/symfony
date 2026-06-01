<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Encryption;

use Symfony\Component\Encryption\Exception\UnsupportedAlgorithmException;

/**
 * Cryptographic digests of arbitrary data over a whitelist of strong
 * algorithms. Weak algorithms (md5, sha1, crc) are intentionally excluded.
 *
 * @author David Gebler <me@davegebler.com>
 */
final class Hasher implements HasherInterface
{
    private const ALLOWED = [
        'sha256',
        'sha384',
        'sha512',
        'sha3-256',
        'sha3-384',
        'sha3-512',
        'blake2b-512',
    ];

    public function __construct(
        private readonly string $defaultAlgorithm = 'sha256',
    ) {
        $this->assertSupported($defaultAlgorithm);
    }

    /**
     * Hex-encoded digest.
     */
    #[\Override]
    public function hash(string $data, ?string $algorithm = null): string
    {
        return Encoding::toHex($this->raw($data, $algorithm));
    }

    /**
     * Base64-encoded digest.
     */
    #[\Override]
    public function hashBase64(string $data, ?string $algorithm = null): string
    {
        return Encoding::toBase64($this->raw($data, $algorithm));
    }

    /**
     * Raw binary digest.
     */
    #[\Override]
    public function raw(string $data, ?string $algorithm = null): string
    {
        $algorithm ??= $this->defaultAlgorithm;
        $this->assertSupported($algorithm);

        return hash($algorithm, $data, true);
    }

    private function assertSupported(string $algorithm): void
    {
        if (!\in_array($algorithm, self::ALLOWED, true) || !\in_array($algorithm, hash_algos(), true)) {
            throw new UnsupportedAlgorithmException(sprintf('Unsupported hash algorithm "%s". Supported: "%s".', $algorithm, implode(', ', self::ALLOWED)));
        }
    }
}
