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
 * Internal contract for an asymmetric (public-key) encryption backend.
 *
 * Anonymous mode encrypts to a recipient public key (sender unauthenticated).
 * Authenticated mode additionally binds the sender's identity. Not all
 * algorithms support authenticated mode; callers check {@see self::algorithm()}.
 *
 * @author David Gebler <me@davegebler.com>
 *
 * @internal
 */
interface AsymmetricEncryptionEngineInterface
{
    /**
     * @return array{0: string, 1: string} [publicKey, privateKey] raw material
     */
    public function generateKeyPair(): array;

    public function sealAnonymous(string $plaintext, string $recipientPublic): string;

    public function openAnonymous(string $ciphertext, string $recipientPublic, string $recipientPrivate): string;

    public function encryptAuthenticated(string $plaintext, string $nonce, string $senderPrivate, string $recipientPublic): string;

    public function decryptAuthenticated(string $ciphertext, string $nonce, string $recipientPrivate, string $senderPublic): string;

    /** @return positive-int */
    public function authenticatedNonceBytes(): int;

    public function isAvailable(): bool;

    public function algorithm(): string;

    public function name(): string;
}
