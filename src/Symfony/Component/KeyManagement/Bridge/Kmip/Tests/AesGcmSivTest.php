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
use PHPUnit\Framework\TestCase;
use Symfony\Component\KeyManagement\Bridge\Kmip\Kmip\OperationFailed;
use Symfony\Component\KeyManagement\Bridge\Kmip\Kmip\ProtocolVersion;
use Symfony\Component\KeyManagement\Bridge\Kmip\Kmip\RequestClientInterface;
use Symfony\Component\KeyManagement\Bridge\Kmip\Kmip\Ttlv;
use Symfony\Component\KeyManagement\Bridge\Kmip\KmipKms;
use Symfony\Component\KeyManagement\Bridge\Kmip\KmipKmsFactory;
use Symfony\Component\KeyManagement\Ciphertext;
use Symfony\Component\KeyManagement\Dsn;
use Symfony\Component\KeyManagement\Exception\DecryptionFailedException;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;
use Symfony\Component\KeyManagement\Exception\RuntimeException;

final class AesGcmSivTest extends TestCase
{
    private const string CIPHER = 'aes-gcm-siv';
    private const string HEADER = "\x01\x00\x00\x00\x03\x80\x00\x00\x02\x0C";

    public function testSelectedCipherSendsIndependentCryptographicParameters()
    {
        $client = self::client(static function (int $operation, string $payload): array {
            $fields = self::fields($payload);
            self::assertSame('42002b01000000204200110500000004800000020000000042002805000000040000000300000000', bin2hex(Ttlv::encode(RequestClientInterface::CRYPTOGRAPHIC_PARAMETERS, Ttlv::STRUCTURE, $fields[RequestClientInterface::CRYPTOGRAPHIC_PARAMETERS]['value'])));
            self::assertSame(self::HEADER."aad\0bytes", $fields[RequestClientInterface::AAD]['value']);
            self::assertSame(12, \strlen($fields[RequestClientInterface::IV]['value']));

            return [
                RequestClientInterface::UNIQUE_IDENTIFIER => self::field(Ttlv::TEXT, 'key'),
                RequestClientInterface::DATA => self::field(Ttlv::BYTES, RequestClientInterface::ENCRYPT === $operation ? 'encrypted' : 'plaintext'),
                RequestClientInterface::TAG => self::field(Ttlv::BYTES, str_repeat('t', 16)),
            ];
        });
        $kms = new KmipKms($client, 12, self::CIPHER, '0x80000002');
        $encrypted = $kms->encrypt('key', 'plaintext', "aad\0bytes");

        $this->assertSame(self::HEADER, substr($encrypted->blob, 0, 10));
        $this->assertSame('plaintext', $kms->decrypt($encrypted, "aad\0bytes"));
    }

    public function testFactorySelectsTheCipher()
    {
        $kms = (new KmipKmsFactory())->create(Dsn::fromString('kmip://localhost?cert=a&key=b&version=2.0&cipher=aes-gcm-siv&mode=0x80000002'));
        $cipher = (new \ReflectionProperty($kms, 'cipher'))->getValue($kms);
        $encrypted = $cipher->encrypt(self::authenticatedClient(), 'key', 'plaintext', 'aad');

        $this->assertStringStartsWith(self::HEADER, $encrypted->blob);
    }

    #[DataProvider('plaintexts')]
    public function testBinaryAndEmptyPlaintextRoundTrip(string $plaintext)
    {
        $client = self::authenticatedClient();
        $kms = new KmipKms($client, 12, self::CIPHER, '0x80000002');
        $encrypted = $kms->encrypt('key', $plaintext, 'aad');

        $this->assertStringStartsWith(self::HEADER, $encrypted->blob);
        $this->assertSame($plaintext, $kms->decrypt($encrypted, 'aad'));
        $this->assertNotSame($encrypted->blob, $kms->encrypt('key', $plaintext, 'aad')->blob);
    }

    public static function plaintexts(): iterable
    {
        yield 'empty' => [''];
        yield 'binary' => ["plain\0\xFFtext"];
    }

    public function testExistingAesGcmCiphertextRemainsReadable()
    {
        $client = self::authenticatedClient('42001105000000040000000900000000'
            .'42002805000000040000000300000000'
            .'4200cd02000000040000008000000000'
            .'4200ce02000000040000001000000000');
        $encrypted = (new KmipKms($client, 16))->encrypt('key', 'existing plaintext', 'aad');

        $this->assertSame('existing plaintext', (new KmipKms($client, 12, self::CIPHER, '0x80000002'))->decrypt($encrypted, 'aad'));
    }

    public function testExistingChaCha20Poly1305CiphertextRemainsReadable()
    {
        $client = self::authenticatedClient('42002805000000040000001e00000000');
        $encrypted = (new KmipKms($client, 12, 'chacha20-poly1305'))->encrypt('key', 'existing plaintext', 'aad');

        $this->assertSame('existing plaintext', (new KmipKms($client, 12, self::CIPHER, '0x80000002'))->decrypt($encrypted, 'aad'));
    }

    public function testStoredCipherIsReadAfterSelectingAesGcm()
    {
        $client = self::authenticatedClient('42001105000000048000000200000000'
            .'42002805000000040000000300000000');
        $encrypted = (new KmipKms($client, 12, self::CIPHER, '0x80000002'))->encrypt('key', 'plaintext', 'aad');

        $this->assertStringStartsWith(self::HEADER, $encrypted->blob);
        $this->assertSame('plaintext', (new KmipKms($client, 16, 'aes-gcm', '0x80000002'))->decrypt($encrypted, 'aad'));
    }

    #[DataProvider('tampering')]
    public function testTamperedCiphertextAndAssociatedDataAreRejected(?int $offset, string $aad)
    {
        $client = self::authenticatedClient();
        $kms = new KmipKms($client, 12, self::CIPHER, '0x80000002');
        $encrypted = $kms->encrypt('key', 'plaintext', 'aad');
        $this->assertStringStartsWith(self::HEADER, $encrypted->blob);
        $blob = $encrypted->blob;
        if (null !== $offset) {
            $blob[$offset] = \chr(\ord($blob[$offset]) ^ 1);
        }

        $this->expectException(DecryptionFailedException::class);
        $kms->decrypt(new Ciphertext($blob, 'key'), $aad);
    }

    public static function tampering(): iterable
    {
        yield 'format' => [0, 'aad'];
        yield 'algorithm' => [4, 'aad'];
        yield 'mode' => [8, 'aad'];
        yield 'nonce length' => [9, 'aad'];
        yield 'nonce' => [10, 'aad'];
        yield 'tag' => [22, 'aad'];
        yield 'encrypted data' => [38, 'aad'];
        yield 'wrong AAD' => [null, 'wrong'];
        yield 'missing AAD' => [null, ''];
    }

    public function testDataKeyWrappingUsesTheSelectedCipher()
    {
        $client = self::authenticatedClient();
        $kms = new KmipKms($client, 12, self::CIPHER, '0x80000002');
        $generated = $kms->generateDataKey('key', 32, 'aad');
        $plaintext = $generated->use(static fn (string $value): string => $value);

        $this->assertStringStartsWith(self::HEADER, $generated->wrapped->blob);
        $this->assertSame($plaintext, $kms->unwrapDataKey($generated->wrapped, 'aad')->use(static fn (string $value): string => $value));
        $this->assertSame($plaintext, $kms->decrypt($kms->wrapDataKey('key', $plaintext, 'aad'), 'aad'));
    }

    #[DataProvider('invalidResponses')]
    public function testMalformedResponsesAreRejected(string $fault, string $message)
    {
        $client = self::client(static function (int $operation, string $payload) use ($fault): array {
            $fields = self::fields($payload);
            self::assertSame(self::HEADER.'aad', $fields[RequestClientInterface::AAD]['value']);
            $response = [
                RequestClientInterface::UNIQUE_IDENTIFIER => self::field(Ttlv::TEXT, 'key'),
                RequestClientInterface::DATA => self::field(Ttlv::BYTES, 'x'),
                RequestClientInterface::TAG => self::field(Ttlv::BYTES, str_repeat('t', 16)),
            ];
            match ($fault) {
                'missing tag' => $response[RequestClientInterface::TAG] = null,
                'short tag' => $response[RequestClientInterface::TAG]['value'] = 't',
                'wrong key' => $response[RequestClientInterface::UNIQUE_IDENTIFIER]['value'] = 'other',
                'wrong IV' => $response[RequestClientInterface::IV] = self::field(Ttlv::BYTES, ''),
                'wrong length' => $response[RequestClientInterface::DATA]['value'] = '',
            };

            return array_filter($response);
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);
        (new KmipKms($client, 12, self::CIPHER, '0x80000002'))->encrypt('key', 'x', 'aad');
    }

    public static function invalidResponses(): iterable
    {
        yield 'missing tag' => ['missing tag', 'Missing KMIP response payload field.'];
        yield 'short tag' => ['short tag', 'KMIP returned invalid authenticated ciphertext.'];
        yield 'wrong key' => ['wrong key', 'KMIP returned a different key identifier.'];
        yield 'wrong IV' => ['wrong IV', 'KMIP returned a different encryption IV.'];
        yield 'wrong length' => ['wrong length', 'KMIP returned invalid authenticated ciphertext.'];
    }

    public function testIvLengthOptionIsRejectedForTheFixedNonceCipher()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "iv_length" option is only supported with "aes-gcm".');

        (new KmipKmsFactory())->create(Dsn::fromString('kmip://localhost?cert=a&key=b&version=2.0&cipher=aes-gcm-siv&mode=0x80000002&iv_length=16'));
    }

    #[DataProvider('vendorModes')]
    public function testVendorModeIsEncodedWithoutDependingOnNativeIntegerSize(string $mode, string $expected)
    {
        $client = self::authenticatedClient();
        $kms = (new KmipKmsFactory())->create(Dsn::fromString('kmip://localhost?cert=a&key=b&version=2.0&cipher=aes-gcm-siv&mode='.$mode));
        $cipher = (new \ReflectionProperty($kms, 'cipher'))->getValue($kms);
        $encrypted = $cipher->encrypt($client, 'key', 'plaintext', 'aad');

        $this->assertSame($expected, bin2hex(substr($encrypted->blob, 5, 4)));
        $this->assertSame('plaintext', $cipher->decrypt($client, $encrypted, 'aad'));
    }

    public static function vendorModes(): iterable
    {
        yield 'first extension' => ['0x80000000', '80000000'];
        yield 'last extension' => ['0x8FFFFFFF', '8fffffff'];
        yield 'lowercase hex' => ['0x8abcdef0', '8abcdef0'];
        yield 'another vendor' => ['0x80001234', '80001234'];
    }

    #[DataProvider('invalidModes')]
    public function testInvalidVendorModeIsRejectedBeforeConnecting(string $option, string $message)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        (new KmipKmsFactory())->create(Dsn::fromString('kmip://localhost?cert=a&key=b&version=2.0&cipher=aes-gcm-siv'.$option));
    }

    public static function invalidModes(): iterable
    {
        yield 'missing' => ['', 'requires a vendor mode'];
        yield 'empty' => ['&mode=', 'must be a hexadecimal value'];
        yield 'standard GCM' => ['&mode=0x00000009', 'must be a hexadecimal value'];
        yield 'below extension range' => ['&mode=0x7FFFFFFF', 'must be a hexadecimal value'];
        yield 'above extension range' => ['&mode=0x90000000', 'must be a hexadecimal value'];
        yield 'too long' => ['&mode=0x800000002', 'must be a hexadecimal value'];
        yield 'invalid hex' => ['&mode=0x8000000G', 'must be a hexadecimal value'];
        yield 'decimal' => ['&mode=2147483650', 'must be a hexadecimal value'];
        yield 'array' => ['&mode[]=0x80000002', 'must be scalar'];
    }

    #[DataProvider('untrustedModes')]
    public function testAnUnconfiguredVendorModeIsRejectedBeforeARequest(?string $mode)
    {
        $client = self::client(static fn (): array => throw new \LogicException('An unconfigured mode must not reach the server.'));
        $kms = new KmipKms($client, 12, 'aes-gcm', $mode);

        $this->expectException(DecryptionFailedException::class);
        $kms->decrypt(new Ciphertext(self::HEADER.str_repeat('i', 12).str_repeat('t', 16).'x', 'key'), 'aad');
    }

    public static function untrustedModes(): iterable
    {
        yield 'no configured mode' => [null];
        yield 'different configured mode' => ['0x80001234'];
    }

    public function testFactoryCanKeepVendorDecryptionEnabledWhenSelectingAesGcm()
    {
        $client = self::authenticatedClient();
        $encrypted = (new KmipKms($client, 12, self::CIPHER, '0x80000002'))->encrypt('key', 'plaintext', 'aad');
        $kms = (new KmipKmsFactory())->create(Dsn::fromString('kmip://localhost?cert=a&key=b&version=2.0&mode=0x80000002'));
        $cipher = (new \ReflectionProperty($kms, 'cipher'))->getValue($kms);

        $this->assertSame('plaintext', $cipher->decrypt($client, $encrypted, 'aad'));
        $this->assertStringStartsWith("\x01\0\0\0\x03\0\0\0\x09\x0C", $cipher->encrypt($client, 'key', 'new plaintext', 'aad')->blob);
    }

    public function testFactoryCanKeepVendorDecryptionEnabledWhenSelectingChaCha20Poly1305()
    {
        $client = self::authenticatedClient('42001105000000048000000200000000'
            .'42002805000000040000000300000000');
        $encrypted = (new KmipKms($client, 12, self::CIPHER, '0x80000002'))->encrypt('key', 'plaintext', 'aad');
        $kms = (new KmipKmsFactory())->create(Dsn::fromString('kmip://localhost?cert=a&key=b&version=2.0&cipher=chacha20-poly1305&mode=0x80000002'));
        $cipher = (new \ReflectionProperty($kms, 'cipher'))->getValue($kms);

        $this->assertSame('plaintext', $cipher->decrypt($client, $encrypted, 'aad'));
        $this->assertStringStartsWith("\x01\0\0\0\x1E\0\0\0\0\x0C", $cipher->encrypt($client, 'key', 'new plaintext', 'aad')->blob);
    }

    public function testCipherOptionMustBeAString()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "cipher" option of the "kmip://" DSN must be a string.');

        (new KmipKmsFactory())->create(new Dsn('kmip', 'localhost', null, null, null, '', ['cert' => 'a', 'key' => 'b', 'version' => '2.0', 'cipher' => 1]));
    }

    public function testCipherOptionMustBeScalar()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "cipher" option of the "kmip://" DSN must be scalar.');

        (new KmipKmsFactory())->create(Dsn::fromString('kmip://localhost?cert=a&key=b&version=2.0&cipher[]=unknown'));
    }

    public function testTheCipherRequiresATwelveByteNonce()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The KMIP "AES-GCM-SIV" IV length must be 12 bytes.');

        new KmipKms(self::authenticatedClient(), 16, self::CIPHER, '0x80000002');
    }

    public function testAnExplicitAesGcmSelectionKeepsTheDefaultCipher()
    {
        $kms = (new KmipKmsFactory())->create(Dsn::fromString('kmip://localhost?cert=a&key=b&version=2.0&cipher=aes-gcm'));
        $cipher = (new \ReflectionProperty($kms, 'cipher'))->getValue($kms);

        $this->assertStringStartsWith("\x01\0\0\0\x03\0\0\0\x09\x0C", $cipher->encrypt(self::authenticatedClient(), 'key', 'plaintext', 'aad')->blob);
    }

    private static function authenticatedClient(?string $expectedDecryptionParameters = null): RequestClientInterface
    {
        return self::client(static function (int $operation, string $payload) use ($expectedDecryptionParameters): array {
            $fields = self::fields($payload);
            if (RequestClientInterface::DECRYPT === $operation && null !== $expectedDecryptionParameters) {
                self::assertSame($expectedDecryptionParameters, bin2hex($fields[RequestClientInterface::CRYPTOGRAPHIC_PARAMETERS]['value']));
            }
            $data = $fields[RequestClientInterface::DATA]['value'];
            $iv = $fields[RequestClientInterface::IV]['value'];
            $aad = $fields[RequestClientInterface::AAD]['value'];
            $response = [RequestClientInterface::UNIQUE_IDENTIFIER => self::field(Ttlv::TEXT, 'key')];
            if (RequestClientInterface::ENCRYPT === $operation) {
                $data = strrev($data);
                $response[RequestClientInterface::TAG] = self::field(Ttlv::BYTES, substr(hash_hmac('sha256', $data.$iv.$aad, 'unit-test-key', true), 0, 16));
            } elseif (!hash_equals(substr(hash_hmac('sha256', $data.$iv.$aad, 'unit-test-key', true), 0, 16), $fields[RequestClientInterface::TAG]['value'])) {
                throw new OperationFailed(0x0A, true);
            } else {
                $data = strrev($data);
            }
            $response[RequestClientInterface::DATA] = self::field(Ttlv::BYTES, $data);

            return $response;
        });
    }

    private static function client(\Closure $handler): RequestClientInterface
    {
        return new class($handler) implements RequestClientInterface {
            public function __construct(private \Closure $handler)
            {
            }

            public function request(int $operation, #[\SensitiveParameter] string $payload): array
            {
                $fields = ($this->handler)($operation, $payload);
                foreach ($fields as $tag => $field) {
                    $fields[$tag]['tag'] = $tag;
                }

                return $fields;
            }
        };
    }

    private static function fields(string $payload): array
    {
        $fields = [];
        foreach (Ttlv::items($payload, ProtocolVersion::Version20) as $field) {
            $fields[$field['tag']] = $field;
        }

        return $fields;
    }

    private static function field(int $type, string $value): array
    {
        return ['tag' => 0, 'type' => $type, 'value' => $value];
    }
}
