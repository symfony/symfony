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

use Symfony\Component\Encryption\Exception\InvalidArgumentException;
use Symfony\Component\Encryption\Exception\UnsupportedAlgorithmException;

/**
 * Shared-secret message authentication using HMAC. Tags are returned
 * hex-encoded; verification is timing-safe via Comparator.
 *
 * @author David Gebler <me@davegebler.com>
 */
final class Mac implements MacInterface
{
    private const ALLOWED = ['sha256', 'sha384', 'sha512', 'sha3-256', 'sha3-512'];

    public function __construct(
        private readonly string $defaultAlgorithm = 'sha256',
    ) {
        $this->assertSupported($defaultAlgorithm);
    }

    /**
     * Generate a random raw key.
     */
    #[\Override]
    public function generateKey(int $bytes = 32): string
    {
        if ($bytes < 16) {
            throw new InvalidArgumentException('MAC key must be at least 16 bytes.');
        }

        return random_bytes($bytes);
    }

    /**
     * Produce a hex-encoded authentication tag.
     */
    #[\Override]
    public function sign(string $message, string $key, ?string $algorithm = null): string
    {
        $algorithm ??= $this->defaultAlgorithm;
        $this->assertSupported($algorithm);

        if ('' === $key) {
            throw new InvalidArgumentException('MAC key must not be empty.');
        }

        return hash_hmac($algorithm, $message, $key);
    }

    /**
     * Timing-safe verification. Returns false for malformed tags or invalid
     * arguments rather than throwing, so a verification mismatch never raises.
     */
    #[\Override]
    public function verify(string $tag, string $message, string $key, ?string $algorithm = null): bool
    {
        try {
            $expected = $this->sign($message, $key, $algorithm);
        } catch (InvalidArgumentException) {
            return false;
        }

        return Comparator::equals($expected, $tag);
    }

    private function assertSupported(string $algorithm): void
    {
        if (!\in_array($algorithm, self::ALLOWED, true) || !\in_array($algorithm, hash_hmac_algos(), true)) {
            throw new UnsupportedAlgorithmException(sprintf('Unsupported MAC algorithm "%s". Supported: "%s".', $algorithm, implode(', ', self::ALLOWED)));
        }
    }
}
