<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement;

use Symfony\Component\KeyManagement\Exception\DecryptionFailedException;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;
use Symfony\Component\KeyManagement\Exception\KeyNotFoundException;
use Symfony\Component\KeyManagement\Exception\LogicException;
use Symfony\Component\KeyManagement\Exception\RuntimeException;
use Symfony\Component\KeyManagement\Exception\UnsupportedOperationException;

/**
 * High-level envelope encryption helper.
 *
 * Hides the data key dance from the caller: encrypts arbitrary-size payloads
 * by asking the KMS for a fresh data key, locally encrypting the payload
 * with the AEAD cipher of the configured {@see EnvelopeFormat}, and
 * bundling everything into an {@see Envelope} that owns its own framing.
 * Decryption asks the KMS to unwrap the data key and decrypts locally with
 * the format recorded in the envelope.
 *
 * The Envelope value object is opaque: store it on disk, in a database, or
 * send it over the wire (it implements `Stringable`). The same envelope
 * decrypts identically regardless of which KMS backend produced it, as long
 * as the same backend (or a compatible one with access to the same master
 * key) is used to decrypt.
 *
 * Why the data key comes back in plaintext: it is not the master key. The
 * backend mints a fresh single-use key, hands back one plaintext copy plus
 * one wrapped by the master key, then keeps nothing. The master key never
 * leaves the backend, and the plaintext copy is wiped as soon as the payload
 * is encrypted ({@see DataKey::use()}). That local copy is precisely what
 * allows a payload of any size to be encrypted without sending it anywhere.
 *
 * This is a different trade-off from {@see EncrypterInterface::encrypt()},
 * which sends the plaintext to the backend and gets the ciphertext back:
 *   - direct: the payload itself travels to the KMS and its size is capped by
 *     the provider (4 KB on AWS KMS, a few hundred bytes for RSA on Azure Key
 *     Vault). Suited to a config secret, a token, or wrapping a key.
 *   - envelope (this class): only the data key is exchanged with the KMS, the
 *     payload never leaves the process, and its size is unbounded.
 *
 * Envelope encryption therefore needs a backend that generates data keys, and
 * the constructor asks for that capability rather than for a bare encrypter.
 * Backends declining {@see DataKeyGeneratorInterface} are only usable through
 * the direct path.
 *
 * Envelopes use a fresh data key and random nonce for each payload.
 * Use a blind index in a sibling column for equality lookups.
 *
 * The `$aad` argument authenticates the bulk ciphertext through the local AEAD.
 * The AEAD also authenticates the format id, master key id and wrapped data key.
 * The AAD isn't forwarded to the KMS, so wrapping the data key doesn't authenticate the AAD.
 * A mismatch is refused on every backend, including backends that don't support AAD.
 * A self-contained envelope can't use a KMS policy conditioned on the AAD, such as AWS KMS `kms:EncryptionContext`.
 * A data key store can bind a context at the backend, {@see DataKeyStoreInterface}.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
final class EnvelopeEncrypter implements EnvelopeEncrypterInterface, EnvelopeDecrypterInterface
{
    use LocalAead;

    private readonly SelfContainedFormat $format;

    /**
     * @var list<string>|null
     */
    private readonly ?array $allowedKeyIds;

    /**
     * @param list<string>|null $allowedKeyIds Exact master key IDs to accept; null accepts any key
     */
    public function __construct(
        private readonly DataKeyGeneratorInterface $kms,
        ?array $allowedKeyIds = null,
    ) {
        if (!\extension_loaded('openssl')) {
            throw new LogicException('The "openssl" PHP extension is required for envelope encryption.');
        }

        if (null !== $allowedKeyIds) {
            foreach ($allowedKeyIds as $keyId) {
                if (!\is_string($keyId) || '' === $keyId) {
                    throw new InvalidArgumentException('Allowed key IDs must be non-empty strings.');
                }
            }

            $allowedKeyIds = array_values(array_unique($allowedKeyIds));
        }

        $this->allowedKeyIds = $allowedKeyIds;
        $this->format = new SelfContainedFormat();
    }

    /**
     * @throws KeyNotFoundException          If `$key` is unknown to the underlying KMS
     * @throws UnsupportedOperationException If the backend cannot generate data keys
     * @throws RuntimeException              On any other backend failure
     * @throws LogicException                If the KMS returns a key that is not in the allowed key IDs
     */
    public function encrypt(string $key, #[\SensitiveParameter] string $plaintext, string $aad = ''): Envelope
    {
        $format = $this->format;
        $dataKey = $this->kms->generateDataKey($key, $format->keyBytes());

        if (!$this->isKeyIdAllowed($dataKey->wrapped->keyId)) {
            throw new LogicException(\sprintf('The KMS returned key "%s", which is not in the allowed key IDs.', $dataKey->wrapped->keyId));
        }

        $iv = random_bytes($format->ivBytes());

        [$ciphertext, $tag] = $dataKey->use(static fn (#[\SensitiveParameter] string $dek): array => self::seal($format, $dek, $plaintext, $iv, $aad, $dataKey->wrapped->keyId, $dataKey->wrapped->blob));

        return Envelope::selfContained($dataKey->wrapped->keyId, $dataKey->wrapped->blob, $iv, $tag, $ciphertext);
    }

    /**
     * @throws LogicException            If the envelope refers to a stored data key, which this encrypter has no store to resolve
     * @throws DecryptionFailedException If the payload is invalid, tampered, `$aad` does not match, or the envelope refers to a key that is not in the allowed key IDs
     */
    public function decrypt(Envelope $envelope, string $aad = ''): string
    {
        if (!$envelope->format instanceof SelfContainedFormat) {
            throw new LogicException(\sprintf('The envelope refers to a stored data key, which "%s" cannot resolve.', self::class));
        }

        if (!$this->isKeyIdAllowed($envelope->keyId)) {
            throw new DecryptionFailedException();
        }

        $dataKey = $this->kms->unwrapDataKey(new Ciphertext($envelope->wrappedDek, $envelope->keyId));

        return $dataKey->use(static fn (#[\SensitiveParameter] string $dek): string => self::open($envelope, $dek, $aad));
    }

    private function isKeyIdAllowed(?string $keyId): bool
    {
        return null === $this->allowedKeyIds || (\is_string($keyId) && \in_array($keyId, $this->allowedKeyIds, true));
    }
}
