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

use Symfony\Component\KeyManagement\Exception\DataKeyNotFoundException;
use Symfony\Component\KeyManagement\Exception\DecryptionFailedException;
use Symfony\Component\KeyManagement\Exception\LogicException;

/**
 * Envelope encryption over a {@see DataKeyStoreInterface}, in {@see StoredFormat}.
 *
 * Where {@see EnvelopeEncrypter} mints a data key per payload and ships it wrapped inside the
 * envelope, this one asks the store for the data key of a scope and ships a reference to it. Every
 * payload of a scope shares that key, so the KMS is contacted once per key and per process instead
 * of once per payload, and the payload carries a 16-byte reference instead of a wrapped key.
 *
 * `$key` is read as the scope: whatever the application decides may share a data key, typically a
 * column, a tenant or a purpose. The master key and the KMS client belong to the store, which
 * records them next to each data key so they can later be swapped.
 *
 * Reading a dataset that mixes both formats is what a progressive migration needs, so a
 * self-contained envelope is handed to the decrypter given as `$selfContained`:
 *
 *     $decrypter = new StoredEnvelopeEncrypter($store, new EnvelopeEncrypter($kms));
 *
 * New payloads are then written in the stored format while the ones written before keep resolving
 * through the KMS. Without that fallback, a self-contained envelope is refused rather than
 * silently mishandled.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
final class StoredEnvelopeEncrypter implements EnvelopeEncrypterInterface, EnvelopeDecrypterInterface
{
    use LocalAead;

    private readonly StoredFormat $format;

    public function __construct(
        private readonly DataKeyStoreInterface $store,
        private readonly ?EnvelopeDecrypterInterface $selfContained = null,
    ) {
        if (!\extension_loaded('openssl')) {
            throw new LogicException('The "openssl" PHP extension is required for envelope encryption.');
        }

        $this->format = new StoredFormat();
    }

    /**
     * The AAD binds the payload only, not the stored data key: that key is shared by the whole
     * scope, so binding it to the AAD of one payload would make it unusable for the next.
     */
    public function encrypt(string $key, #[\SensitiveParameter] string $plaintext, string $aad = ''): Envelope
    {
        $format = $this->format;
        $dataKey = $this->store->current($key);
        $iv = random_bytes($format->ivBytes());

        [$ciphertext, $tag] = $dataKey->use(static fn (#[\SensitiveParameter] string $dek): array => self::seal($format, $dek, $plaintext, $iv, $aad));

        return Envelope::referencing($dataKey->reference, $iv, $tag, $ciphertext);
    }

    /**
     * @throws LogicException            If the envelope carries its own data key and no fallback decrypter was given
     * @throws DataKeyNotFoundException  If the store no longer holds the data key the envelope refers to
     * @throws DecryptionFailedException If the payload is invalid, tampered, or `$aad` does not match
     */
    public function decrypt(Envelope $envelope, string $aad = ''): string
    {
        if (null !== $envelope->reference) {
            $dataKey = $this->store->get($envelope->reference);

            return $dataKey->use(static fn (#[\SensitiveParameter] string $dek): string => self::open($envelope, $dek, $aad));
        }

        return $this->selfContained?->decrypt($envelope, $aad)
            ?? throw new LogicException(\sprintf('The envelope carries its own data key; give "%s" a decrypter for that format to read it.', self::class));
    }
}
