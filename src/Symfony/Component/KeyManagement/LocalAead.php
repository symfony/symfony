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
use Symfony\Component\KeyManagement\Exception\RuntimeException;

/**
 * The local half of envelope encryption, shared by the encrypters.
 *
 * Both {@see EnvelopeEncrypter} and {@see StoredEnvelopeEncrypter} differ only in where their data
 * key comes from; once they hold one, the AEAD pass over the payload is the same, and it has no
 * business being written twice.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @internal
 */
trait LocalAead
{
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
