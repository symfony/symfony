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
use Symfony\Component\KeyManagement\Exception\LogicException;
use Symfony\Component\KeyManagement\Exception\RuntimeException;

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
 * Envelopes are always randomized, and there is no deterministic counterpart
 * to {@see EncrypterInterface::encrypt()}'s `$deterministic` flag here. Stable
 * output would require a stable data key, which `generateDataKey()` refuses to
 * return by contract, and a deterministic wrapping, which AWS KMS, Azure Key
 * Vault, Google Cloud KMS and Vault Transit all decline since they draw a
 * random nonce when they wrap. Deriving the nonce alone would leave the
 * envelope varying from call to call anyway. When equal plaintexts must yield
 * equal ciphertexts, for instance to look up an encrypted column, encrypt
 * through the direct path on a backend that offers the flag, or keep a blind
 * index (an HMAC of the plaintext) in a sibling column.
 *
 * AAD handling: the `$aad` argument is forwarded BOTH to the KMS (binding
 * the wrapped data key) and to the local AEAD (binding the bulk ciphertext).
 * The double binding is defense in depth: the local AEAD always catches
 * AAD mismatches even on backends where the KMS can't enforce them. Note
 * that some backends require a specific key shape for KMS-side AAD: e.g.
 * Vault Transit only accepts a `context` for keys created with
 * `derived=true` and rejects it on plain symmetric keys. Passing a
 * non-empty `$aad` to a non-derived Vault key will surface as a
 * RuntimeException at encrypt time.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
final class EnvelopeEncrypter implements EnvelopeEncrypterInterface, EnvelopeDecrypterInterface
{
    private readonly SelfContainedFormat $format;

    public function __construct(
        private readonly DataKeyGeneratorInterface $kms,
    ) {
        if (!\extension_loaded('openssl')) {
            throw new LogicException('The "openssl" PHP extension is required for envelope encryption.');
        }

        $this->format = new SelfContainedFormat();
    }

    public function encrypt(string $key, #[\SensitiveParameter] string $plaintext, string $aad = ''): Envelope
    {
        $format = $this->format;
        $dataKey = $this->kms->generateDataKey($key, $format->keyBytes(), $aad);
        $iv = random_bytes($format->ivBytes());

        [$ciphertext, $tag] = $dataKey->use(static fn (#[\SensitiveParameter] string $dek): array => self::seal($format, $dek, $plaintext, $iv, $aad));

        return Envelope::selfContained($dataKey->wrapped->keyId, $dataKey->wrapped->blob, $iv, $tag, $ciphertext);
    }

    /**
     * @throws DecryptionFailedException If the payload is invalid, tampered, or `$aad` does not match
     */
    public function decrypt(Envelope $envelope, string $aad = ''): string
    {
        $dataKey = $this->kms->unwrapDataKey(new Ciphertext($envelope->wrappedDek, $envelope->keyId), $aad);

        return $dataKey->use(static fn (#[\SensitiveParameter] string $dek): string => self::open($envelope, $dek, $aad));
    }

    /**
     * @return array{string, string} the ciphertext and the AEAD tag
     *
     * @throws RuntimeException If the cipher rejects the inputs
     */
    private static function seal(EnvelopeFormat $format, #[\SensitiveParameter] string $dataKey, #[\SensitiveParameter] string $plaintext, string $iv, string $aad): array
    {
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, $format->cipher(), $dataKey, \OPENSSL_RAW_DATA, $iv, $tag, $aad, $format->tagBytes());

        if (false === $ciphertext) {
            throw new RuntimeException('Local AEAD encryption failed: '.(openssl_error_string() ?: 'unknown error'));
        }

        return [$ciphertext, $tag];
    }

    /**
     * @throws DecryptionFailedException If the payload is invalid, tampered, or the AAD does not match
     */
    private static function open(Envelope $envelope, #[\SensitiveParameter] string $dataKey, string $aad): string
    {
        $plaintext = openssl_decrypt($envelope->ciphertext, $envelope->format->cipher(), $dataKey, \OPENSSL_RAW_DATA, $envelope->iv, $envelope->tag, $aad);

        if (false === $plaintext) {
            throw new DecryptionFailedException();
        }

        return $plaintext;
    }
}
