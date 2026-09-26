<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\Kmip\Kmip;

use Symfony\Component\KeyManagement\Bridge\Kmip\KmipEncryptionSchemeInterface;
use Symfony\Component\KeyManagement\Bridge\Kmip\KmipEncryptionSchemeRegistry;
use Symfony\Component\KeyManagement\Ciphertext;
use Symfony\Component\KeyManagement\Exception\DecryptionFailedException;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;

/**
 * Packs KMIP encryption results into a blob that the bridge can decrypt later.
 *
 * KMIP carries encrypted data, the IV and the authentication tag in separate operation fields.
 * Ciphertext::keyId stores the KMIP Unique Identifier of the key used for encryption.
 * The blob starts with a format byte (0x01), a one-byte scheme-name length and the ASCII scheme name.
 * After the header, the built-in schemes store a one-byte IV length, the IV, a 16-byte authentication tag and the encrypted data, in that order. Other schemes define their own blob contents; this codec leaves those bytes untouched.
 * AES-GCM uses a 16-byte IV by default and accepts a configured length; ChaCha20-Poly1305 and AES-GCM-SIV use 12-byte IVs.
 * On decryption, AES-GCM uses the stored length to locate the IV and set the KMIP IV Length parameter. Changing the configured length therefore affects new ciphertext without preventing access to earlier ciphertext, provided the server still accepts that IV length.
 *
 * This blob is the bridge's storage format, not a KMIP TTLV message.
 * The scheme name tells the bridge how to interpret the bytes when decrypting them later.
 * The format byte and scheme name are included in the authenticated additional data, so changing either makes decryption fail.
 * Keep each stored name mapped to the same algorithm and mode while ciphertext written under it remains stored.
 *
 * @author Antonio Pauletich <antonio.pauletich95@gmail.com>
 *
 * @internal
 */
final class CiphertextCodec
{
    /**
     * The first byte of the bridge's named ciphertext format.
     */
    private const string NAMED_FORMAT = "\x01";
    private const int FORMAT_BYTE_OFFSET = 0;
    private const int NAME_LENGTH_OFFSET = 1;
    private const int NAME_OFFSET = 2;
    private const int MIN_NAME_LENGTH = 1;

    public function __construct(private readonly KmipEncryptionSchemeRegistry $schemes)
    {
    }

    public function authenticatedData(KmipEncryptionSchemeInterface $scheme, #[\SensitiveParameter] string $aad): string
    {
        return $this->header($scheme).$aad;
    }

    public function encode(KmipEncryptionSchemeInterface $scheme, #[\SensitiveParameter] Ciphertext $schemeCiphertext): Ciphertext
    {
        return new Ciphertext($this->header($scheme).$schemeCiphertext->blob, $schemeCiphertext->keyId);
    }

    /**
     * @return array{KmipEncryptionSchemeInterface, Ciphertext, string}
     */
    public function decode(#[\SensitiveParameter] Ciphertext $ciphertext, #[\SensitiveParameter] string $aad): array
    {
        $blob = $ciphertext->blob;
        if (\strlen($blob) < self::NAME_OFFSET + self::MIN_NAME_LENGTH || self::NAMED_FORMAT !== $blob[self::FORMAT_BYTE_OFFSET]) {
            throw new DecryptionFailedException();
        }
        $length = \ord($blob[self::NAME_LENGTH_OFFSET]);
        if ($length < self::MIN_NAME_LENGTH || \strlen($blob) < self::NAME_OFFSET + $length) {
            throw new DecryptionFailedException();
        }
        $header = substr($blob, 0, self::NAME_OFFSET + $length);
        try {
            $scheme = $this->schemes->get(substr($blob, self::NAME_OFFSET, $length));
        } catch (InvalidArgumentException) {
            throw new DecryptionFailedException();
        }

        return [$scheme, new Ciphertext(substr($blob, self::NAME_OFFSET + $length), $ciphertext->keyId), $header.$aad];
    }

    private function header(KmipEncryptionSchemeInterface $scheme): string
    {
        $name = $scheme->name();

        return self::NAMED_FORMAT.\chr(\strlen($name)).$name;
    }
}
