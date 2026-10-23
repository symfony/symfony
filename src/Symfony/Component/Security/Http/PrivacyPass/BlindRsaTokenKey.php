<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\PrivacyPass;

/**
 * The public key of an issuer of Blind RSA tokens: a DER-encoded SubjectPublicKeyInfo using the id-RSASSA-PSS object identifier.
 *
 * The signature parameters are read from the key, not from this class: an issuer announces the hash, the mask generation function and the salt length its tokens are signed with, and OpenSSL enforces them.
 * The object identifier is the one thing checked here, because a key announcing id-rsaEncryption instead would be verified against PKCS #1 v1.5 signatures.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc9578#section-6.5
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 */
final class BlindRsaTokenKey implements TokenKeyInterface
{
    /**
     * The DER-encoded id-RSASSA-PSS object identifier, 1.2.840.113549.1.1.10.
     */
    private const ID_RSASSA_PSS = '06092a864886f70d01010a';

    /**
     * The modulus size the 256-byte authenticator of this token type requires.
     */
    private const MODULUS_BITS = 2048;

    private readonly \OpenSSLAsymmetricKey $publicKey;

    /**
     * @param string $tokenKey The DER-encoded SubjectPublicKeyInfo of the issuer
     *
     * @throws \InvalidArgumentException If the key is not one a Blind RSA token can be verified with
     */
    public function __construct(private readonly string $tokenKey)
    {
        $algorithm = self::skipHeader($tokenKey, 0);

        if ("\x30" !== ($tokenKey[0] ?? '') || "\x30" !== ($tokenKey[$algorithm] ?? '')) {
            throw new \InvalidArgumentException('The token key must be a DER-encoded SubjectPublicKeyInfo.');
        }
        if (hex2bin(self::ID_RSASSA_PSS) !== substr($tokenKey, self::skipHeader($tokenKey, $algorithm), 11)) {
            throw new \InvalidArgumentException('The token key must use the id-RSASSA-PSS object identifier.');
        }

        $publicKey = openssl_pkey_get_public(\sprintf("-----BEGIN PUBLIC KEY-----\n%s-----END PUBLIC KEY-----\n", chunk_split(base64_encode($tokenKey), 64, "\n")));

        if (false === $publicKey) {
            throw new \InvalidArgumentException(\sprintf('The token key cannot be read: "%s".', openssl_error_string() ?: 'unknown error'));
        }
        if (self::MODULUS_BITS !== $bits = openssl_pkey_get_details($publicKey)['bits'] ?? 0) {
            throw new \InvalidArgumentException(\sprintf('A Blind RSA token is signed with a %d-bit key, %d given.', self::MODULUS_BITS, $bits));
        }

        $this->publicKey = $publicKey;
    }

    /**
     * The digest of the key, as RFC 9578, Section 6.5, computes it.
     */
    public function getId(): string
    {
        return hash('sha256', $this->tokenKey, true);
    }

    public function toBase64Url(): string
    {
        return strtr(base64_encode($this->tokenKey), '+/', '-_');
    }

    public function verify(string $authenticatorInput, string $authenticator): bool
    {
        return 1 === openssl_verify($authenticatorInput, $authenticator, $this->publicKey, \OPENSSL_ALGO_SHA384);
    }

    /**
     * The offset right after the header of the DER value starting at the given offset.
     */
    private static function skipHeader(string $der, int $offset): int
    {
        $length = \ord($der[$offset + 1] ?? "\x00");

        return $offset + (0x80 < $length ? 2 + ($length & 0x7F) : 2);
    }
}
