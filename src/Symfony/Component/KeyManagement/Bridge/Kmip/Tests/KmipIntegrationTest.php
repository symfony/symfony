<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\Kmip\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\KeyManagement\Bridge\Kmip\Kmip\OperationFailed;
use Symfony\Component\KeyManagement\Bridge\Kmip\Kmip\ProtocolClient;
use Symfony\Component\KeyManagement\Bridge\Kmip\Kmip\ProtocolVersion;
use Symfony\Component\KeyManagement\Bridge\Kmip\Kmip\StreamTransport;
use Symfony\Component\KeyManagement\Bridge\Kmip\Kmip\Ttlv;
use Symfony\Component\KeyManagement\Bridge\Kmip\KmipKms;
use Symfony\Component\KeyManagement\Bridge\Kmip\KmipKmsFactory;
use Symfony\Component\KeyManagement\Ciphertext;
use Symfony\Component\KeyManagement\Dsn;
use Symfony\Component\KeyManagement\EnvelopeEncrypter;
use Symfony\Component\KeyManagement\Exception\DecryptionFailedException;
use Symfony\Component\KeyManagement\Exception\KeyNotFoundException;
use Symfony\Component\KeyManagement\Exception\RuntimeException;

#[Group('integration')]
#[Group('kmip-integration')]
final class KmipIntegrationTest extends TestCase
{
    private const int TAG_ATTRIBUTE = 0x420008;
    private const int TAG_ATTRIBUTE_NAME = 0x42000A;
    private const int TAG_ATTRIBUTE_VALUE = 0x42000B;
    private const int TAG_BATCH_ITEM = 0x42000F;
    private const int TAG_CRYPTOGRAPHIC_ALGORITHM = 0x420028;
    private const int TAG_CRYPTOGRAPHIC_LENGTH = 0x42002A;
    private const int TAG_CRYPTOGRAPHIC_USAGE_MASK = 0x42002C;
    private const int TAG_OBJECT_TYPE = 0x420057;
    private const int TAG_OPERATION = 0x42005C;
    private const int TAG_PROTOCOL_VERSION = 0x420069;
    private const int TAG_PROTOCOL_VERSION_MAJOR = 0x42006A;
    private const int TAG_PROTOCOL_VERSION_MINOR = 0x42006B;
    private const int TAG_RESPONSE_MESSAGE = 0x42007B;
    private const int TAG_RESPONSE_PAYLOAD = 0x42007C;
    private const int TAG_RESULT_STATUS = 0x42007F;
    private const int TAG_REVOCATION_REASON = 0x420081;
    private const int TAG_REVOCATION_REASON_CODE = 0x420082;
    private const int TAG_TEMPLATE_ATTRIBUTE = 0x420091;
    private const int TAG_ATTRIBUTES = 0x420125;
    private const int OPERATION_ACTIVATE = 0x12;
    private const int OPERATION_CREATE = 0x01;
    private const int OPERATION_REVOKE = 0x13;
    private const int OPERATION_DESTROY = 0x14;
    private const int OPERATION_DISCOVER_VERSIONS = 0x1E;
    private const int STATUS_SUCCESS = 0;
    private const int REASON_PERMISSION_DENIED = 0x0C;
    private const int REASON_AUTHENTICATION_NOT_SUCCESSFUL = 3;
    private const int REASON_INVALID_MESSAGE = 4;
    private const int REASON_GENERAL_FAILURE = 0x100;
    private const int DEFAULT_PORT = 5696;
    private const int TIMEOUT_SECONDS = 10;
    private const int AES_ALGORITHM = 3;
    private const int CHACHA20_POLY1305_ALGORITHM = 0x1E;
    private const string EVIDEN_GCM_SIV_MODE = '0x80000002';
    private const int AES_KEY_LENGTH_BITS = 256;
    private const int DEFAULT_IV_LENGTH_BYTES = 12;
    private const int LONG_IV_LENGTH_BYTES = 16;
    private const int OBJECT_TYPE_SYMMETRIC_KEY = 2;
    private const int USAGE_ENCRYPT_ONLY = 4;
    private const int USAGE_ENCRYPT_AND_DECRYPT = 12;
    private const int REVOCATION_REASON_UNSPECIFIED = 1;
    private const int BULK_PAYLOAD_REPETITIONS = 8192;
    private const int RAW_IV_OFFSET = 1;
    private const int AUTHENTICATION_TAG_LENGTH = 16;
    private const int CIPHERTEXT_HEADER_LENGTH = 9;

    private string $host;
    private int $port;
    private array $tlsOptions;
    private array $keys = [];

    protected function setUp(): void
    {
        $host = getenv('KMIP_TEST_HOST');
        if (!$host) {
            if ('1' === getenv('KMIP_TEST_REQUIRED')) {
                $this->fail('KMIP_TEST_HOST is required.');
            }
            $this->markTestSkipped('KMIP fixture is not running.');
        }
        if ('1' === getenv('KMIP_TEST_REQUIRED')) {
            foreach (['KMIP_TEST_CERT', 'KMIP_TEST_KEY', 'KMIP_TEST_CA', 'KMIP_TEST_PEER_NAME'] as $name) {
                $this->assertNotEmpty(getenv($name), $name.' is required.');
            }
        }
        $this->host = $host;
        $this->port = (int) (getenv('KMIP_TEST_PORT') ?: self::DEFAULT_PORT);
        $this->tlsOptions = [
            'cert' => (string) getenv('KMIP_TEST_CERT'),
            'key' => (string) getenv('KMIP_TEST_KEY'),
            'ca' => (string) getenv('KMIP_TEST_CA'),
            'peer_name' => (string) getenv('KMIP_TEST_PEER_NAME'),
        ];
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->keys, true) as $keyId => [$protocol, $active]) {
            if ($active) {
                $this->revokeKey($protocol, $keyId);
            }
            $protocol->request(self::OPERATION_DESTROY, Ttlv::text(ProtocolClient::UNIQUE_IDENTIFIER, $keyId));
        }
    }

    public function testServerAdvertisesSupportedVersion()
    {
        $version = ProtocolVersion::Version20;
        $versions = $this->discoverVersions($this->protocol($version), $version);

        foreach ($this->supportedVersions() as $expected) {
            $this->assertContains($expected->value, $versions);
        }
    }

    public function testUntrustedServerCertificateFailureIdentifiesTheTlsProblem()
    {
        $transport = $this->transport(['ca' => $this->tlsOptions['cert']]);

        try {
            $transport->exchange('request');
            $this->fail('An untrusted server certificate must be rejected.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Could not establish a verified KMIP TLS connection', $e->getMessage());
            $this->assertStringContainsString('certificate verify failed', $e->getMessage());
            $this->assertStringNotContainsString('socket error 0', $e->getMessage());
        }
    }

    #[DataProvider('configurations')]
    public function testAuthenticatedEncryptionAndCrossVersionDecryption(ProtocolVersion $version, string $cipher, int $ivLength)
    {
        [, $keyId, $kms] = $this->createKms($version, $cipher, $ivLength);

        foreach (['', 'x', "hello\0world", str_repeat("\0binary\xFF", self::BULK_PAYLOAD_REPETITIONS)] as $plaintext) {
            $encrypted = $kms->encrypt($keyId, $plaintext, "aad\0bytes");

            $this->assertSame($keyId, $encrypted->keyId);
            $this->assertStringStartsWith(self::header($cipher, $ivLength), $encrypted->blob);
            $this->assertSame($plaintext, $kms->decrypt($encrypted, "aad\0bytes"));
            $this->assertSame($plaintext, $kms->decrypt($kms->encrypt($keyId, $plaintext)));
            $this->assertNotSame($encrypted->blob, $kms->encrypt($keyId, $plaintext, "aad\0bytes")->blob);
        }

        $encrypted = $kms->encrypt($keyId, 'cross-version plaintext', 'context');
        foreach ($this->supportedVersions() as $otherVersion) {
            $this->assertSame('cross-version plaintext', $this->kms($otherVersion, $cipher)->decrypt($encrypted, 'context'));
        }
    }

    #[DataProvider('configurations')]
    public function testStoredCipherAndIvLengthSurviveConfigurationChanges(ProtocolVersion $version, string $cipher, int $ivLength)
    {
        [$protocol, $keyId, $kms] = $this->createKms($version, $cipher, $ivLength);
        $encrypted = $kms->encrypt($keyId, 'selected cipher', 'context');
        $otherIvLength = self::DEFAULT_IV_LENGTH_BYTES === $ivLength ? self::LONG_IV_LENGTH_BYTES : self::DEFAULT_IV_LENGTH_BYTES;

        $this->assertSame('selected cipher', $this->kms($version, 'aes-gcm', $otherIvLength)->decrypt($encrypted, 'context'));

        $aesKeyId = $this->provisionKey($protocol, $version);
        $earlier = $this->kms($version, 'aes-gcm', $otherIvLength)->encrypt($aesKeyId, 'earlier AES-GCM ciphertext', 'context');

        $this->assertStringStartsWith(self::header('aes-gcm', $otherIvLength), $earlier->blob);
        $this->assertSame('earlier AES-GCM ciphertext', $kms->decrypt($earlier, 'context'));
    }

    #[DataProvider('configurations')]
    public function testTamperedCiphertextAndAssociatedDataAreRejected(ProtocolVersion $version, string $cipher, int $ivLength)
    {
        [$protocol, $keyId, $kms] = $this->createKms($version, $cipher, $ivLength);
        $encrypted = $kms->encrypt($keyId, 'authenticated plaintext', 'context');
        $ivOffset = self::CIPHERTEXT_HEADER_LENGTH + self::RAW_IV_OFFSET;
        $tagOffset = $ivOffset + $ivLength;
        $dataOffset = $tagOffset + self::AUTHENTICATION_TAG_LENGTH;

        foreach (['IV' => $ivOffset, 'authentication tag' => $tagOffset, 'encrypted data' => $dataOffset] as $offset) {
            $blob = $encrypted->blob;
            $blob[$offset] = \chr(\ord($blob[$offset]) ^ 1);

            $this->expectAuthenticationFailure(static fn () => $kms->decrypt(new Ciphertext($blob, $keyId), 'context'));
        }
        foreach (['format' => 0, 'algorithm' => 4, 'block cipher mode' => 8] as $offset) {
            $blob = $encrypted->blob;
            $blob[$offset] = \chr(\ord($blob[$offset]) ^ 1);

            $this->expectDecryptionFailure(static fn () => $kms->decrypt(new Ciphertext($blob, $keyId), 'context'));
        }
        $blob = $encrypted->blob;
        $blob[self::CIPHERTEXT_HEADER_LENGTH] = "\0";
        $this->expectDecryptionFailure(static fn () => $kms->decrypt(new Ciphertext($blob, $keyId), 'context'));
        if ('aes-gcm' === $cipher) {
            $blob[self::CIPHERTEXT_HEADER_LENGTH] = \chr(self::DEFAULT_IV_LENGTH_BYTES === $ivLength ? self::LONG_IV_LENGTH_BYTES : self::DEFAULT_IV_LENGTH_BYTES);
            $this->expectAuthenticationFailure(static fn () => $kms->decrypt(new Ciphertext($blob, $keyId), 'context'));
        }

        $alternateKeyId = $this->provisionKey($protocol, $version, self::USAGE_ENCRYPT_AND_DECRYPT, true, self::algorithm($cipher));
        $this->expectAuthenticationFailure(static fn () => $kms->decrypt(new Ciphertext($encrypted->blob, $alternateKeyId), 'context'));
        $this->expectDecryptionFailure(static fn () => $kms->decrypt(new Ciphertext($encrypted->blob, 'unknown-key'), 'context'));
        $this->expectAuthenticationFailure(static fn () => $kms->decrypt($encrypted, 'wrong context'));
        $this->expectAuthenticationFailure(static fn () => $kms->decrypt($encrypted));
        $this->expectAuthenticationFailure(static fn () => $kms->decrypt($kms->encrypt($keyId, 'without aad'), 'extra aad'));
    }

    #[DataProvider('configurations')]
    public function testDataKeyGenerationWrappingAndUnwrapping(ProtocolVersion $version, string $cipher, int $ivLength)
    {
        [, $keyId, $kms] = $this->createKms($version, $cipher, $ivLength);

        foreach ([16, 32, 64] as $length) {
            foreach (['', 'wrapped aad'] as $aad) {
                $generated = $kms->generateDataKey($keyId, $length, $aad);
                $plaintext = $generated->use(static fn (string $bytes): string => $bytes);

                $this->assertSame($length, \strlen($plaintext));
                $this->assertStringStartsWith(self::header($cipher, $ivLength), $generated->wrapped->blob);
                $this->assertSame($plaintext, $kms->unwrapDataKey($generated->wrapped, $aad)->use(static fn (string $bytes): string => $bytes));
                $wrapped = $kms->wrapDataKey($keyId, $plaintext, $aad);
                $this->assertSame($plaintext, $kms->unwrapDataKey($wrapped, $aad)->use(static fn (string $bytes): string => $bytes));
                $this->expectAuthenticationFailure(static fn () => $kms->unwrapDataKey($wrapped, 'wrong context'));
            }
        }
        $ordinaryWrapped = $kms->encrypt($keyId, random_bytes(32), 'rewrap');
        $this->assertSame($kms->decrypt($ordinaryWrapped, 'rewrap'), $kms->unwrapDataKey($ordinaryWrapped, 'rewrap')->use(static fn (string $bytes): string => $bytes));
    }

    #[DataProvider('configurations')]
    public function testEnvelopeEncryption(ProtocolVersion $version, string $cipher, int $ivLength)
    {
        [, $keyId, $kms] = $this->createKms($version, $cipher, $ivLength);
        $envelopes = new EnvelopeEncrypter($kms);
        $plaintext = str_repeat("bulk\0payload\xFF", self::BULK_PAYLOAD_REPETITIONS);
        $encrypted = $envelopes->encrypt($keyId, $plaintext, 'context');

        $this->assertSame($plaintext, $envelopes->decrypt($encrypted, 'context'));
        $this->expectDecryptionFailure(static fn () => $envelopes->decrypt($encrypted, 'wrong context'));
    }

    #[DataProvider('configurations')]
    public function testUsernameAndPasswordCredentials(ProtocolVersion $version, string $cipher, int $ivLength)
    {
        [, $keyId] = $this->createKms($version, $cipher, $ivLength);
        $credentialed = $this->kms($version, $cipher, $ivLength, [], 'user:password@');
        $encrypted = $credentialed->encrypt($keyId, 'credentialed', 'aad');

        $this->assertSame('credentialed', $credentialed->decrypt($encrypted, 'aad'));
        $this->assertStringStartsWith(self::header($cipher, $ivLength), $encrypted->blob);
    }

    public function testEncryptedClientPrivateKey()
    {
        $key = $this->fixtureOption('KMIP_TEST_ENCRYPTED_KEY');
        $passphrase = $this->fixtureOption('KMIP_TEST_KEY_PASSPHRASE');
        [, $keyId] = $this->createKms(ProtocolVersion::Version20, 'aes-gcm', self::DEFAULT_IV_LENGTH_BYTES);
        $kms = $this->kms(ProtocolVersion::Version20, 'aes-gcm', self::DEFAULT_IV_LENGTH_BYTES, ['key' => $key, 'passphrase' => $passphrase]);

        $this->assertSame('encrypted key', $kms->decrypt($kms->encrypt($keyId, 'encrypted key')));
        try {
            $this->kms(ProtocolVersion::Version20, 'aes-gcm', self::DEFAULT_IV_LENGTH_BYTES, ['key' => $key, 'passphrase' => 'wrong-private-passphrase'])->encrypt($keyId, 'tls');
            $this->fail('The wrong private-key passphrase must fail.');
        } catch (RuntimeException $e) {
            $this->assertStringNotContainsString('wrong-private-passphrase', $e->getMessage());
        }
    }

    #[DataProvider('configurations')]
    public function testAnotherClientIdentityCannotDecrypt(ProtocolVersion $version, string $cipher, int $ivLength)
    {
        $options = ['cert' => $this->fixtureOption('KMIP_TEST_OTHER_CERT'), 'key' => $this->fixtureOption('KMIP_TEST_OTHER_KEY')];
        [, $keyId, $kms] = $this->createKms($version, $cipher, $ivLength);
        $encrypted = $kms->encrypt($keyId, 'private plaintext', 'context');
        $otherOwner = $this->kms($version, $cipher, $ivLength, $options);

        $this->assertSame('private plaintext', $kms->decrypt($encrypted, 'context'));
        if (self::isEviden()) {
            $this->expectDecryptionFailure(static fn () => $otherOwner->decrypt($encrypted, 'context'));

            return;
        }
        try {
            $otherOwner->decrypt($encrypted, 'context');
            $this->fail('Another client identity must be denied.');
        } catch (OperationFailed $e) {
            $this->assertSame(self::REASON_PERMISSION_DENIED, $e->reason);
        }
    }

    #[DataProvider('configurations')]
    public function testKeyUsageAndLifecycle(ProtocolVersion $version, string $cipher, int $ivLength)
    {
        [$protocol, , $kms] = $this->createKms($version, $cipher, $ivLength);
        $algorithm = self::algorithm($cipher);
        $encryptOnlyKeyId = $this->provisionKey($protocol, $version, self::USAGE_ENCRYPT_ONLY, true, $algorithm);
        $restrictedCiphertext = $kms->encrypt($encryptOnlyKeyId, 'restricted');
        if (self::isEviden()) {
            $this->expectDecryptionFailure(static fn () => $kms->decrypt($restrictedCiphertext));
        } else {
            $this->expectOperationalFailure(static fn () => $kms->decrypt($restrictedCiphertext));
        }
        $preActiveKeyId = $this->provisionKey($protocol, $version, self::USAGE_ENCRYPT_AND_DECRYPT, false, $algorithm);
        $this->expectOperationalFailure(static fn () => $kms->encrypt($preActiveKeyId, 'inactive'));
        $revokedKeyId = $this->provisionKey($protocol, $version, self::USAGE_ENCRYPT_AND_DECRYPT, true, $algorithm);
        $revokedCiphertext = $kms->encrypt($revokedKeyId, 'revoked');
        $this->revokeKey($protocol, $revokedKeyId);
        $this->expectOperationalFailure(static fn () => $kms->encrypt($revokedKeyId, 'after revoke'));
        if (self::isEviden()) {
            $this->assertSame('revoked', $kms->decrypt($revokedCiphertext));
        } else {
            $this->expectOperationalFailure(static fn () => $kms->decrypt($revokedCiphertext));
        }
    }

    public function testUnknownKeyIdentifiersAreEscaped()
    {
        foreach ($this->supportedVersions() as $version) {
            try {
                $this->kms($version)->encrypt("missing\n\x1B[31m", 'data');
                $this->fail('An unknown key must fail.');
            } catch (KeyNotFoundException $e) {
                $this->assertStringContainsString('\\x0A', $e->getMessage());
                $this->assertStringContainsString('\\x1B', $e->getMessage());
                $this->assertStringNotContainsString("\n", $e->getMessage());
            }
        }
    }

    public function testClientWithoutExtendedKeyUsageCannotUseAnotherClientsKey()
    {
        [, $keyId, $owner] = $this->createKms(ProtocolVersion::Version20, 'aes-gcm', self::DEFAULT_IV_LENGTH_BYTES);
        $kms = $this->kms(ProtocolVersion::Version20, 'aes-gcm', self::DEFAULT_IV_LENGTH_BYTES, [
            'cert' => $this->fixtureOption('KMIP_TEST_NO_EKU_CERT'),
            'key' => $this->fixtureOption('KMIP_TEST_NO_EKU_KEY'),
        ]);

        $this->assertSame('owner', $owner->decrypt($owner->encrypt($keyId, 'owner')));
        if (self::isEviden()) {
            $this->expectException(KeyNotFoundException::class);
            $kms->encrypt($keyId, 'tls');

            return;
        }
        try {
            $kms->encrypt($keyId, 'tls');
            $this->fail('A client without clientAuth EKU must be denied.');
        } catch (OperationFailed $e) {
            $this->assertSame(self::REASON_AUTHENTICATION_NOT_SUCCESSFUL, $e->reason);
            $this->assertFalse($e->hasOperation);
        }
    }

    #[DataProvider('invalidTlsOptions')]
    public function testTlsVerificationCannotBeBypassed(array $options)
    {
        if (isset($options['ca'])) {
            $options['ca'] = $this->tlsOptions['cert'];
        }
        $kms = $this->kms(ProtocolVersion::Version20, 'aes-gcm', self::DEFAULT_IV_LENGTH_BYTES, $options);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Could not establish a verified KMIP TLS connection');
        $kms->encrypt('key', 'plaintext');
    }

    public static function invalidTlsOptions(): iterable
    {
        yield 'wrong peer name' => [['peer_name' => 'wrong.example']];
        yield 'host does not match certificate' => [['peer_name' => null]];
        yield 'untrusted certificate authority' => [['ca' => 'client certificate']];
    }

    public function testAnUnconfiguredVendorModeCannotDecryptStoredCiphertext()
    {
        [, $keyId, $kms] = $this->createKms(ProtocolVersion::Version20, 'aes-gcm-siv', self::DEFAULT_IV_LENGTH_BYTES);
        $encrypted = $kms->encrypt($keyId, 'plaintext', 'context');

        foreach ([null, '0x80001234'] as $mode) {
            $reader = $this->kms(ProtocolVersion::Version20, 'aes-gcm', self::DEFAULT_IV_LENGTH_BYTES, ['mode' => $mode]);
            $this->expectDecryptionFailure(static fn () => $reader->decrypt($encrypted, 'context'));
        }
    }

    public function testVersion21IsRejectedWithoutAffectingVersion20()
    {
        if (self::isEviden()) {
            $this->markTestSkipped('Only the PyKMIP fixture is expected to reject KMIP 2.1.');
        }
        [, $keyId, $version20Kms] = $this->createKms(ProtocolVersion::Version20, 'aes-gcm', self::DEFAULT_IV_LENGTH_BYTES);
        $version21Kms = $this->kms(ProtocolVersion::Version21);

        try {
            $version21Kms->encrypt($keyId, 'unsupported');
            $this->fail('PyKMIP must reject KMIP 2.1.');
        } catch (OperationFailed $e) {
            $this->assertSame(self::REASON_INVALID_MESSAGE, $e->reason);
            $this->assertFalse($e->hasOperation);
        }

        $this->assertSame('working', $version20Kms->decrypt($version20Kms->encrypt($keyId, 'working')));
    }

    public static function configurations(): iterable
    {
        foreach (ProtocolVersion::cases() as $version) {
            foreach (['aes-gcm', 'chacha20-poly1305', 'aes-gcm-siv'] as $cipher) {
                yield $version->value.' '.$cipher => [$version, $cipher, self::DEFAULT_IV_LENGTH_BYTES];
            }
        }
        yield '2.0 aes-gcm with a 16-byte IV' => [ProtocolVersion::Version20, 'aes-gcm', self::LONG_IV_LENGTH_BYTES];
    }

    private function createKms(ProtocolVersion $version, string $cipher, int $ivLength): array
    {
        if (!self::isEviden() && (ProtocolVersion::Version21 === $version || 'aes-gcm' !== $cipher)) {
            $this->markTestSkipped('KMIP 2.1, ChaCha20-Poly1305 and AES-GCM-SIV require the Eviden fixture.');
        }
        $protocol = $this->protocol($version);
        $keyId = $this->provisionKey($protocol, $version, self::USAGE_ENCRYPT_AND_DECRYPT, true, self::algorithm($cipher));

        return [$protocol, $keyId, $this->kms($version, $cipher, $ivLength)];
    }

    private function protocol(ProtocolVersion $version): ProtocolClient
    {
        return new ProtocolClient($this->transport(), $version, null, null);
    }

    private function transport(array $options = []): StreamTransport
    {
        $options += $this->tlsOptions;

        return new StreamTransport($this->host, $this->port, $options['cert'], $options['key'], $options['ca'], $options['peer_name'], null, self::TIMEOUT_SECONDS);
    }

    private function kms(ProtocolVersion $version, string $cipher = 'aes-gcm', int $ivLength = self::DEFAULT_IV_LENGTH_BYTES, array $options = [], string $credentials = ''): KmipKms
    {
        $options += ['cipher' => $cipher, 'version' => $version->value] + $this->tlsOptions;
        if ('aes-gcm' === $cipher) {
            $options += ['iv_length' => $ivLength];
        }
        if (self::isEviden()) {
            $options += ['mode' => self::EVIDEN_GCM_SIV_MODE];
        }
        $options = array_filter($options, static fn ($value) => null !== $value);
        $host = str_contains($this->host, ':') ? '['.$this->host.']' : $this->host;

        return (new KmipKmsFactory())->create(Dsn::fromString('kmip://'.$credentials.$host.':'.$this->port.'?'.http_build_query($options)));
    }

    private function provisionKey(ProtocolClient $protocol, ProtocolVersion $version, int $usageMask = self::USAGE_ENCRYPT_AND_DECRYPT, bool $activate = true, int $algorithm = self::AES_ALGORITHM): string
    {
        if (ProtocolVersion::Version14 === $version) {
            $attributes = '';
            foreach ([
                ['Cryptographic Algorithm', Ttlv::ENUMERATION, $algorithm],
                ['Cryptographic Length', Ttlv::INTEGER, self::AES_KEY_LENGTH_BITS],
                ['Cryptographic Usage Mask', Ttlv::INTEGER, $usageMask],
            ] as [$name, $type, $value]) {
                $attributes .= Ttlv::structure(self::TAG_ATTRIBUTE, Ttlv::text(self::TAG_ATTRIBUTE_NAME, $name).Ttlv::integer(self::TAG_ATTRIBUTE_VALUE, $value, $type));
            }
            $attributes = Ttlv::structure(self::TAG_TEMPLATE_ATTRIBUTE, $attributes);
        } else {
            $attributes = Ttlv::structure(self::TAG_ATTRIBUTES,
                Ttlv::integer(self::TAG_CRYPTOGRAPHIC_ALGORITHM, $algorithm, Ttlv::ENUMERATION)
                .Ttlv::integer(self::TAG_CRYPTOGRAPHIC_LENGTH, self::AES_KEY_LENGTH_BITS)
                .Ttlv::integer(self::TAG_CRYPTOGRAPHIC_USAGE_MASK, $usageMask)
            );
        }
        $created = $protocol->request(self::OPERATION_CREATE, Ttlv::integer(self::TAG_OBJECT_TYPE, self::OBJECT_TYPE_SYMMETRIC_KEY, Ttlv::ENUMERATION).$attributes);
        $keyId = Ttlv::requireType($created[ProtocolClient::UNIQUE_IDENTIFIER], ProtocolClient::UNIQUE_IDENTIFIER, Ttlv::TEXT);
        $this->keys[$keyId] = [$protocol, false];
        if ($activate) {
            $protocol->request(self::OPERATION_ACTIVATE, Ttlv::text(ProtocolClient::UNIQUE_IDENTIFIER, $keyId));
            $this->keys[$keyId][1] = true;
        }

        return $keyId;
    }

    private function revokeKey(ProtocolClient $protocol, string $keyId): void
    {
        $protocol->request(self::OPERATION_REVOKE, Ttlv::text(ProtocolClient::UNIQUE_IDENTIFIER, $keyId).Ttlv::structure(self::TAG_REVOCATION_REASON, Ttlv::integer(self::TAG_REVOCATION_REASON_CODE, self::REVOCATION_REASON_UNSPECIFIED, Ttlv::ENUMERATION)));
        $this->keys[$keyId][1] = false;
    }

    private function discoverVersions(ProtocolClient $protocol, ProtocolVersion $version): array
    {
        $frame = $this->transport()->exchange($protocol->buildRequest(self::OPERATION_DISCOVER_VERSIONS, ''));
        $message = Ttlv::children(Ttlv::items($frame, $version)[0], self::TAG_RESPONSE_MESSAGE, $version);
        $batch = Ttlv::children($message[1], self::TAG_BATCH_ITEM, $version);
        $fields = [];
        foreach ($batch as $item) {
            $fields[$item['tag']] = $item;
        }
        if (!isset($fields[self::TAG_OPERATION], $fields[self::TAG_RESULT_STATUS], $fields[self::TAG_RESPONSE_PAYLOAD]) || self::OPERATION_DISCOVER_VERSIONS !== Ttlv::number($fields[self::TAG_OPERATION], self::TAG_OPERATION, Ttlv::ENUMERATION) || self::STATUS_SUCCESS !== Ttlv::number($fields[self::TAG_RESULT_STATUS], self::TAG_RESULT_STATUS, Ttlv::ENUMERATION)) {
            throw new RuntimeException('KMIP DiscoverVersions did not succeed.');
        }
        $payload = Ttlv::children($fields[self::TAG_RESPONSE_PAYLOAD], self::TAG_RESPONSE_PAYLOAD, $version);
        $versions = [];
        foreach ($payload as $item) {
            $parts = Ttlv::children($item, self::TAG_PROTOCOL_VERSION, $version);
            $versions[] = Ttlv::number($parts[0], self::TAG_PROTOCOL_VERSION_MAJOR).'.'.Ttlv::number($parts[1], self::TAG_PROTOCOL_VERSION_MINOR);
        }

        return $versions;
    }

    private function expectDecryptionFailure(\Closure $call): void
    {
        try {
            $call();
            $this->fail('Expected decryption to fail.');
        } catch (DecryptionFailedException) {
            $this->assertTrue(true);
        }
    }

    private function expectAuthenticationFailure(\Closure $call): void
    {
        if (self::isEviden()) {
            $this->expectDecryptionFailure($call);

            return;
        }
        try {
            $call();
            $this->fail('Expected authentication to fail.');
        } catch (OperationFailed $e) {
            // PyKMIP reports an invalid authentication tag as General Failure rather than Cryptographic Failure.
            $this->assertSame(self::REASON_GENERAL_FAILURE, $e->reason);
        }
    }

    private function expectOperationalFailure(\Closure $call): void
    {
        try {
            $call();
            $this->fail('Expected an operational failure.');
        } catch (RuntimeException $e) {
            $this->assertNotInstanceOf(DecryptionFailedException::class, $e);
        }
    }

    private function fixtureOption(string $name): string
    {
        $value = getenv($name);
        if (false === $value || '' === $value) {
            if ('1' === getenv('KMIP_TEST_REQUIRED')) {
                $this->fail($name.' is required.');
            }
            $this->markTestSkipped($name.' is not configured.');
        }

        return $value;
    }

    private function supportedVersions(): array
    {
        return self::isEviden() ? ProtocolVersion::cases() : [ProtocolVersion::Version14, ProtocolVersion::Version20];
    }

    private static function isEviden(): bool
    {
        return 'eviden' === getenv('KMIP_TEST_SERVER');
    }

    private static function algorithm(string $cipher): int
    {
        return 'chacha20-poly1305' === $cipher ? self::CHACHA20_POLY1305_ALGORITHM : self::AES_ALGORITHM;
    }

    private static function header(string $cipher, int $ivLength): string
    {
        return hex2bin(match ($cipher) {
            'aes-gcm' => '010000000300000009',
            'chacha20-poly1305' => '010000001e00000000',
            'aes-gcm-siv' => '010000000380000002',
        }).\chr($ivLength);
    }
}
