<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Encryption\Engine;

/**
 * Internal contract for RSA key generation and RSA-OAEP (SHA-256) key wrapping.
 *
 * @author David Gebler <me@davegebler.com>
 *
 * @internal
 */
interface RsaEngineInterface
{
    /**
     * @return array{0: string, 1: string} [publicPem, privatePem]
     */
    public function generateKeyPair(int $bits): array;

    /**
     * RSA-OAEP-encrypt a short secret (e.g. a symmetric key) to a public key.
     */
    public function wrap(string $plaintextKey, string $recipientPublicPem): string;

    /**
     * RSA-OAEP-decrypt. Throws DecryptionException on failure.
     */
    public function unwrap(string $wrapped, string $recipientPrivatePem): string;

    public function isAvailable(): bool;

    public function name(): string;
}
