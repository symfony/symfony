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

use Symfony\Component\Encryption\Engine\EngineSelector;
use Symfony\Component\Encryption\Engine\SymmetricEngineInterface;
use Symfony\Component\Encryption\Envelope\AsymmetricEnvelope;
use Symfony\Component\Encryption\Envelope\Envelope;
use Symfony\Component\Encryption\Exception\DecryptionException;
use Symfony\Component\Encryption\Exception\InvalidArgumentException;
use Symfony\Component\Encryption\Exception\InvalidKeyException;
use Symfony\Component\Encryption\Key\KeyPair;
use Symfony\Component\Encryption\Key\PrivateKey;
use Symfony\Component\Encryption\Key\PublicKey;

/**
 * Public-key (asymmetric) encryption. Anonymous mode encrypts to a recipient's
 * public key; authenticated mode (X25519 only) additionally binds the sender.
 *
 * The backend is selected automatically and hidden; ciphertext is a
 * self-describing envelope. X25519 (anonymous + authenticated) and RSA-OAEP
 * (anonymous, hybrid) are supported, dispatched on the recipient key.
 *
 * @author David Gebler <me@davegebler.com>
 *
 * @api
 */
final class AsymmetricCipher implements AsymmetricCipherInterface
{
    private const DEFAULT_ALGORITHM = 'x25519';
    private const ALGORITHM_RSA = 'rsa';

    public function __construct(
        private readonly EngineSelector $engines = new EngineSelector(),
    ) {
    }

    #[\Override]
    public function generateKeyPair(string $algorithm = self::DEFAULT_ALGORITHM, int $rsaKeyBits = 3072): KeyPair
    {
        if (self::ALGORITHM_RSA === $algorithm) {
            [$public, $private] = $this->engines->rsaEngine()->generateKeyPair($rsaKeyBits);

            return new KeyPair(
                PublicKey::fromBytes(self::ALGORITHM_RSA, 'encryption', $public),
                PrivateKey::fromBytes(self::ALGORITHM_RSA, 'encryption', $private),
            );
        }

        $engine = $this->engines->asymmetricEncryptionEngine(self::DEFAULT_ALGORITHM);
        [$public, $private] = $engine->generateKeyPair();

        return new KeyPair(
            PublicKey::fromBytes(self::DEFAULT_ALGORITHM, 'encryption', $public),
            PrivateKey::fromBytes(self::DEFAULT_ALGORITHM, 'encryption', $private),
        );
    }

    #[\Override]
    public function encryptAnonymous(string $plaintext, PublicKey $recipient): string
    {
        $this->assertEncryptionKey($recipient);

        if (self::ALGORITHM_RSA === $recipient->algorithm()) {
            return $this->encryptAnonymousRsa($plaintext, $recipient);
        }

        $engine = $this->engines->asymmetricEncryptionEngine($recipient->algorithm());
        $body = $engine->sealAnonymous($plaintext, $recipient->bytes());

        return Encoding::toBase64(AsymmetricEnvelope::anonymous($recipient->algorithm(), $body)->serialize());
    }

    #[\Override]
    public function decryptAnonymous(string $ciphertext, KeyPair $recipient): string
    {
        $envelope = $this->parse($ciphertext);
        if (AsymmetricEnvelope::MODE_ANONYMOUS !== $envelope->mode()) {
            throw new DecryptionException('This ciphertext is not an anonymous message.');
        }

        try {
            if (self::ALGORITHM_RSA === $envelope->algorithm()) {
                return $this->decryptAnonymousRsa($envelope->body(), $recipient);
            }

            $engine = $this->engines->asymmetricEncryptionEngine($envelope->algorithm());

            return $engine->openAnonymous($envelope->body(), $recipient->public()->bytes(), $recipient->private()->bytes());
        } catch (InvalidKeyException $e) {
            throw new DecryptionException('Decryption failed: incompatible key for this ciphertext.', 0, $e);
        }
    }

    #[\Override]
    public function encryptAuthenticated(string $plaintext, PrivateKey $senderPrivate, PublicKey $recipient): string
    {
        $this->assertEncryptionKey($senderPrivate);
        $this->assertEncryptionKey($recipient);
        if (self::DEFAULT_ALGORITHM !== $recipient->algorithm()) {
            throw new InvalidKeyException(\sprintf('Authenticated encryption requires X25519 keys; got "%s".', $recipient->algorithm()));
        }

        $engine = $this->engines->asymmetricEncryptionEngine($recipient->algorithm());
        $nonce = random_bytes($engine->authenticatedNonceBytes());
        $body = $engine->encryptAuthenticated($plaintext, $nonce, $senderPrivate->bytes(), $recipient->bytes());

        return Encoding::toBase64(AsymmetricEnvelope::authenticated($recipient->algorithm(), $nonce, $body)->serialize());
    }

    #[\Override]
    public function decryptAuthenticated(string $ciphertext, KeyPair $recipient, PublicKey $senderPublic): string
    {
        $envelope = $this->parse($ciphertext);
        if (AsymmetricEnvelope::MODE_AUTHENTICATED !== $envelope->mode()) {
            throw new DecryptionException('This ciphertext is not an authenticated message.');
        }

        $engine = $this->engines->asymmetricEncryptionEngine($envelope->algorithm());

        return $engine->decryptAuthenticated(
            $envelope->body(),
            $envelope->nonce(),
            $recipient->private()->bytes(),
            $senderPublic->bytes(),
        );
    }

    private function encryptAnonymousRsa(string $plaintext, PublicKey $recipient): string
    {
        $symEngine = $this->engines->symmetricEngine();
        $symKey = random_bytes(SymmetricEngineInterface::KEY_BYTES);
        $nonce = random_bytes(SymmetricEngineInterface::NONCE_BYTES);

        try {
            $symCiphertext = $symEngine->encrypt($plaintext, $symKey, $nonce);
            $inner = Envelope::forRawKey($nonce, $symCiphertext)->serialize();
            $wrapped = $this->engines->rsaEngine()->wrap($symKey, $recipient->bytes());
        } finally {
            $this->wipe($symKey);
        }

        $body = pack('n', \strlen($wrapped)).$wrapped.$inner;

        return Encoding::toBase64(AsymmetricEnvelope::anonymous(self::ALGORITHM_RSA, $body)->serialize());
    }

    private function decryptAnonymousRsa(string $body, KeyPair $recipient): string
    {
        if (\strlen($body) < 2) {
            throw new DecryptionException('Malformed RSA ciphertext.');
        }

        /** @var array{1: int} $lengthHeader */
        $lengthHeader = unpack('n', substr($body, 0, 2));
        $wrappedLength = $lengthHeader[1];
        if (\strlen($body) < 2 + $wrappedLength) {
            throw new DecryptionException('Malformed RSA ciphertext.');
        }

        $wrapped = substr($body, 2, $wrappedLength);
        $innerRaw = substr($body, 2 + $wrappedLength);

        $symKey = $this->engines->rsaEngine()->unwrap($wrapped, $recipient->private()->bytes());

        try {
            if (SymmetricEngineInterface::KEY_BYTES !== \strlen($symKey)) {
                throw new DecryptionException('Malformed RSA ciphertext.');
            }
            $inner = Envelope::deserialize($innerRaw);

            return $this->engines->symmetricEngine()->decrypt($inner->ciphertext(), $symKey, $inner->nonce());
        } catch (InvalidArgumentException $e) {
            throw new DecryptionException('Malformed RSA ciphertext.', 0, $e);
        } finally {
            $this->wipe($symKey);
        }
    }

    /**
     * @param-out string|null $key
     */
    private function wipe(string &$key): void
    {
        if (\function_exists('sodium_memzero')) {
            sodium_memzero($key);
        }
    }

    private function assertEncryptionKey(PublicKey|PrivateKey $key): void
    {
        if ('encryption' !== $key->purpose()) {
            throw new InvalidKeyException(\sprintf('Expected an encryption key; got purpose "%s".', $key->purpose()));
        }
    }

    private function parse(string $ciphertext): AsymmetricEnvelope
    {
        try {
            return AsymmetricEnvelope::deserialize(Encoding::fromBase64($ciphertext));
        } catch (InvalidArgumentException $e) {
            throw new DecryptionException('Malformed ciphertext.', 0, $e);
        }
    }
}
