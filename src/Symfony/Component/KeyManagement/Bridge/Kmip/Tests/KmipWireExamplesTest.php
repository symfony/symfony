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
use Symfony\Component\KeyManagement\Bridge\Kmip\Kmip\AbstractAuthenticatedEncryptionScheme;
use Symfony\Component\KeyManagement\Bridge\Kmip\Kmip\AesGcmEncryptionScheme;
use Symfony\Component\KeyManagement\Bridge\Kmip\Kmip\OperationFailed;
use Symfony\Component\KeyManagement\Bridge\Kmip\Kmip\ProtocolClient;
use Symfony\Component\KeyManagement\Bridge\Kmip\Kmip\StreamTransport;
use Symfony\Component\KeyManagement\Bridge\Kmip\Kmip\Ttlv;
use Symfony\Component\KeyManagement\Bridge\Kmip\KmipVersion;
use Symfony\Component\KeyManagement\Bridge\Kmip\Tests\Fixtures\KmipWireExamples;

final class KmipWireExamplesTest extends TestCase
{
    private const int TAG_ATTRIBUTE = 0x420008;
    private const int TAG_ATTRIBUTE_NAME = 0x42000A;
    private const int TAG_ATTRIBUTE_VALUE = 0x42000B;
    private const int TAG_BLOCK_CIPHER_MODE = 0x420011;
    private const int TAG_CRYPTOGRAPHIC_ALGORITHM = 0x420028;
    private const int TAG_CRYPTOGRAPHIC_LENGTH = 0x42002A;
    private const int TAG_CRYPTOGRAPHIC_USAGE_MASK = 0x42002C;
    private const int TAG_KEY_MATERIAL = 0x420043;
    private const int TAG_NAME = 0x420053;
    private const int TAG_NAME_TYPE = 0x420054;
    private const int TAG_NAME_VALUE = 0x420055;
    private const int TAG_OBJECT_TYPE = 0x420057;
    private const int TAG_OPERATION = 0x42005C;
    private const int TAG_REQUEST_MESSAGE = 0x420078;
    private const int TAG_RESPONSE_MESSAGE = 0x42007B;
    private const int TAG_SERVER_CORRELATION_VALUE = 0x420106;
    private const int TAG_TEMPLATE_ATTRIBUTE = 0x420091;
    private const int TAG_UNIQUE_IDENTIFIER = 0x420094;
    private const int TAG_ATTRIBUTES = 0x420125;
    private const int OPERATION_DISCOVER_VERSIONS = 0x1E;
    private const int OPERATION_GET = 0x0A;
    private const int OPERATION_PING = 0x3B;
    private const int MODE_NIST_KEY_WRAP = 0x0D;
    private const int REASON_PERMISSION_DENIED = 0x0C;
    private const int OPERATION_CREATE = 1;
    private const int NAME_TYPE_UNINTERPRETED_TEXT_STRING = 1;
    private const int OBJECT_TYPE_SYMMETRIC_KEY = 2;
    private const int AES_ALGORITHM = 3;
    private const int AES_KEY_LENGTH_BITS = 256;
    private const int USAGE_ENCRYPT_AND_DECRYPT = 12;
    private const int AES_GCM_TAG_LENGTH = 16;
    private const int DEFAULT_PORT = 5696;
    private const int TIMEOUT_SECONDS = 10;
    private const int VERSION_MAJOR_VALUE_BYTE_OFFSET = 35;
    private const int VERSION_MINOR_VALUE_BYTE_OFFSET = 51;

    #[DataProvider('examples')]
    public function testIndependentWireExampleIsAWellFormedTtlvTree(int $example, int $length, int $tag, KmipVersion $version)
    {
        $frame = KmipWireExamples::bytes($example);
        $this->assertSame($length, \strlen($frame));
        $items = Ttlv::items($frame, $version);
        $this->assertCount(1, $items);
        $this->assertSame($tag, $items[0]['tag']);
        $this->assertSame(Ttlv::STRUCTURE, $items[0]['type']);
        $this->assertSame($frame, Ttlv::encode($items[0]['tag'], $items[0]['type'], $items[0]['value']));
        $this->assertWellFormedChildren($items[0], $version);
    }

    public static function examples(): iterable
    {
        yield 'KMIP 1.4 Discover Versions request' => [KmipWireExamples::DISCOVER_VERSIONS_REQUEST_VERSION14, 104, self::TAG_REQUEST_MESSAGE, KmipVersion::Version14];
        yield 'KMIP 1.4 Create request' => [KmipWireExamples::CREATE_REQUEST_VERSION14, 360, self::TAG_REQUEST_MESSAGE, KmipVersion::Version14];
        yield 'KMIP 2.0 Create request' => [KmipWireExamples::CREATE_REQUEST_VERSION20, 216, self::TAG_REQUEST_MESSAGE, KmipVersion::Version20];
        yield 'KMIP 1.4 Encrypt request' => [KmipWireExamples::ENCRYPT_REQUEST_VERSION14, 248, self::TAG_REQUEST_MESSAGE, KmipVersion::Version14];
        yield 'KMIP 1.4 Create response' => [KmipWireExamples::CREATE_RESPONSE_VERSION14, 168, self::TAG_RESPONSE_MESSAGE, KmipVersion::Version14];
        yield 'KMIP 1.4 Encrypt response' => [KmipWireExamples::ENCRYPT_RESPONSE_VERSION14, 192, self::TAG_RESPONSE_MESSAGE, KmipVersion::Version14];
        yield 'KMIP 1.4 Encrypt permission denied response' => [KmipWireExamples::ENCRYPT_PERMISSION_DENIED_RESPONSE_VERSION14, 200, self::TAG_RESPONSE_MESSAGE, KmipVersion::Version14];
        yield 'KMIP 1.4 Get request' => [KmipWireExamples::GET_REQUEST_VERSION14, 208, self::TAG_REQUEST_MESSAGE, KmipVersion::Version14];
        yield 'KMIP 1.4 Get response' => [KmipWireExamples::GET_RESPONSE_VERSION14, 280, self::TAG_RESPONSE_MESSAGE, KmipVersion::Version14];
        yield 'KMIP 2.1 Decrypt response' => [KmipWireExamples::DECRYPT_RESPONSE_VERSION21, 200, self::TAG_RESPONSE_MESSAGE, KmipVersion::Version21];
        yield 'KMIP 2.1 Ping response' => [KmipWireExamples::PING_RESPONSE_VERSION21, 152, self::TAG_RESPONSE_MESSAGE, KmipVersion::Version21];
        yield 'KMIP 2.1 Encrypt request' => [KmipWireExamples::ENCRYPT_REQUEST_VERSION21, 296, self::TAG_REQUEST_MESSAGE, KmipVersion::Version21];
        yield 'KMIP 2.1 Encrypt response' => [KmipWireExamples::ENCRYPT_RESPONSE_VERSION21, 240, self::TAG_RESPONSE_MESSAGE, KmipVersion::Version21];
    }

    public function testVersion21DecryptResponseFromOasisTestCase()
    {
        $decrypted = self::client(KmipVersion::Version21)->parseResponse(KmipWireExamples::bytes(KmipWireExamples::DECRYPT_RESPONSE_VERSION21), ProtocolClient::DECRYPT);

        $this->assertSame('1', $decrypted[ProtocolClient::UNIQUE_IDENTIFIER]['value']);
        $this->assertSame(hex2bin('010203040506070809101112131415160102030405060708091011121314151601'), $decrypted[ProtocolClient::DATA]['value']);
    }

    public function testVersion21PingResponseWithServerCorrelationValueFromOasisTestCase()
    {
        $response = KmipWireExamples::bytes(KmipWireExamples::PING_RESPONSE_VERSION21);
        $message = Ttlv::items($response, KmipVersion::Version21)[0];

        $this->assertSame('server-1', Ttlv::requireType(self::find($message, self::TAG_SERVER_CORRELATION_VALUE, KmipVersion::Version21), self::TAG_SERVER_CORRELATION_VALUE, Ttlv::TEXT));
        $this->assertSame([], self::client(KmipVersion::Version21)->parseResponse($response, self::OPERATION_PING));
    }

    public function testVersion21EncryptExchangeMatchesCapturedWireFrames()
    {
        $keyId = '99c71963-0c84-426f-919c-6b25c7c06557';
        $payload = (new \ReflectionMethod(AbstractAuthenticatedEncryptionScheme::class, 'payload'))->invoke(new AesGcmEncryptionScheme(), $keyId, 'kmip-2.1-wire-example', hex2bin('000102030405060708090a0b'), 'context');

        $this->assertSame(KmipWireExamples::bytes(KmipWireExamples::ENCRYPT_REQUEST_VERSION21), self::client(KmipVersion::Version21)->buildRequest(ProtocolClient::ENCRYPT, $payload));

        $encrypted = self::client(KmipVersion::Version21)->parseResponse(KmipWireExamples::bytes(KmipWireExamples::ENCRYPT_RESPONSE_VERSION21), ProtocolClient::ENCRYPT);
        $this->assertSame($keyId, $encrypted[ProtocolClient::UNIQUE_IDENTIFIER]['value']);
        $this->assertSame('01cb8746917945dff89d8855b0b6239a5247c7c58e', bin2hex($encrypted[ProtocolClient::DATA]['value']));
        $this->assertSame('b496155cbd6ae232fd99749e5b5fda8f', bin2hex($encrypted[ProtocolClient::TAG]['value']));
    }

    public function testDiscoverVersionsRequestMatchesIndependentVector()
    {
        $this->assertSame(KmipWireExamples::bytes(KmipWireExamples::DISCOVER_VERSIONS_REQUEST_VERSION14), self::client(KmipVersion::Version14)->buildRequest(self::OPERATION_DISCOVER_VERSIONS, ''));
    }

    public function testVersionedCreateRequestsMatchIndependentVectors()
    {
        $name = Ttlv::text(self::TAG_NAME_VALUE, 'my-key').Ttlv::integer(self::TAG_NAME_TYPE, self::NAME_TYPE_UNINTERPRETED_TEXT_STRING, Ttlv::ENUMERATION);
        $attributes = '';
        foreach ([
            ['Cryptographic Algorithm', Ttlv::ENUMERATION, self::AES_ALGORITHM],
            ['Cryptographic Length', Ttlv::INTEGER, self::AES_KEY_LENGTH_BITS],
            ['Cryptographic Usage Mask', Ttlv::INTEGER, self::USAGE_ENCRYPT_AND_DECRYPT],
        ] as [$label, $type, $value]) {
            $attributes .= Ttlv::structure(self::TAG_ATTRIBUTE, Ttlv::text(self::TAG_ATTRIBUTE_NAME, $label).Ttlv::integer(self::TAG_ATTRIBUTE_VALUE, $value, $type));
        }
        $attributes .= Ttlv::structure(self::TAG_ATTRIBUTE, Ttlv::text(self::TAG_ATTRIBUTE_NAME, 'Name').Ttlv::structure(self::TAG_ATTRIBUTE_VALUE, $name));
        $payload14 = Ttlv::integer(self::TAG_OBJECT_TYPE, self::OBJECT_TYPE_SYMMETRIC_KEY, Ttlv::ENUMERATION).Ttlv::structure(self::TAG_TEMPLATE_ATTRIBUTE, $attributes);
        $this->assertSame(KmipWireExamples::bytes(KmipWireExamples::CREATE_REQUEST_VERSION14), self::client(KmipVersion::Version14)->buildRequest(self::OPERATION_CREATE, $payload14));

        $payload20 = Ttlv::integer(self::TAG_OBJECT_TYPE, self::OBJECT_TYPE_SYMMETRIC_KEY, Ttlv::ENUMERATION)
            .Ttlv::structure(self::TAG_ATTRIBUTES,
                Ttlv::integer(self::TAG_CRYPTOGRAPHIC_ALGORITHM, self::AES_ALGORITHM, Ttlv::ENUMERATION)
                .Ttlv::integer(self::TAG_CRYPTOGRAPHIC_LENGTH, self::AES_KEY_LENGTH_BITS)
                .Ttlv::integer(self::TAG_CRYPTOGRAPHIC_USAGE_MASK, self::USAGE_ENCRYPT_AND_DECRYPT)
                .Ttlv::structure(self::TAG_NAME, $name)
            );
        $this->assertSame(KmipWireExamples::bytes(KmipWireExamples::CREATE_REQUEST_VERSION20), self::client(KmipVersion::Version20)->buildRequest(self::OPERATION_CREATE, $payload20));
    }

    public function testEncryptRequestMatchesIndependentVectorAcrossVersions()
    {
        $payload = (new \ReflectionMethod(AbstractAuthenticatedEncryptionScheme::class, 'payload'))->invoke(new AesGcmEncryptionScheme(), '1', 'hello', hex2bin('000102030405060708090a0b'), 'aad');
        $version14Request = self::client(KmipVersion::Version14)->buildRequest(ProtocolClient::ENCRYPT, $payload);
        $this->assertSame(KmipWireExamples::bytes(KmipWireExamples::ENCRYPT_REQUEST_VERSION14), $version14Request);

        $version20Request = self::client(KmipVersion::Version20)->buildRequest(ProtocolClient::ENCRYPT, $payload);
        $this->assertSame(substr_replace(substr_replace($version14Request, "\x02", self::VERSION_MAJOR_VALUE_BYTE_OFFSET, 1), "\x00", self::VERSION_MINOR_VALUE_BYTE_OFFSET, 1), $version20Request);
        $this->assertSame(substr_replace($version20Request, "\x01", self::VERSION_MINOR_VALUE_BYTE_OFFSET, 1), self::client(KmipVersion::Version21)->buildRequest(ProtocolClient::ENCRYPT, $payload));
    }

    public function testIndependentResponsesMapToExpectedFieldsAndReason()
    {
        $created = self::client(KmipVersion::Version14)->parseResponse(KmipWireExamples::bytes(KmipWireExamples::CREATE_RESPONSE_VERSION14), self::OPERATION_CREATE);
        $this->assertSame('1', $created[ProtocolClient::UNIQUE_IDENTIFIER]['value']);
        $this->assertSame(self::OBJECT_TYPE_SYMMETRIC_KEY, Ttlv::number($created[self::TAG_OBJECT_TYPE], self::TAG_OBJECT_TYPE, Ttlv::ENUMERATION));

        $encrypted = self::client(KmipVersion::Version14)->parseResponse(KmipWireExamples::bytes(KmipWireExamples::ENCRYPT_RESPONSE_VERSION14), ProtocolClient::ENCRYPT);
        $this->assertSame('1', $encrypted[ProtocolClient::UNIQUE_IDENTIFIER]['value']);
        $this->assertSame(hex2bin('0badc0ffee'), $encrypted[ProtocolClient::DATA]['value']);
        $this->assertSame(str_repeat("\0", self::AES_GCM_TAG_LENGTH), $encrypted[ProtocolClient::TAG]['value']);

        try {
            self::client(KmipVersion::Version14)->parseResponse(KmipWireExamples::bytes(KmipWireExamples::ENCRYPT_PERMISSION_DENIED_RESPONSE_VERSION14), ProtocolClient::ENCRYPT);
            $this->fail('The fixed Permission Denied response must fail.');
        } catch (OperationFailed $e) {
            $this->assertSame(self::REASON_PERMISSION_DENIED, $e->reason);
            $this->assertTrue($e->hasOperation);
            $this->assertStringNotContainsString('Active state', $e->getMessage());
        }
    }

    public function testGetExamplesPreserveWrappingFieldsAndRawAesKey()
    {
        $request = Ttlv::items(KmipWireExamples::bytes(KmipWireExamples::GET_REQUEST_VERSION14), KmipVersion::Version14)[0];
        $this->assertSame(self::OPERATION_GET, Ttlv::number(self::find($request, self::TAG_OPERATION, KmipVersion::Version14), self::TAG_OPERATION, Ttlv::ENUMERATION));
        $this->assertSame('2', Ttlv::requireType(self::find($request, self::TAG_UNIQUE_IDENTIFIER, KmipVersion::Version14), self::TAG_UNIQUE_IDENTIFIER, Ttlv::TEXT));
        $this->assertSame(self::MODE_NIST_KEY_WRAP, Ttlv::number(self::find($request, self::TAG_BLOCK_CIPHER_MODE, KmipVersion::Version14), self::TAG_BLOCK_CIPHER_MODE, Ttlv::ENUMERATION));

        $response = Ttlv::items(KmipWireExamples::bytes(KmipWireExamples::GET_RESPONSE_VERSION14), KmipVersion::Version14)[0];
        $this->assertSame(range(0, 31), array_values(unpack('C*', Ttlv::requireType(self::find($response, self::TAG_KEY_MATERIAL, KmipVersion::Version14), self::TAG_KEY_MATERIAL, Ttlv::BYTES))));
    }

    private static function client(KmipVersion $version): ProtocolClient
    {
        return new ProtocolClient(new StreamTransport('localhost', self::DEFAULT_PORT, '/missing/cert', '/missing/key', null, 'localhost', null, self::TIMEOUT_SECONDS), $version, null, null);
    }

    /**
     * @param array{tag: int, type: int, value: string} $item
     */
    private function assertWellFormedChildren(array $item, KmipVersion $version): void
    {
        if (Ttlv::STRUCTURE !== $item['type']) {
            return;
        }
        foreach (Ttlv::children($item, $item['tag'], $version) as $child) {
            $this->assertWellFormedChildren($child, $version);
        }
    }

    /**
     * @param array{tag: int, type: int, value: string} $item
     *
     * @return array{tag: int, type: int, value: string}
     */
    private static function find(array $item, int $tag, KmipVersion $version): array
    {
        if ($tag === $item['tag']) {
            return $item;
        }
        if (Ttlv::STRUCTURE === $item['type']) {
            foreach (Ttlv::children($item, $item['tag'], $version) as $child) {
                try {
                    return self::find($child, $tag, $version);
                } catch (\LogicException) {
                }
            }
        }

        throw new \LogicException('Tag not found in fixed wire example.');
    }
}
