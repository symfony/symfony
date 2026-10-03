<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\Kmip;

use Symfony\Component\KeyManagement\Bridge\Kmip\Kmip\AesGcmEncryptionScheme;
use Symfony\Component\KeyManagement\Bridge\Kmip\Kmip\ChaCha20Poly1305EncryptionScheme;
use Symfony\Component\KeyManagement\Bridge\Kmip\Kmip\CiphertextCodec;
use Symfony\Component\KeyManagement\Bridge\Kmip\Kmip\FrameTooLarge;
use Symfony\Component\KeyManagement\Bridge\Kmip\Kmip\OperationFailed;
use Symfony\Component\KeyManagement\Bridge\Kmip\Kmip\ProtocolClient;
use Symfony\Component\KeyManagement\Bridge\Kmip\Kmip\StreamTransport;
use Symfony\Component\KeyManagement\Bridge\Kmip\Kmip\Ttlv;
use Symfony\Component\KeyManagement\Ciphertext;
use Symfony\Component\KeyManagement\DataKey;
use Symfony\Component\KeyManagement\DataKeyGeneratorInterface;
use Symfony\Component\KeyManagement\DecrypterInterface;
use Symfony\Component\KeyManagement\EncrypterInterface;
use Symfony\Component\KeyManagement\Exception\DecryptionFailedException;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;
use Symfony\Component\KeyManagement\Exception\KeyNotFoundException;
use Symfony\Component\KeyManagement\Exception\UnsupportedOperationException;

/**
 * Encrypts and decrypts with a server-held symmetric key through KMIP.
 *
 * The constructor accepts a request client and encryption schemes. For a direct TLS connection, {@see self::fromTls()} builds the request client from the connection settings; {@see KmipKmsFactory} does the same for a DSN.
 *
 * The built-in transport opens a verified mutual TLS connection for each operation. The selected encryption scheme sends the plaintext or a locally generated data key to the server; the master key stays there. Deterministic encryption is unsupported.
 *
 * The bridge authenticates the caller's AAD together with its ciphertext format header. Decryption reads the stored scheme name, so a client can change its selected scheme while retaining access to earlier ciphertext if the old scheme remains registered.
 *
 * When a server reports KMIP General Failure, decrypt and unwrap preserve that operational error. PyKMIP also uses General Failure for a bad authentication tag, so callers using that server may receive {@see \Symfony\Component\KeyManagement\Exception\RuntimeException} rather than {@see DecryptionFailedException} for tampered data or incorrect AAD.
 *
 * @author Antonio Pauletich <antonio.pauletich95@gmail.com>
 *
 * @experimental
 */
final class KmipKms implements EncrypterInterface, DecrypterInterface, DataKeyGeneratorInterface
{
    /**
     * Default TCP port assigned to KMIP.
     */
    private const int DEFAULT_PORT = 5696;
    private const int MIN_PORT = 1;
    private const int MAX_PORT = 65535;
    /**
     * Minimum locally generated data key size of 128 bits.
     */
    private const int MIN_DATA_KEY_LENGTH = 16;
    /**
     * Default locally generated data key size of 256 bits.
     */
    private const int DEFAULT_DATA_KEY_LENGTH = 32;
    /**
     * Space reserved for KMIP framing and other request fields around a key identifier or data key.
     */
    private const int FRAME_SIZE_RESERVE = 1024;
    /**
     * Limits key identifiers included in exception messages.
     */
    private const int MAX_DISPLAY_KEY_ID_LENGTH = 128;
    /**
     * Default TLS connection timeout in seconds.
     */
    private const float DEFAULT_TIMEOUT = 10.0;
    private const int REASON_ITEM_NOT_FOUND = 0x01;
    private const int REASON_CRYPTOGRAPHIC_FAILURE = 0x0A;
    private const int REASON_OBJECT_DESTROYED = 0x36;
    private const int REASON_OBJECT_NOT_FOUND = 0x37;
    private const string DEFAULT_CIPHER = 'aes-gcm';
    /**
     * A hostname containing a slash, colon or whitespace must pass IP address validation.
     */
    private const string HOST_CHARACTERS_REQUIRING_IP_VALIDATION_PATTERN = '/[\/:\s]/';
    /**
     * An empty regular expression in Unicode mode checks UTF-8 validity.
     */
    private const string UTF8_VALIDATION_PATTERN = '//u';
    /**
     * Unicode control and format characters are escaped in displayed key identifiers.
     */
    private const string CONTROL_OR_FORMAT_CHARACTER_PATTERN = '/[\p{Cc}\p{Cf}]/u';
    private const string CONTROL_CHARACTER_ESCAPE_PREFIX = '\\x';

    private readonly KmipRequestClientInterface $requestClient;
    private readonly KmipEncryptionSchemeInterface $encryptionScheme;
    private readonly CiphertextCodec $ciphertextCodec;

    public function __construct(
        KmipRequestClientInterface $requestClient,
        KmipEncryptionSchemeRegistry $schemes = new KmipEncryptionSchemeRegistry([new AesGcmEncryptionScheme(), new ChaCha20Poly1305EncryptionScheme()]),
        string $cipher = self::DEFAULT_CIPHER,
    ) {
        $this->encryptionScheme = $schemes->get($cipher);
        $this->ciphertextCodec = new CiphertextCodec($schemes);
        $this->requestClient = $requestClient;
    }

    /**
     * Builds a KMIP client using verified mutual TLS and the selected protocol version.
     */
    public static function fromTls(
        string $host,
        string $clientCertificateFile,
        string $clientPrivateKeyFile,
        KmipVersion $version,
        int $port = self::DEFAULT_PORT,
        ?string $certificateAuthorityFile = null,
        ?string $peerName = null,
        #[\SensitiveParameter] ?string $passphrase = null,
        ?string $username = null,
        #[\SensitiveParameter] ?string $password = null,
        float $timeout = self::DEFAULT_TIMEOUT,
        KmipEncryptionSchemeRegistry $schemes = new KmipEncryptionSchemeRegistry([new AesGcmEncryptionScheme(), new ChaCha20Poly1305EncryptionScheme()]),
        string $cipher = self::DEFAULT_CIPHER,
    ): self {
        if ('' === $host || preg_match(self::HOST_CHARACTERS_REQUIRING_IP_VALIDATION_PATTERN, $host) && !filter_var($host, \FILTER_VALIDATE_IP)) {
            throw new InvalidArgumentException('The KMIP host is invalid.');
        }
        if ('' === $clientCertificateFile || '' === $clientPrivateKeyFile) {
            throw new InvalidArgumentException('KMIP requires nonempty "cert" and "key" paths.');
        }
        if ($port < self::MIN_PORT || $port > self::MAX_PORT) {
            throw new InvalidArgumentException(\sprintf('The KMIP port must be between %d and %d.', self::MIN_PORT, self::MAX_PORT));
        }
        if ('' === $certificateAuthorityFile || '' === $peerName) {
            throw new InvalidArgumentException('KMIP "ca" and "peer_name" must be nonempty when set.');
        }
        if (!is_finite($timeout) || $timeout <= 0) {
            throw new InvalidArgumentException('The KMIP timeout must be positive.');
        }
        if ((null === $username) !== (null === $password) || null !== $username && ('' === $username || '' === $password || !preg_match(self::UTF8_VALIDATION_PATTERN, $username) || !preg_match(self::UTF8_VALIDATION_PATTERN, $password))) {
            throw new InvalidArgumentException('KMIP credentials require a nonempty UTF-8 username and password pair.');
        }

        return new self(new ProtocolClient(new StreamTransport($host, $port, $clientCertificateFile, $clientPrivateKeyFile, $certificateAuthorityFile, $peerName ?? $host, $passphrase, $timeout), $version, $username, $password), $schemes, $cipher);
    }

    public function encrypt(string $keyId, #[\SensitiveParameter] string $plaintext, #[\SensitiveParameter] string $aad = '', bool $deterministic = false): Ciphertext
    {
        if ($deterministic) {
            throw new UnsupportedOperationException('KMIP encryption does not support deterministic encryption.');
        }
        self::validateKeyId($keyId);

        try {
            $schemeCiphertext = $this->encryptionScheme->encrypt($this->requestClient, $keyId, $plaintext, $this->ciphertextCodec->authenticatedData($this->encryptionScheme, $aad));
        } catch (OperationFailed $e) {
            if ($e->hasOperation && \in_array($e->reason, [self::REASON_ITEM_NOT_FOUND, self::REASON_OBJECT_NOT_FOUND], true)) {
                throw new KeyNotFoundException(self::safeKeyId($keyId), $e);
            }

            throw $e;
        }

        return $this->ciphertextCodec->encode($this->encryptionScheme, $schemeCiphertext);
    }

    public function decrypt(#[\SensitiveParameter] Ciphertext $ciphertext, #[\SensitiveParameter] string $aad = ''): string
    {
        try {
            self::validateKeyId($ciphertext->keyId);
        } catch (InvalidArgumentException) {
            throw new DecryptionFailedException();
        }
        [$scheme, $schemeCiphertext, $authenticatedData] = $this->ciphertextCodec->decode($ciphertext, $aad);

        try {
            return $scheme->decrypt($this->requestClient, $schemeCiphertext, $authenticatedData);
        } catch (FrameTooLarge) {
            throw new DecryptionFailedException();
        } catch (OperationFailed $e) {
            // PyKMIP also reports invalid authentication tags as General Failure, but that reason can mean a server error.
            if ($e->hasOperation && \in_array($e->reason, [self::REASON_ITEM_NOT_FOUND, self::REASON_CRYPTOGRAPHIC_FAILURE, self::REASON_OBJECT_DESTROYED, self::REASON_OBJECT_NOT_FOUND], true)) {
                throw new DecryptionFailedException();
            }

            throw $e;
        }
    }

    public function generateDataKey(string $keyId, int $length = self::DEFAULT_DATA_KEY_LENGTH, #[\SensitiveParameter] string $aad = ''): DataKey
    {
        if ($length < self::MIN_DATA_KEY_LENGTH || $length > Ttlv::MAX_FRAME - self::FRAME_SIZE_RESERVE) {
            throw new InvalidArgumentException(\sprintf('The KMIP data key length must be at least %d bytes and fit in one frame.', self::MIN_DATA_KEY_LENGTH));
        }
        $plaintext = random_bytes($length);

        return new DataKey($plaintext, $this->encrypt($keyId, $plaintext, $aad));
    }

    public function unwrapDataKey(#[\SensitiveParameter] Ciphertext $wrapped, #[\SensitiveParameter] string $aad = ''): DataKey
    {
        return new DataKey($this->decrypt($wrapped, $aad), $wrapped);
    }

    private static function validateKeyId(string $keyId): void
    {
        if ('' === $keyId || \strlen($keyId) > Ttlv::MAX_FRAME - self::FRAME_SIZE_RESERVE || !preg_match(self::UTF8_VALIDATION_PATTERN, $keyId)) {
            throw new InvalidArgumentException('The KMIP key identifier must be a nonempty UTF-8 Text String that fits in one frame.');
        }
    }

    private static function safeKeyId(string $keyId): string
    {
        preg_match('/^.{0,'.self::MAX_DISPLAY_KEY_ID_LENGTH.'}/us', $keyId, $display);

        return preg_replace_callback(self::CONTROL_OR_FORMAT_CHARACTER_PATTERN, self::escapeControlCharacter(...), $display[0] ?? '') ?? '';
    }

    /**
     * @param non-empty-list<string> $match
     */
    private static function escapeControlCharacter(array $match): string
    {
        return self::CONTROL_CHARACTER_ESCAPE_PREFIX.strtoupper(bin2hex($match[0]));
    }
}
