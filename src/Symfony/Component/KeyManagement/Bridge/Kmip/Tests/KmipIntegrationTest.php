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
    private const int REASON_GENERAL_FAILURE = 0x100;
    private const int DEFAULT_PORT = 5696;
    private const int TIMEOUT_SECONDS = 10;
    private const int AES_ALGORITHM = 3;
    private const int AES_KEY_LENGTH_BITS = 256;
    private const int DEFAULT_IV_LENGTH_BYTES = 12;
    private const int LONG_IV_LENGTH_BYTES = 16;
    private const int OBJECT_TYPE_SYMMETRIC_KEY = 2;
    private const int USAGE_ENCRYPT_ONLY = 4;
    private const int USAGE_ENCRYPT_AND_DECRYPT = 12;
    private const int REVOCATION_REASON_UNSPECIFIED = 1;
    private const int BULK_PAYLOAD_REPETITIONS = 8192;
    private const int RAW_IV_LENGTH_OFFSET = 0;
    private const int RAW_IV_OFFSET = 1;
    private const int AUTHENTICATION_TAG_LENGTH = 16;
    private const string CIPHERTEXT_HEADER = "\x01\x00\x00\x00\x03\x00\x00\x00\x09";
    private const int CIPHERTEXT_HEADER_LENGTH = 9;

    public function testServerAdvertisesSupportedVersion()
    {
        $host = getenv('KMIP_TEST_HOST');
        if (!$host) {
            if ('1' === getenv('KMIP_TEST_REQUIRED')) {
                $this->fail('KMIP_TEST_HOST is required.');
            }
            $this->markTestSkipped('KMIP fixture is not running.');
        }
        $version = ProtocolVersion::Version20;
        $clientCertificateFile = (string) getenv('KMIP_TEST_CERT');
        $clientPrivateKeyFile = (string) getenv('KMIP_TEST_KEY');
        $certificateAuthorityFile = (string) getenv('KMIP_TEST_CA');
        $peerName = (string) getenv('KMIP_TEST_PEER_NAME');
        $port = (int) (getenv('KMIP_TEST_PORT') ?: self::DEFAULT_PORT);
        $protocol = new ProtocolClient(new StreamTransport($host, $port, $clientCertificateFile, $clientPrivateKeyFile, $certificateAuthorityFile, $peerName, null, self::TIMEOUT_SECONDS), $version, null, null);

        $this->assertContains($version->value, self::discoverVersions($protocol, $version, $host, $port, $clientCertificateFile, $clientPrivateKeyFile, $certificateAuthorityFile, $peerName));
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
    public function testVersionedOperations(ProtocolVersion $version, int $ivLength = self::DEFAULT_IV_LENGTH_BYTES)
    {
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
        $this->assertContains(ProtocolVersion::Version14->value, $versions);
        $this->assertContains(ProtocolVersion::Version20->value, $versions);
        $this->assertContains($version->value, $versions);
        $keyId = self::provisionKey($protocol, $version);
        $options = ['cert' => $clientCertificateFile, 'key' => $clientPrivateKeyFile, 'ca' => $certificateAuthorityFile, 'peer_name' => $peerName];
        $kms = self::kms($host, $port, $version, $options + ['iv_length' => $ivLength]);

        $ciphertext = $kms->encrypt($keyId, "hello\0world", "aad\0bytes");
        $this->assertSame($keyId, $ciphertext->keyId);
        $this->assertStringStartsWith(self::CIPHERTEXT_HEADER.\chr($ivLength), $ciphertext->blob);
        $this->assertSame("hello\0world", $kms->decrypt($ciphertext, "aad\0bytes"));
        $this->assertNotSame($ciphertext->blob, $kms->encrypt($keyId, "hello\0world", "aad\0bytes")->blob);
        $otherIvLength = self::DEFAULT_IV_LENGTH_BYTES === $ivLength ? self::LONG_IV_LENGTH_BYTES : self::DEFAULT_IV_LENGTH_BYTES;
        $otherIvKms = self::kms($host, $port, $version, $options + ['iv_length' => $otherIvLength]);
        $otherIvCiphertext = $otherIvKms->encrypt($keyId, 'other IV', 'aad');
        $this->assertSame('other IV', $kms->decrypt($otherIvCiphertext, 'aad'));
        $this->assertSame('selected IV', $otherIvKms->decrypt($kms->encrypt($keyId, 'selected IV', 'aad'), 'aad'));
        $this->assertStringStartsWith(self::CIPHERTEXT_HEADER.\chr($otherIvLength), $otherIvCiphertext->blob);
        foreach (['', 'x', str_repeat("\0binary\xFF", self::BULK_PAYLOAD_REPETITIONS)] as $plaintext) {
            $this->assertSame($plaintext, $kms->decrypt($kms->encrypt($keyId, $plaintext)));
        }
        $otherVersion = ProtocolVersion::Version14 === $version ? ProtocolVersion::Version20 : ProtocolVersion::Version14;
        $otherVersionKms = self::kms($host, $port, $otherVersion, $options);
        $this->assertSame("hello\0world", $otherVersionKms->decrypt($ciphertext, "aad\0bytes"));

        $payloadOffset = self::CIPHERTEXT_HEADER_LENGTH;
        $this->assertSame($ivLength, \ord($ciphertext->blob[$payloadOffset + self::RAW_IV_LENGTH_OFFSET]));
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
        $tampered = $ciphertext->blob;
        $tampered[$payloadOffset + self::RAW_IV_LENGTH_OFFSET] = \chr($otherIvLength);
        $this->expectAuthenticationFailure(static fn () => $kms->decrypt(new Ciphertext($tampered, $keyId), "aad\0bytes"));
        foreach ([0, 4, 8] as $headerOffset) {
            $tampered = $ciphertext->blob;
            $tampered[$headerOffset] = \chr(\ord($tampered[$headerOffset]) ^ 1);
            $this->expectDecryptionFailure(static fn () => $kms->decrypt(new Ciphertext($tampered, $keyId), "aad\0bytes"));
        }
        $this->expectDecryptionFailure(static fn () => $kms->decrypt(new Ciphertext($ciphertext->blob, 'unknown-key'), "aad\0bytes"));
        $alternateKeyId = self::provisionKey($protocol, $version);
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

        $credentialed = (new KmipKmsFactory())->create(Dsn::fromString('kmip://user:password@'.$host.':'.$port.'?'.http_build_query($options + ['version' => $version->value, 'iv_length' => $ivLength])));
        $credentialedCiphertext = $credentialed->encrypt($keyId, 'credentialed', 'aad');
        $this->assertSame('credentialed', $credentialed->decrypt($credentialedCiphertext, 'aad'));
        $this->assertSame($ivLength, \ord($credentialedCiphertext->blob[self::CIPHERTEXT_HEADER_LENGTH]));

        $encryptedClientPrivateKeyFile = (string) getenv('KMIP_TEST_ENCRYPTED_KEY');
        if ('' !== $encryptedClientPrivateKeyFile) {
            $withPassphrase = self::kms($host, $port, $version, ['key' => $encryptedClientPrivateKeyFile, 'passphrase' => (string) getenv('KMIP_TEST_KEY_PASSPHRASE')] + $options);
            $this->assertSame('encrypted key', $withPassphrase->decrypt($withPassphrase->encrypt($keyId, 'encrypted key')));
            try {
                self::kms($host, $port, $version, ['key' => $encryptedClientPrivateKeyFile, 'passphrase' => 'wrong-private-passphrase'] + $options)->encrypt($keyId, 'tls');
                $this->fail('The wrong private-key passphrase must fail.');
            } catch (RuntimeException $e) {
                $this->assertStringNotContainsString('wrong-private-passphrase', $e->getMessage());
            }
        }

        $otherClientCertificateFile = (string) getenv('KMIP_TEST_OTHER_CERT');
        $otherClientPrivateKeyFile = (string) getenv('KMIP_TEST_OTHER_KEY');
        if ('' !== $otherClientCertificateFile && '' !== $otherClientPrivateKeyFile) {
            $otherOwner = self::kms($host, $port, $version, ['cert' => $otherClientCertificateFile, 'key' => $otherClientPrivateKeyFile] + $options);
            try {
                $otherOwner->decrypt($ciphertext, "aad\0bytes");
                $this->fail('Another client identity must be denied.');
            } catch (OperationFailed $e) {
                $this->assertSame(self::REASON_PERMISSION_DENIED, $e->reason);
            }
        }

        $encryptOnlyKeyId = self::provisionKey($protocol, $version, self::USAGE_ENCRYPT_ONLY);
        $restrictedCiphertext = $kms->encrypt($encryptOnlyKeyId, 'restricted');
        $this->expectOperationalFailure(static fn () => $kms->decrypt($restrictedCiphertext));
        $preActiveKeyId = self::provisionKey($protocol, $version, self::USAGE_ENCRYPT_AND_DECRYPT, false);
        $this->expectOperationalFailure(static fn () => $kms->encrypt($preActiveKeyId, 'inactive'));
        $revokedKeyId = self::provisionKey($protocol, $version);
        $revokedCiphertext = $kms->encrypt($revokedKeyId, 'revoked');
        $protocol->request(self::OPERATION_REVOKE, Ttlv::text(ProtocolClient::UNIQUE_IDENTIFIER, $revokedKeyId).Ttlv::structure(self::TAG_REVOCATION_REASON, Ttlv::integer(self::TAG_REVOCATION_REASON_CODE, self::REVOCATION_REASON_UNSPECIFIED, Ttlv::ENUMERATION)));
        $this->expectOperationalFailure(static fn () => $kms->encrypt($revokedKeyId, 'after revoke'));
        $this->expectOperationalFailure(static fn () => $kms->decrypt($revokedCiphertext));

        $this->expectOperationalFailure(static fn () => self::kms($host, $port, $version, ['peer_name' => 'wrong.example'] + $options)->encrypt($keyId, 'tls'));
        $this->expectOperationalFailure(static fn () => self::kms($host, $port, $version, array_diff_key($options, ['peer_name' => true]))->encrypt($keyId, 'tls'));
        $this->expectOperationalFailure(static fn () => self::kms($host, $port, $version, ['ca' => $clientCertificateFile] + $options)->encrypt($keyId, 'tls'));
        $certificateWithoutExtendedKeyUsageFile = (string) getenv('KMIP_TEST_NO_EKU_CERT');
        $matchingPrivateKeyFile = (string) getenv('KMIP_TEST_NO_EKU_KEY');
        if ('' !== $certificateWithoutExtendedKeyUsageFile && '' !== $matchingPrivateKeyFile) {
            try {
                self::kms($host, $port, $version, ['cert' => $certificateWithoutExtendedKeyUsageFile, 'key' => $matchingPrivateKeyFile] + $options)->encrypt($keyId, 'tls');
                $this->fail('A client without clientAuth EKU must be denied.');
            } catch (OperationFailed $e) {
                $this->assertSame(self::REASON_AUTHENTICATION_NOT_SUCCESSFUL, $e->reason);
                $this->assertFalse($e->hasOperation);
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
        yield '1.4' => [ProtocolVersion::Version14];
        yield '2.0' => [ProtocolVersion::Version20];
        yield '2.0 with a 16-byte IV' => [ProtocolVersion::Version20, self::LONG_IV_LENGTH_BYTES];
    }

    private static function provisionKey(ProtocolClient $protocol, ProtocolVersion $version, int $usageMask = self::USAGE_ENCRYPT_AND_DECRYPT, bool $activate = true): string
    {
        if (ProtocolVersion::Version14 === $version) {
            $attributes = '';
            foreach ([
                ['Cryptographic Algorithm', Ttlv::ENUMERATION, self::AES_ALGORITHM],
                ['Cryptographic Length', Ttlv::INTEGER, self::AES_KEY_LENGTH_BITS],
                ['Cryptographic Usage Mask', Ttlv::INTEGER, $usageMask],
            ] as [$name, $type, $value]) {
                $attributes .= Ttlv::structure(self::TAG_ATTRIBUTE, Ttlv::text(self::TAG_ATTRIBUTE_NAME, $name).Ttlv::integer(self::TAG_ATTRIBUTE_VALUE, $value, $type));
            }
            $attributes = Ttlv::structure(self::TAG_TEMPLATE_ATTRIBUTE, $attributes);
        } else {
            $attributes = Ttlv::structure(self::TAG_ATTRIBUTES,
                Ttlv::integer(self::TAG_CRYPTOGRAPHIC_ALGORITHM, self::AES_ALGORITHM, Ttlv::ENUMERATION)
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

    private static function discoverVersions(ProtocolClient $protocol, ProtocolVersion $version, string $host, int $port, string $clientCertificateFile, string $clientPrivateKeyFile, string $certificateAuthorityFile, string $peerName): array
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
        try {
            $call();
            $this->fail('Expected authentication to fail.');
        } catch (DecryptionFailedException) {
            $this->assertTrue(true);
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

    private static function destroyKey(ProtocolClient $protocol, string $keyId, bool $active): void
    {
        if ($active) {
            $protocol->request(self::OPERATION_REVOKE, Ttlv::text(ProtocolClient::UNIQUE_IDENTIFIER, $keyId).Ttlv::structure(self::TAG_REVOCATION_REASON, Ttlv::integer(self::TAG_REVOCATION_REASON_CODE, self::REVOCATION_REASON_UNSPECIFIED, Ttlv::ENUMERATION)));
        }
        $protocol->request(self::OPERATION_DESTROY, Ttlv::text(ProtocolClient::UNIQUE_IDENTIFIER, $keyId));
    }

    private static function kms(string $host, int $port, ProtocolVersion $version, array $options): KmipKms
    {
        return (new KmipKmsFactory())->create(Dsn::fromString('kmip://'.$host.':'.$port.'?'.http_build_query($options + ['version' => $version->value])));
    }
}
