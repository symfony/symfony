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
     * @return array{string, string} The ciphertext and the AEAD tag
     *
     * @throws LogicException   If the data key is not of the length the format calls for
     * @throws RuntimeException If the cipher rejects the inputs
     */
    private static function seal(EnvelopeFormat $format, #[\SensitiveParameter] string $dataKey, #[\SensitiveParameter] string $plaintext, string $iv, string $aad, string ...$fields): array
    {
        self::checkKeyLength($format, $dataKey);
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, $format->cipher(), $dataKey, \OPENSSL_RAW_DATA, $iv, $tag, self::bind($format, $aad, ...$fields), $format->tagBytes());

        if (false === $ciphertext) {
            throw new RuntimeException('Local AEAD encryption failed: '.(openssl_error_string() ?: 'unknown error'));
        }

        return [$ciphertext, $tag];
    }

    /**
     * @throws LogicException            If the data key is not of the length the format calls for
     * @throws DecryptionFailedException If the payload is invalid, tampered, or the AAD does not match
     */
    private static function open(Envelope $envelope, #[\SensitiveParameter] string $dataKey, string $aad): string
    {
        self::checkKeyLength($envelope->format, $dataKey);
        $fields = null !== $envelope->reference ? [$envelope->reference] : [$envelope->keyId, $envelope->wrappedDek];
        $plaintext = openssl_decrypt($envelope->ciphertext, $envelope->format->cipher(), $dataKey, \OPENSSL_RAW_DATA, $envelope->iv, $envelope->tag, self::bind($envelope->format, $aad, ...$fields));

        if (false === $plaintext) {
            throw new DecryptionFailedException();
        }

        return $plaintext;
    }

    /**
     * Authenticates the envelope header before the caller's AAD.
     *
     * Length prefixes keep the header fields and the caller's bytes separate.
     * Stored envelopes bind the reference, so rewrapping a stored key leaves its payloads readable.
     */
    private static function bind(EnvelopeFormat $format, string $aad, string ...$fields): string
    {
        $header = \chr($format->id());
        foreach ($fields as $field) {
            $header .= pack('n', \strlen($field)).$field;
        }

        return $header.$aad;
    }

    /**
     * The cipher would zero-pad a short key and truncate a long one without a word.
     */
    private static function checkKeyLength(EnvelopeFormat $format, #[\SensitiveParameter] string $dataKey): void
    {
        if ($format->keyBytes() !== \strlen($dataKey)) {
            throw new LogicException(\sprintf('The data key must be %d bytes long for "%s", %d given.', $format->keyBytes(), $format->cipher(), \strlen($dataKey)));
        }
    }
}
