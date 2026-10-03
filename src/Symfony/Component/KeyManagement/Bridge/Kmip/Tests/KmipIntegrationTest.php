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
use Symfony\Component\KeyManagement\Bridge\Kmip\Kmip\AesGcmEncryptionScheme;
use Symfony\Component\KeyManagement\Bridge\Kmip\Kmip\AesGcmSivEncryptionScheme;
use Symfony\Component\KeyManagement\Bridge\Kmip\Kmip\ChaCha20Poly1305EncryptionScheme;
use Symfony\Component\KeyManagement\Bridge\Kmip\Kmip\OperationFailed;
use Symfony\Component\KeyManagement\Bridge\Kmip\Kmip\ProtocolClient;
use Symfony\Component\KeyManagement\Bridge\Kmip\Kmip\StreamTransport;
use Symfony\Component\KeyManagement\Bridge\Kmip\Kmip\Ttlv;
use Symfony\Component\KeyManagement\Bridge\Kmip\KmipEncryptionSchemeRegistry;
use Symfony\Component\KeyManagement\Bridge\Kmip\KmipKms;
use Symfony\Component\KeyManagement\Bridge\Kmip\KmipKmsFactory;
use Symfony\Component\KeyManagement\Bridge\Kmip\KmipVersion;
use Symfony\Component\KeyManagement\Ciphertext;
use Symfony\Component\KeyManagement\Dsn;
use Symfony\Component\KeyManagement\EnvelopeEncrypter;
use Symfony\Component\KeyManagement\Exception\DecryptionFailedException;
use Symfony\Component\KeyManagement\Exception\KeyNotFoundException;
use Symfony\Component\KeyManagement\Exception\RuntimeException;
use Symfony\Component\KeyManagement\Exception\UnsupportedOperationException;

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
    private const string EVIDEN_GCM_SIV_BLOCK_CIPHER_MODE = '0x80000002';
    private const int AES_KEY_LENGTH_BITS = 256;
    private const int AES_GCM_DEFAULT_IV_LENGTH_BYTES = 16;
    private const int AES_GCM_SHORT_IV_LENGTH_BYTES = 12;
    private const string AES_CIPHER = 'aes-gcm';
    private const string AES_GCM_SIV_CIPHER = 'aes-gcm-siv';
    private const string CHACHA20_POLY1305_CIPHER = 'chacha20-poly1305';
    private const string EVIDEN_SERVER = 'eviden';
    private const string PYKMIP_SERVER = 'pykmip';
    private const int OBJECT_TYPE_SYMMETRIC_KEY = 2;
    private const int USAGE_ENCRYPT_ONLY = 4;
    private const int USAGE_ENCRYPT_AND_DECRYPT = 12;
    private const int REVOCATION_REASON_UNSPECIFIED = 1;
    private const int BULK_PAYLOAD_REPETITIONS = 8192;
    private const int RAW_IV_LENGTH_OFFSET = 0;
    private const int RAW_IV_OFFSET = 1;
    private const int AUTHENTICATION_TAG_LENGTH = 16;
    private const int NAMED_CIPHERTEXT_PREFIX_LENGTH = 2;
    private const string NAMED_CIPHERTEXT_FORMAT = "\x01";

    public function testServerAdvertisesSupportedVersion()
    {
        $host = getenv('KMIP_TEST_HOST');
        if (!$host) {
            if ('1' === getenv('KMIP_TEST_REQUIRED')) {
                $this->fail('KMIP_TEST_HOST is required.');
            }
            $this->markTestSkipped('KMIP fixture is not running.');
        }
        $version = KmipVersion::Version20;
        $clientCertificateFile = (string) getenv('KMIP_TEST_CERT');
        $clientPrivateKeyFile = (string) getenv('KMIP_TEST_KEY');
        $certificateAuthorityFile = (string) getenv('KMIP_TEST_CA');
        $peerName = (string) getenv('KMIP_TEST_PEER_NAME');
        $port = (int) (getenv('KMIP_TEST_PORT') ?: self::DEFAULT_PORT);
        $protocol = new ProtocolClient(new StreamTransport($host, $port, $clientCertificateFile, $clientPrivateKeyFile, $certificateAuthorityFile, $peerName, null, self::TIMEOUT_SECONDS), $version, null, null);

        $this->assertContains(self::isEviden() ? KmipVersion::Version21->value : $version->value, self::discoverVersions($protocol, $version, $host, $port, $clientCertificateFile, $clientPrivateKeyFile, $certificateAuthorityFile, $peerName));
    }

    public function testUntrustedServerCertificateFailureIdentifiesTheTlsProblem()
    {
        $host = getenv('KMIP_TEST_HOST');
        if (!$host) {
            if ('1' === getenv('KMIP_TEST_REQUIRED')) {
                $this->fail('KMIP_TEST_HOST is required.');
            }
            $this->markTestSkipped('KMIP fixture is not running.');
        }
        $clientCertificateFile = (string) getenv('KMIP_TEST_CERT');
        $clientPrivateKeyFile = (string) getenv('KMIP_TEST_KEY');
        $peerName = (string) getenv('KMIP_TEST_PEER_NAME');
        $port = (int) (getenv('KMIP_TEST_PORT') ?: self::DEFAULT_PORT);
        $transport = new StreamTransport($host, $port, $clientCertificateFile, $clientPrivateKeyFile, $clientCertificateFile, $peerName, null, self::TIMEOUT_SECONDS);

        try {
            $transport->exchange('request');
            $this->fail('An untrusted server certificate must be rejected.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Could not establish a verified KMIP TLS connection', $e->getMessage());
            $this->assertStringContainsString('certificate verify failed', $e->getMessage());
            $this->assertStringNotContainsString('socket error 0', $e->getMessage());
        }
    }

    #[DataProvider('versions')]
    public function testVersionedOperations(KmipVersion $version, string $cipher = self::AES_CIPHER, int $aesGcmIvLength = self::AES_GCM_DEFAULT_IV_LENGTH_BYTES)
    {
        if (self::AES_CIPHER !== $cipher && !self::isEviden()) {
            $this->markTestSkipped('The selected cipher requires the Eviden fixture.');
        }
        if (KmipVersion::Version21 === $version && !self::isEviden()) {
            $this->markTestSkipped('KMIP 2.1 requires the Eviden fixture.');
        }
        $host = getenv('KMIP_TEST_HOST');
        if (!$host) {
            if ('1' === getenv('KMIP_TEST_REQUIRED')) {
                $this->fail('KMIP_TEST_HOST is required.');
            }
            $this->markTestSkipped('KMIP fixture is not running.');
        }
        if ('1' === getenv('KMIP_TEST_REQUIRED')) {
            foreach (['KMIP_TEST_CERT', 'KMIP_TEST_KEY', 'KMIP_TEST_CA', 'KMIP_TEST_PEER_NAME', 'KMIP_TEST_OTHER_CERT', 'KMIP_TEST_OTHER_KEY', 'KMIP_TEST_NO_EKU_CERT', 'KMIP_TEST_NO_EKU_KEY', 'KMIP_TEST_ENCRYPTED_KEY', 'KMIP_TEST_KEY_PASSPHRASE'] as $name) {
                $this->assertNotEmpty(getenv($name), $name.' is required.');
            }
        }
        $clientCertificateFile = (string) getenv('KMIP_TEST_CERT');
        $clientPrivateKeyFile = (string) getenv('KMIP_TEST_KEY');
        $certificateAuthorityFile = (string) getenv('KMIP_TEST_CA');
        $peerName = (string) getenv('KMIP_TEST_PEER_NAME');
        $port = (int) (getenv('KMIP_TEST_PORT') ?: self::DEFAULT_PORT);
        $protocol = new ProtocolClient(new StreamTransport($host, $port, $clientCertificateFile, $clientPrivateKeyFile, $certificateAuthorityFile, $peerName, null, self::TIMEOUT_SECONDS), $version, null, null);
        $versions = self::discoverVersions($protocol, $version, $host, $port, $clientCertificateFile, $clientPrivateKeyFile, $certificateAuthorityFile, $peerName);
        $this->assertContains(KmipVersion::Version14->value, $versions);
        $this->assertContains(KmipVersion::Version20->value, $versions);
        $this->assertContains($version->value, $versions);
        $algorithm = self::CHACHA20_POLY1305_CIPHER === $cipher ? self::CHACHA20_POLY1305_ALGORITHM : self::AES_ALGORITHM;
        $keyId = self::provisionKey($protocol, $version, self::USAGE_ENCRYPT_AND_DECRYPT, true, $algorithm);
        $kms = KmipKms::fromTls($host, $clientCertificateFile, $clientPrivateKeyFile, $version, $port, $certificateAuthorityFile, $peerName, null, null, null, self::TIMEOUT_SECONDS, self::schemes($aesGcmIvLength), $cipher);

        $ciphertext = $kms->encrypt($keyId, "hello\0world", "aad\0bytes");
        $this->assertSame($keyId, $ciphertext->keyId);
        $this->assertStringStartsWith(self::NAMED_CIPHERTEXT_FORMAT.\chr(\strlen($cipher)).$cipher, $ciphertext->blob);
        $this->assertSame("hello\0world", $kms->decrypt($ciphertext, "aad\0bytes"));
        $this->assertNotSame($ciphertext->blob, $kms->encrypt($keyId, "hello\0world", "aad\0bytes")->blob);
        if (self::AES_CIPHER === $cipher) {
            $otherIvLength = self::AES_GCM_DEFAULT_IV_LENGTH_BYTES === $aesGcmIvLength ? self::AES_GCM_SHORT_IV_LENGTH_BYTES : self::AES_GCM_DEFAULT_IV_LENGTH_BYTES;
            $otherIvKms = KmipKms::fromTls($host, $clientCertificateFile, $clientPrivateKeyFile, $version, $port, $certificateAuthorityFile, $peerName, null, null, null, self::TIMEOUT_SECONDS, self::schemes($otherIvLength));
            $otherIvCiphertext = $otherIvKms->encrypt($keyId, 'other IV', 'aad');
            $this->assertSame('other IV', $kms->decrypt($otherIvCiphertext, 'aad'));
            $this->assertSame('selected IV', $otherIvKms->decrypt($kms->encrypt($keyId, 'selected IV', 'aad'), 'aad'));
            $this->assertStringStartsWith(self::NAMED_CIPHERTEXT_FORMAT.\chr(\strlen(self::AES_CIPHER)).self::AES_CIPHER, $otherIvCiphertext->blob);
        }
        foreach (['', 'x', str_repeat("\0binary\xFF", self::BULK_PAYLOAD_REPETITIONS)] as $plaintext) {
            $this->assertSame($plaintext, $kms->decrypt($kms->encrypt($keyId, $plaintext)));
        }
        $otherVersion = KmipVersion::Version14 === $version ? KmipVersion::Version20 : KmipVersion::Version14;
        $otherVersionKms = KmipKms::fromTls($host, $clientCertificateFile, $clientPrivateKeyFile, $otherVersion, $port, $certificateAuthorityFile, $peerName, null, null, null, self::TIMEOUT_SECONDS, self::schemes());
        $this->assertSame("hello\0world", $otherVersionKms->decrypt($ciphertext, "aad\0bytes"));

        $payloadOffset = self::NAMED_CIPHERTEXT_PREFIX_LENGTH + \strlen($cipher);
        $ivLength = \ord($ciphertext->blob[$payloadOffset + self::RAW_IV_LENGTH_OFFSET]);
        $this->assertSame(self::AES_CIPHER === $cipher ? $aesGcmIvLength : self::AES_GCM_SHORT_IV_LENGTH_BYTES, $ivLength);
        $authenticationTagOffset = self::RAW_IV_OFFSET + $ivLength;
        $encryptedDataOffset = $authenticationTagOffset + self::AUTHENTICATION_TAG_LENGTH;
        foreach ([self::RAW_IV_OFFSET, $authenticationTagOffset, $encryptedDataOffset] as $offset) {
            $tampered = $ciphertext->blob;
            $tampered[$payloadOffset + $offset] = \chr(\ord($tampered[$payloadOffset + $offset]) ^ 1);
            $this->expectAuthenticationFailure(static fn () => $kms->decrypt(new Ciphertext($tampered, $keyId), "aad\0bytes"));
        }
        $tampered = $ciphertext->blob;
        $tampered[$payloadOffset + self::RAW_IV_LENGTH_OFFSET] = "\0";
        $this->expectDecryptionFailure(static fn () => $kms->decrypt(new Ciphertext($tampered, $keyId), "aad\0bytes"));
        if (self::AES_CIPHER === $cipher) {
            $tampered = $ciphertext->blob;
            $tampered[$payloadOffset + self::RAW_IV_LENGTH_OFFSET] = \chr(self::AES_GCM_DEFAULT_IV_LENGTH_BYTES === $aesGcmIvLength ? self::AES_GCM_SHORT_IV_LENGTH_BYTES : self::AES_GCM_DEFAULT_IV_LENGTH_BYTES);
            $this->expectAuthenticationFailure(static fn () => $kms->decrypt(new Ciphertext($tampered, $keyId), "aad\0bytes"));
        }
        $tampered = $ciphertext->blob;
        $tampered[self::NAMED_CIPHERTEXT_PREFIX_LENGTH] = 'x';
        $this->expectDecryptionFailure(static fn () => $kms->decrypt(new Ciphertext($tampered, $keyId), "aad\0bytes"));
        $this->expectDecryptionFailure(static fn () => $kms->decrypt(new Ciphertext($ciphertext->blob, 'unknown-key'), "aad\0bytes"));
        $alternateKeyId = self::provisionKey($protocol, $version, self::USAGE_ENCRYPT_AND_DECRYPT, true, $algorithm);
        $this->expectAuthenticationFailure(static fn () => $kms->decrypt(new Ciphertext($ciphertext->blob, $alternateKeyId), "aad\0bytes"));
        try {
            $kms->encrypt('unknown-key', 'data');
            $this->fail('An unknown key must fail.');
        } catch (KeyNotFoundException) {
        }
        try {
            $kms->encrypt("missing\n\x1B[31m", 'data');
            $this->fail('An unknown key must fail.');
        } catch (KeyNotFoundException $e) {
            $this->assertStringContainsString('\\x0A', $e->getMessage());
            $this->assertStringContainsString('\\x1B', $e->getMessage());
            $this->assertStringNotContainsString("\n", $e->getMessage());
        }
        try {
            $kms->encrypt($keyId, 'data', '', true);
            $this->fail('Deterministic encryption must fail.');
        } catch (UnsupportedOperationException) {
        }

        $this->expectAuthenticationFailure(static fn () => $kms->decrypt($ciphertext, 'wrong aad'));
        $this->expectAuthenticationFailure(static fn () => $kms->decrypt($ciphertext));
        $this->expectAuthenticationFailure(static fn () => $kms->decrypt($kms->encrypt($keyId, 'without aad'), 'extra aad'));

        foreach ([16, 32, 64] as $length) {
            foreach (['', 'wrapped aad'] as $aad) {
                $dataKey = $kms->generateDataKey($keyId, $length, $aad);
                $wrapped = $dataKey->wrapped;
                $plaintext = $dataKey->use(static fn (string $bytes): string => $bytes);
                $this->assertSame($length, \strlen($plaintext));
                $this->assertSame($plaintext, $kms->unwrapDataKey($wrapped, $aad)->use(static fn (string $bytes): string => $bytes));
            }
        }
        $ordinaryWrapped = $kms->encrypt($keyId, random_bytes(32), 'rewrap');
        $this->assertSame($kms->decrypt($ordinaryWrapped, 'rewrap'), $kms->unwrapDataKey($ordinaryWrapped, 'rewrap')->use(static fn (string $bytes): string => $bytes));

        $dsnOptions = ['cert' => $clientCertificateFile, 'key' => $clientPrivateKeyFile, 'ca' => $certificateAuthorityFile, 'peer_name' => $peerName, 'version' => $version->value, 'cipher' => $cipher];
        if (self::AES_CIPHER === $cipher && self::AES_GCM_DEFAULT_IV_LENGTH_BYTES !== $aesGcmIvLength) {
            $dsnOptions['iv_length'] = $aesGcmIvLength;
        }
        $dsn = 'kmip://user:password@'.$host.':'.$port.'?'.http_build_query($dsnOptions);
        $credentialed = self::factory()->create(Dsn::fromString($dsn));
        $credentialedCiphertext = $credentialed->encrypt($keyId, 'credentialed', 'aad');
        $this->assertSame('credentialed', $credentialed->decrypt($credentialedCiphertext, 'aad'));
        if (self::AES_CIPHER === $cipher) {
            $this->assertSame($aesGcmIvLength, \ord($credentialedCiphertext->blob[self::NAMED_CIPHERTEXT_PREFIX_LENGTH + \strlen(self::AES_CIPHER)]));
        }

        $encryptedClientPrivateKeyFile = (string) getenv('KMIP_TEST_ENCRYPTED_KEY');
        if ('' !== $encryptedClientPrivateKeyFile) {
            $withPassphrase = KmipKms::fromTls($host, $clientCertificateFile, $encryptedClientPrivateKeyFile, $version, $port, $certificateAuthorityFile, $peerName, (string) getenv('KMIP_TEST_KEY_PASSPHRASE'), null, null, self::TIMEOUT_SECONDS, self::schemes(), $cipher);
            $this->assertSame('encrypted key', $withPassphrase->decrypt($withPassphrase->encrypt($keyId, 'encrypted key')));
            try {
                KmipKms::fromTls($host, $clientCertificateFile, $encryptedClientPrivateKeyFile, $version, $port, $certificateAuthorityFile, $peerName, 'wrong-private-passphrase', null, null, self::TIMEOUT_SECONDS)->encrypt($keyId, 'tls');
                $this->fail('The wrong private-key passphrase must fail.');
            } catch (RuntimeException $e) {
                $this->assertStringNotContainsString('wrong-private-passphrase', $e->getMessage());
            }
        }

        $otherClientCertificateFile = (string) getenv('KMIP_TEST_OTHER_CERT');
        $otherClientPrivateKeyFile = (string) getenv('KMIP_TEST_OTHER_KEY');
        if ('' !== $otherClientCertificateFile && '' !== $otherClientPrivateKeyFile) {
            $otherOwner = KmipKms::fromTls($host, $otherClientCertificateFile, $otherClientPrivateKeyFile, $version, $port, $certificateAuthorityFile, $peerName, null, null, null, self::TIMEOUT_SECONDS, self::schemes());
            try {
                $otherOwner->decrypt($ciphertext, "aad\0bytes");
                $this->fail('Another client identity must be denied.');
            } catch (OperationFailed $e) {
                $this->assertSame(self::REASON_PERMISSION_DENIED, $e->reason);
            }
        }

        $encryptOnlyKeyId = self::provisionKey($protocol, $version, self::USAGE_ENCRYPT_ONLY, true, $algorithm);
        $restrictedCiphertext = $kms->encrypt($encryptOnlyKeyId, 'restricted');
        if (self::isEviden()) {
            $this->expectDecryptionFailure(static fn () => $kms->decrypt($restrictedCiphertext));
        } else {
            $this->expectOperationalFailure(static fn () => $kms->decrypt($restrictedCiphertext));
        }
        $preActiveKeyId = self::provisionKey($protocol, $version, self::USAGE_ENCRYPT_AND_DECRYPT, false, $algorithm);
        $this->expectOperationalFailure(static fn () => $kms->encrypt($preActiveKeyId, 'inactive'));
        $revokedKeyId = self::provisionKey($protocol, $version, self::USAGE_ENCRYPT_AND_DECRYPT, true, $algorithm);
        $revokedCiphertext = $kms->encrypt($revokedKeyId, 'revoked');
        $protocol->request(self::OPERATION_REVOKE, Ttlv::text(ProtocolClient::UNIQUE_IDENTIFIER, $revokedKeyId).Ttlv::structure(self::TAG_REVOCATION_REASON, Ttlv::integer(self::TAG_REVOCATION_REASON_CODE, self::REVOCATION_REASON_UNSPECIFIED, Ttlv::ENUMERATION)));
        $this->expectOperationalFailure(static fn () => $kms->encrypt($revokedKeyId, 'after revoke'));
        if (self::isEviden()) {
            $this->assertSame('revoked', $kms->decrypt($revokedCiphertext));
        } else {
            $this->expectOperationalFailure(static fn () => $kms->decrypt($revokedCiphertext));
        }

        $this->expectOperationalFailure(static fn () => KmipKms::fromTls($host, $clientCertificateFile, $clientPrivateKeyFile, $version, $port, $certificateAuthorityFile, 'wrong.example', null, null, null, self::TIMEOUT_SECONDS)->encrypt($keyId, 'tls'));
        $this->expectOperationalFailure(static fn () => KmipKms::fromTls($host, $clientCertificateFile, $clientPrivateKeyFile, $version, $port, $certificateAuthorityFile, null, null, null, null, self::TIMEOUT_SECONDS)->encrypt($keyId, 'tls'));
        $this->expectOperationalFailure(static fn () => KmipKms::fromTls($host, $clientCertificateFile, $clientPrivateKeyFile, $version, $port, $clientCertificateFile, $peerName, null, null, null, self::TIMEOUT_SECONDS)->encrypt($keyId, 'tls'));
        $certificateWithoutExtendedKeyUsageFile = (string) getenv('KMIP_TEST_NO_EKU_CERT');
        $matchingPrivateKeyFile = (string) getenv('KMIP_TEST_NO_EKU_KEY');
        if ('' !== $certificateWithoutExtendedKeyUsageFile && '' !== $matchingPrivateKeyFile) {
            try {
                KmipKms::fromTls($host, $certificateWithoutExtendedKeyUsageFile, $matchingPrivateKeyFile, $version, $port, $certificateAuthorityFile, $peerName, null, null, null, self::TIMEOUT_SECONDS)->encrypt($keyId, 'tls');
                $this->fail('A client without clientAuth EKU must be denied.');
            } catch (OperationFailed $e) {
                $this->assertSame(self::isEviden() ? self::REASON_PERMISSION_DENIED : self::REASON_AUTHENTICATION_NOT_SUCCESSFUL, $e->reason);
                $this->assertSame(self::isEviden(), $e->hasOperation);
            }
        }

        $envelope = new EnvelopeEncrypter($kms);
        $this->assertSame("bulk\0payload", $envelope->decrypt($envelope->encrypt($keyId, "bulk\0payload", 'context'), 'context'));

        foreach ([$keyId, $alternateKeyId, $encryptOnlyKeyId] as $keyIdToDestroy) {
            self::destroyKey($protocol, $keyIdToDestroy, true);
        }
        self::destroyKey($protocol, $preActiveKeyId, false);
        self::destroyKey($protocol, $revokedKeyId, false);
    }

    public static function versions(): iterable
    {
        yield '1.4' => [KmipVersion::Version14];
        yield '2.0' => [KmipVersion::Version20];
        yield '2.1' => [KmipVersion::Version21];
        yield '2.0 AES-GCM with 12-byte IV' => [KmipVersion::Version20, self::AES_CIPHER, self::AES_GCM_SHORT_IV_LENGTH_BYTES];
        yield '1.4 ChaCha20-Poly1305' => [KmipVersion::Version14, self::CHACHA20_POLY1305_CIPHER];
        yield '2.0 ChaCha20-Poly1305' => [KmipVersion::Version20, self::CHACHA20_POLY1305_CIPHER];
        yield '2.1 ChaCha20-Poly1305' => [KmipVersion::Version21, self::CHACHA20_POLY1305_CIPHER];
        yield '1.4 AES-GCM-SIV' => [KmipVersion::Version14, self::AES_GCM_SIV_CIPHER];
        yield '2.0 AES-GCM-SIV' => [KmipVersion::Version20, self::AES_GCM_SIV_CIPHER];
        yield '2.1 AES-GCM-SIV' => [KmipVersion::Version21, self::AES_GCM_SIV_CIPHER];
    }

    public function testVersion21IsRejectedWithoutAffectingVersion20()
    {
        if (!self::isPyKmip()) {
            $this->markTestSkipped('Only the PyKMIP fixture is expected to reject KMIP 2.1.');
        }
        $host = getenv('KMIP_TEST_HOST');
        if (!$host) {
            if ('1' === getenv('KMIP_TEST_REQUIRED')) {
                $this->fail('KMIP_TEST_HOST is required.');
            }
            $this->markTestSkipped('KMIP fixture is not running.');
        }
        $clientCertificateFile = (string) getenv('KMIP_TEST_CERT');
        $clientPrivateKeyFile = (string) getenv('KMIP_TEST_KEY');
        $certificateAuthorityFile = (string) getenv('KMIP_TEST_CA');
        $peerName = (string) getenv('KMIP_TEST_PEER_NAME');
        $port = (int) (getenv('KMIP_TEST_PORT') ?: self::DEFAULT_PORT);
        $protocol = new ProtocolClient(new StreamTransport($host, $port, $clientCertificateFile, $clientPrivateKeyFile, $certificateAuthorityFile, $peerName, null, self::TIMEOUT_SECONDS), KmipVersion::Version20, null, null);
        $keyId = self::provisionKey($protocol, KmipVersion::Version20);
        $options = http_build_query(['cert' => $clientCertificateFile, 'key' => $clientPrivateKeyFile, 'ca' => $certificateAuthorityFile, 'peer_name' => $peerName]);
        $factory = self::factory();
        $version21Kms = $factory->create(Dsn::fromString('kmip://'.$host.':'.$port.'?'.$options.'&version=2.1'));
        try {
            $version21Kms->encrypt($keyId, 'unsupported');
            $this->fail('PyKMIP 0.10.0 must reject version 2.1.');
        } catch (OperationFailed $e) {
            $this->assertSame(self::REASON_INVALID_MESSAGE, $e->reason);
            $this->assertFalse($e->hasOperation);
        }
        $version20Kms = $factory->create(Dsn::fromString('kmip://'.$host.':'.$port.'?'.$options.'&version=2.0'));
        $this->assertSame('working', $version20Kms->decrypt($version20Kms->encrypt($keyId, 'working')));
        self::destroyKey($protocol, $keyId, true);
    }

    private static function provisionKey(ProtocolClient $protocol, KmipVersion $version, int $usageMask = self::USAGE_ENCRYPT_AND_DECRYPT, bool $activate = true, int $algorithm = self::AES_ALGORITHM): string
    {
        if (KmipVersion::Version14 === $version) {
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
        if ($activate) {
            $protocol->request(self::OPERATION_ACTIVATE, Ttlv::text(ProtocolClient::UNIQUE_IDENTIFIER, $keyId));
        }

        return $keyId;
    }

    /**
     * @return list<string>
     */
    private static function discoverVersions(ProtocolClient $protocol, KmipVersion $version, string $host, int $port, string $clientCertificateFile, string $clientPrivateKeyFile, string $certificateAuthorityFile, string $peerName): array
    {
        $frame = (new StreamTransport($host, $port, $clientCertificateFile, $clientPrivateKeyFile, $certificateAuthorityFile, $peerName, null, self::TIMEOUT_SECONDS))->exchange($protocol->buildRequest(self::OPERATION_DISCOVER_VERSIONS, ''));
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
        if (!self::isPyKmip()) {
            $this->expectDecryptionFailure($call);

            return;
        }

        // PyKMIP reports an invalid authentication tag as General Failure rather than Cryptographic Failure.
        try {
            $call();
            $this->fail('Expected authentication to fail.');
        } catch (OperationFailed $e) {
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

    private static function destroyKey(ProtocolClient $protocol, string $keyId, bool $active): void
    {
        if ($active) {
            $protocol->request(self::OPERATION_REVOKE, Ttlv::text(ProtocolClient::UNIQUE_IDENTIFIER, $keyId).Ttlv::structure(self::TAG_REVOCATION_REASON, Ttlv::integer(self::TAG_REVOCATION_REASON_CODE, self::REVOCATION_REASON_UNSPECIFIED, Ttlv::ENUMERATION)));
        }
        $protocol->request(self::OPERATION_DESTROY, Ttlv::text(ProtocolClient::UNIQUE_IDENTIFIER, $keyId));
    }

    private static function factory(): KmipKmsFactory
    {
        return new KmipKmsFactory(self::schemes());
    }

    private static function schemes(int $aesGcmIvLength = self::AES_GCM_DEFAULT_IV_LENGTH_BYTES): KmipEncryptionSchemeRegistry
    {
        return new KmipEncryptionSchemeRegistry([new AesGcmEncryptionScheme($aesGcmIvLength), new ChaCha20Poly1305EncryptionScheme(), new AesGcmSivEncryptionScheme(self::EVIDEN_GCM_SIV_BLOCK_CIPHER_MODE)]);
    }

    private static function isEviden(): bool
    {
        return self::EVIDEN_SERVER === getenv('KMIP_TEST_SERVER');
    }

    private static function isPyKmip(): bool
    {
        return self::PYKMIP_SERVER === getenv('KMIP_TEST_SERVER');
    }
}
