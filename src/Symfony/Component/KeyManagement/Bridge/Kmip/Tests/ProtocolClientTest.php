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
use Symfony\Component\KeyManagement\Bridge\Kmip\Tests\Fixtures\RedactedTraceAssertionsTrait;
use Symfony\Component\KeyManagement\Exception\RuntimeException;

final class ProtocolClientTest extends TestCase
{
    use RedactedTraceAssertionsTrait;

    private const int TAG_BATCH_COUNT = 0x42000D;
    private const int TAG_BATCH_ITEM = 0x42000F;
    private const int TAG_CRITICALITY_INDICATOR = 0x420026;
    private const int TAG_MESSAGE_EXTENSION = 0x420051;
    private const int TAG_OPERATION = 0x42005C;
    private const int TAG_PROTOCOL_VERSION = 0x420069;
    private const int TAG_PROTOCOL_VERSION_MAJOR = 0x42006A;
    private const int TAG_PROTOCOL_VERSION_MINOR = 0x42006B;
    private const int TAG_REQUEST_MESSAGE = 0x420078;
    private const int TAG_RESPONSE_HEADER = 0x42007A;
    private const int TAG_RESPONSE_MESSAGE = 0x42007B;
    private const int TAG_RESPONSE_PAYLOAD = 0x42007C;
    private const int TAG_RESULT_MESSAGE = 0x42007D;
    private const int TAG_RESULT_REASON = 0x42007E;
    private const int TAG_RESULT_STATUS = 0x42007F;
    private const int TAG_TIME_STAMP = 0x420092;
    private const int TAG_UNIQUE_BATCH_ITEM_ID = 0x420093;
    private const int TAG_UNIQUE_IDENTIFIER = 0x420094;
    private const int TAG_VENDOR_EXTENSION = 0x42009C;
    private const int TAG_VENDOR_IDENTIFICATION = 0x42009D;
    private const int TAG_DATA = 0x4200C2;
    private const int TAG_CORRELATION_VALUE = 0x4200D6;
    private const int TAG_ATTESTATION_TYPE = 0x4200C7;
    private const int TAG_NONCE = 0x4200C8;
    private const int TAG_NONCE_ID = 0x4200C9;
    private const int TAG_NONCE_VALUE = 0x4200CA;
    private const int TAG_AUTHENTICATED_ENCRYPTION_TAG = 0x4200FF;
    private const int TAG_CLIENT_CORRELATION_VALUE = 0x420105;
    private const int TAG_SERVER_CORRELATION_VALUE = 0x420106;
    private const int TAG_SERVER_HASHED_PASSWORD = 0x420155;
    private const int TAG_UNKNOWN_TEST_FIELD = 0x420777;
    private const int TAG_VENDOR_DEFINED_TEST_FIELD = 0x540001;
    private const int TYPE_STRUCTURE = 1;
    private const int TYPE_INTEGER = 2;
    private const int TYPE_ENUMERATION = 5;
    private const int TYPE_BOOLEAN = 6;
    private const int TYPE_TEXT_STRING = 7;
    private const int TYPE_BYTE_STRING = 8;
    private const int TYPE_DATE_TIME = 9;
    private const int TYPE_DATE_TIME_EXTENDED = 11;

    private const int OPERATION_ENCRYPT = 0x1F;
    private const int OPERATION_DECRYPT = 0x20;
    private const int REASON_OBJECT_DESTROYED = 0x36;
    private const int REASON_PERMISSION_DENIED = 0x0C;
    private const int REASON_GENERAL_FAILURE = 0x100;
    private const int REASON_UNKNOWN_OBJECT_GROUP = 0x4A;
    private const int REASON_CONSTRAINT_VIOLATION = 0x4B;
    private const int REASON_DUPLICATE_PROCESS_REQUEST = 0x4C;
    private const string REASON_VENDOR_DEFINED = '0x80000002';

    private const int STATUS_SUCCESS = 0;
    private const int STATUS_OPERATION_FAILED = 1;
    private const int STATUS_OPERATION_PENDING = 2;
    private const int STATUS_OPERATION_UNDONE = 3;
    private const int REASON_ITEM_NOT_FOUND = 1;
    private const int REASON_INVALID_MESSAGE = 4;
    private const int AES_GCM_IV_LENGTH = 12;
    private const int AES_GCM_TAG_LENGTH = 16;
    private const int DEFAULT_PORT = 5696;
    private const int TIMEOUT_SECONDS = 10;
    private const int ITEM_HEADER_LENGTH = 8;
    private const int ITEM_ALIGNMENT = 8;
    private const int TAG_LENGTH = 3;
    private const int VERSION_MINOR_VALUE_BYTE_OFFSET = 51;
    private const int VERSION_20_MAJOR = 2;
    private const int VERSION_20_MINOR = 0;
    private const int CRITICALITY_VALUE_LAST_BYTE_OFFSET = 15;
    private const int SINGLE_BATCH_COUNT = 1;
    private const int INVALID_BATCH_COUNT = 2;
    private const string LEGACY_ERROR_VERSION = '1.0';
    private const int REQUEST_MESSAGE_OFFSET = 0;
    private const int REQUEST_HEADER_OFFSET = 8;
    private const int AUTHENTICATION_OFFSET = 56;
    private const int CREDENTIAL_OFFSET = 64;
    private const int CREDENTIAL_TYPE_OFFSET = 72;
    private const int USERNAME_OFFSET = 96;
    private const int PASSWORD_OFFSET = 112;
    private const int BATCH_COUNT_OFFSET = 128;
    private const int CREDENTIAL_FIELD_LENGTH = 16;
    private const int TAG_AND_TYPE_LENGTH = 4;

    #[DataProvider('requestHeaders')]
    public function testRequestHeaderHasTheSpecifiedBytes(KmipVersion $version, int $major, int $minor)
    {
        $client = self::client($version);
        $frame = $client->buildRequest(self::OPERATION_ENCRYPT, '');
        $expected = '4200780100000060'
            .'4200770100000038'
            .'4200690100000020'
            .'42006a0200000004'.\sprintf('%08x', $major).'00000000'
            .'42006b0200000004'.\sprintf('%08x', $minor).'00000000'
            .'42000d02000000040000000100000000'
            .'42000f0100000018'
            .'42005c05000000040000001f00000000'
            .'4200790100000000';
        $this->assertSame($expected, bin2hex($frame));
    }

    public static function requestHeaders(): iterable
    {
        yield '1.4' => [KmipVersion::Version14, 1, 4];
        yield '2.0' => [KmipVersion::Version20, 2, 0];
        yield '2.1' => [KmipVersion::Version21, 2, 1];
    }

    #[DataProvider('protocolVersions')]
    public function testCredentialsAreNestedAfterVersionAndBeforeBatchCount(KmipVersion $version)
    {
        $frame = self::client($version, 'é', 's€')->buildRequest(self::OPERATION_ENCRYPT, '');
        $this->assertSame('42007801000000a8', bin2hex(substr($frame, self::REQUEST_MESSAGE_OFFSET, self::ITEM_HEADER_LENGTH)));
        $this->assertSame('4200770100000080', bin2hex(substr($frame, self::REQUEST_HEADER_OFFSET, self::ITEM_HEADER_LENGTH)));
        $this->assertSame('42000c0100000040', bin2hex(substr($frame, self::AUTHENTICATION_OFFSET, self::ITEM_HEADER_LENGTH)));
        $this->assertSame('4200230100000038', bin2hex(substr($frame, self::CREDENTIAL_OFFSET, self::ITEM_HEADER_LENGTH)));
        $this->assertSame('42002405000000040000000100000000', bin2hex(substr($frame, self::CREDENTIAL_TYPE_OFFSET, self::CREDENTIAL_FIELD_LENGTH)));
        $this->assertSame('4200990700000002c3a9000000000000', bin2hex(substr($frame, self::USERNAME_OFFSET, self::CREDENTIAL_FIELD_LENGTH)));
        $this->assertSame('4200a1070000000473e282ac00000000', bin2hex(substr($frame, self::PASSWORD_OFFSET, self::CREDENTIAL_FIELD_LENGTH)));
        $this->assertSame('42000d02', bin2hex(substr($frame, self::BATCH_COUNT_OFFSET, self::TAG_AND_TYPE_LENGTH)));
    }

    public function testBinarySuccessPayloadIsAccepted()
    {
        $payload = self::item(self::TAG_UNIQUE_IDENTIFIER, self::TYPE_TEXT_STRING, 'key').self::item(self::TAG_DATA, self::TYPE_BYTE_STRING, "\0\xFF").self::item(self::TAG_AUTHENTICATED_ENCRYPTION_TAG, self::TYPE_BYTE_STRING, str_repeat('t', self::AES_GCM_TAG_LENGTH));
        $fields = self::client(KmipVersion::Version20)->parseResponse(self::response(KmipVersion::Version20, self::OPERATION_ENCRYPT, self::STATUS_SUCCESS, null, $payload), self::OPERATION_ENCRYPT);
        $this->assertSame("\0\xFF", $fields[self::TAG_DATA]['value']);
    }

    public function testResponseTraceRedactsReturnedData()
    {
        $secret = 'response-plaintext-marker';
        $payload = self::item(self::TAG_UNIQUE_IDENTIFIER, self::TYPE_TEXT_STRING, 'key')
            .self::item(self::TAG_DATA, self::TYPE_TEXT_STRING, $secret)
            .self::item(self::TAG_AUTHENTICATED_ENCRYPTION_TAG, self::TYPE_BYTE_STRING, str_repeat('t', self::AES_GCM_TAG_LENGTH));
        $response = self::response(KmipVersion::Version20, self::OPERATION_ENCRYPT, self::STATUS_SUCCESS, null, $payload);

        $trace = self::traceOf(static fn () => self::client(KmipVersion::Version20)->parseResponse($response, self::OPERATION_ENCRYPT));

        self::assertRedacted($secret, $trace);
        foreach ($trace as $frame) {
            if (!str_starts_with($frame['class'] ?? '', 'Symfony\\Component\\KeyManagement\\Bridge\\Kmip\\')) {
                continue;
            }
            $arguments = $frame['args'] ?? [];
            array_walk_recursive($arguments, static function (mixed $argument) use ($secret): void {
                if (\is_string($argument)) {
                    self::assertStringNotContainsString($secret, $argument);
                }
            });
        }
    }

    #[DataProvider('protocolVersions')]
    public function testEncryptAndDecryptAcceptOptionalCorrelationValue(KmipVersion $version)
    {
        $correlation = self::item(self::TAG_CORRELATION_VALUE, self::TYPE_BYTE_STRING, "\0stream");
        $identifier = self::item(self::TAG_UNIQUE_IDENTIFIER, self::TYPE_TEXT_STRING, 'key');
        $data = self::item(self::TAG_DATA, self::TYPE_BYTE_STRING, 'data');
        $encrypt = $identifier.$data.$correlation.self::item(self::TAG_AUTHENTICATED_ENCRYPTION_TAG, self::TYPE_BYTE_STRING, str_repeat('t', self::AES_GCM_TAG_LENGTH));
        $decrypt = $identifier.$data.$correlation;

        $this->assertSame("\0stream", self::client($version)->parseResponse(self::response($version, self::OPERATION_ENCRYPT, self::STATUS_SUCCESS, null, $encrypt), self::OPERATION_ENCRYPT)[self::TAG_CORRELATION_VALUE]['value']);
        $this->assertSame("\0stream", self::client($version)->parseResponse(self::response($version, self::OPERATION_DECRYPT, self::STATUS_SUCCESS, null, $decrypt), self::OPERATION_DECRYPT)[self::TAG_CORRELATION_VALUE]['value']);
    }

    public function testCorrelationValueMustBeAByteString()
    {
        $payload = self::item(self::TAG_UNIQUE_IDENTIFIER, self::TYPE_TEXT_STRING, 'key')
            .self::item(self::TAG_DATA, self::TYPE_BYTE_STRING, 'data')
            .self::item(self::TAG_CORRELATION_VALUE, self::TYPE_TEXT_STRING, 'stream')
            .self::item(self::TAG_AUTHENTICATED_ENCRYPTION_TAG, self::TYPE_BYTE_STRING, str_repeat('t', self::AES_GCM_TAG_LENGTH));

        $this->expectException(RuntimeException::class);
        self::client(KmipVersion::Version20)->parseResponse(self::response(KmipVersion::Version20, self::OPERATION_ENCRYPT, self::STATUS_SUCCESS, null, $payload), self::OPERATION_ENCRYPT);
    }

    public function testVersion14AcceptsAResultReasonOnSuccess()
    {
        $payload = self::item(self::TAG_UNIQUE_IDENTIFIER, self::TYPE_TEXT_STRING, 'key').self::item(self::TAG_DATA, self::TYPE_BYTE_STRING, '').self::item(self::TAG_AUTHENTICATED_ENCRYPTION_TAG, self::TYPE_BYTE_STRING, str_repeat('t', self::AES_GCM_TAG_LENGTH));
        $fields = self::client(KmipVersion::Version14)->parseResponse(self::response(KmipVersion::Version14, self::OPERATION_ENCRYPT, self::STATUS_SUCCESS, self::REASON_ITEM_NOT_FOUND, $payload), self::OPERATION_ENCRYPT);
        $this->assertSame('key', $fields[self::TAG_UNIQUE_IDENTIFIER]['value']);
    }

    #[DataProvider('protocolVersions')]
    public function testDecryptSuccessRequiresUniqueIdentifier(KmipVersion $version)
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Decrypt response');
        self::client($version)->parseResponse(self::response($version, self::OPERATION_DECRYPT, self::STATUS_SUCCESS, null, self::item(self::TAG_DATA, self::TYPE_BYTE_STRING, 'plaintext')), self::OPERATION_DECRYPT);
    }

    public function testVersion21AcceptsByteStringDataAndRejectsReferences()
    {
        $bytes = self::item(self::TAG_UNIQUE_IDENTIFIER, self::TYPE_TEXT_STRING, 'key').self::item(self::TAG_DATA, self::TYPE_BYTE_STRING, '').self::item(self::TAG_AUTHENTICATED_ENCRYPTION_TAG, self::TYPE_BYTE_STRING, str_repeat('t', self::AES_GCM_TAG_LENGTH));
        $this->assertSame('', self::client(KmipVersion::Version21)->parseResponse(self::response(KmipVersion::Version21, self::OPERATION_ENCRYPT, self::STATUS_SUCCESS, null, $bytes), self::OPERATION_ENCRYPT)[self::TAG_DATA]['value']);

        $enumeration = self::item(self::TAG_UNIQUE_IDENTIFIER, self::TYPE_TEXT_STRING, 'key').self::item(self::TAG_DATA, self::TYPE_ENUMERATION, pack('N', 1)).self::item(self::TAG_AUTHENTICATED_ENCRYPTION_TAG, self::TYPE_BYTE_STRING, str_repeat('t', self::AES_GCM_TAG_LENGTH));
        $this->expectException(RuntimeException::class);
        self::client(KmipVersion::Version21)->parseResponse(self::response(KmipVersion::Version21, self::OPERATION_ENCRYPT, self::STATUS_SUCCESS, null, $enumeration), self::OPERATION_ENCRYPT);
    }

    public function testVersion21ChangesOnlyTheSelectedVersionBytes()
    {
        $payload = self::item(self::TAG_UNIQUE_IDENTIFIER, self::TYPE_TEXT_STRING, 'key').self::item(self::TAG_DATA, self::TYPE_BYTE_STRING, "\0binary");
        $version20Request = self::client(KmipVersion::Version20, 'user', 'password')->buildRequest(self::OPERATION_ENCRYPT, $payload);
        $version21Request = self::client(KmipVersion::Version21, 'user', 'password')->buildRequest(self::OPERATION_ENCRYPT, $payload);
        $this->assertSame(\strlen($version20Request), \strlen($version21Request));
        $this->assertSame(substr_replace($version20Request, "\x01", self::VERSION_MINOR_VALUE_BYTE_OFFSET, 1), $version21Request);
    }

    #[DataProvider('protocolVersions')]
    public function testEncryptAndDecryptPayloadUseTheSameFixedAesGcmFields(KmipVersion $version)
    {
        $payload = (new \ReflectionMethod(AbstractAuthenticatedEncryptionScheme::class, 'payload'))->invoke(new AesGcmEncryptionScheme(), 'key', "\0A", str_repeat("\x01", self::AES_GCM_IV_LENGTH), 'aad');
        $expected = '42009407000000036b65790000000000'
            .'42002b0100000040'
            .'42001105000000040000000900000000'
            .'42002805000000040000000300000000'
            .'4200cd02000000040000006000000000'
            .'4200ce02000000040000001000000000'
            .'4200c208000000020041000000000000'
            .'42003d080000000c'.str_repeat('01', self::AES_GCM_IV_LENGTH).'00000000'
            .'4200fe08000000036161640000000000';
        $this->assertSame($expected, bin2hex($payload));

        $client = self::client($version);
        $encrypt = $client->buildRequest(self::OPERATION_ENCRYPT, $payload);
        $decrypt = $client->buildRequest(self::OPERATION_DECRYPT, $payload.self::item(self::TAG_AUTHENTICATED_ENCRYPTION_TAG, self::TYPE_BYTE_STRING, str_repeat("\x02", self::AES_GCM_TAG_LENGTH)));
        $this->assertSame($expected, bin2hex(substr($encrypt, -\strlen($payload))));
        $this->assertStringEndsWith($expected.'4200ff0800000010'.str_repeat('02', self::AES_GCM_TAG_LENGTH), bin2hex($decrypt));
    }

    public static function protocolVersions(): iterable
    {
        yield '1.4' => [KmipVersion::Version14];
        yield '2.0' => [KmipVersion::Version20];
        yield '2.1' => [KmipVersion::Version21];
    }

    public function testVersion21FailureReasonIsPreserved()
    {
        try {
            self::client(KmipVersion::Version21)->parseResponse(self::response(KmipVersion::Version21, self::OPERATION_DECRYPT, self::STATUS_OPERATION_FAILED, self::REASON_OBJECT_DESTROYED), self::OPERATION_DECRYPT);
            $this->fail('Expected failure.');
        } catch (OperationFailed $e) {
            $this->assertSame(self::REASON_OBJECT_DESTROYED, $e->reason);
            $this->assertTrue($e->hasOperation);
        }
    }

    public function testVendorDefinedFailureReasonIsPreservedOnBothIntegerArchitectures()
    {
        try {
            self::client(KmipVersion::Version20)->parseResponse(self::response(KmipVersion::Version20, self::OPERATION_DECRYPT, self::STATUS_OPERATION_FAILED, self::REASON_VENDOR_DEFINED), self::OPERATION_DECRYPT);
            $this->fail('Expected failure.');
        } catch (OperationFailed $e) {
            $this->assertSame(4 === \PHP_INT_SIZE ? self::REASON_VENDOR_DEFINED : 0x80000002, $e->reason);
            $this->assertSame('KMIP operation failed (reason 0x80000002).', $e->getMessage());
        }
    }

    #[DataProvider('protocolVersions')]
    public function testSuccessAndFailureKeepTheSameMeaningAcrossVersions(KmipVersion $version)
    {
        $payload = self::item(self::TAG_UNIQUE_IDENTIFIER, self::TYPE_TEXT_STRING, 'key').self::item(self::TAG_DATA, self::TYPE_BYTE_STRING, '').self::item(self::TAG_AUTHENTICATED_ENCRYPTION_TAG, self::TYPE_BYTE_STRING, str_repeat('t', self::AES_GCM_TAG_LENGTH));
        $this->assertSame('key', self::client($version)->parseResponse(self::response($version, self::OPERATION_ENCRYPT, self::STATUS_SUCCESS, null, $payload), self::OPERATION_ENCRYPT)[self::TAG_UNIQUE_IDENTIFIER]['value']);

        try {
            self::client($version)->parseResponse(self::response($version, self::OPERATION_ENCRYPT, self::STATUS_OPERATION_FAILED, self::REASON_PERMISSION_DENIED), self::OPERATION_ENCRYPT);
            $this->fail('Permission Denied must fail.');
        } catch (OperationFailed $e) {
            $this->assertSame(self::REASON_PERMISSION_DENIED, $e->reason);
            $this->assertTrue($e->hasOperation);
        }
    }

    #[DataProvider('version21Reasons')]
    public function testVersion21OnlyReasonsRemainOperationalFailures(int $reason)
    {
        foreach ([ProtocolClient::ENCRYPT, ProtocolClient::DECRYPT] as $operation) {
            try {
                self::client(KmipVersion::Version21)->parseResponse(self::response(KmipVersion::Version21, $operation, self::STATUS_OPERATION_FAILED, $reason), $operation);
                $this->fail('The operation must fail.');
            } catch (OperationFailed $e) {
                $this->assertSame($reason, $e->reason);
                $this->assertTrue($e->hasOperation);
            }
        }
    }

    public static function version21Reasons(): iterable
    {
        yield 'Unknown Object Group' => [self::REASON_UNKNOWN_OBJECT_GROUP];
        yield 'Constraint Violation' => [self::REASON_CONSTRAINT_VIOLATION];
        yield 'Duplicate Process Request' => [self::REASON_DUPLICATE_PROCESS_REQUEST];
    }

    public function testOuterFailureWithNoOperationIsOperational()
    {
        $client = self::client(KmipVersion::Version20);
        try {
            $client->parseResponse(self::response(self::LEGACY_ERROR_VERSION, null, self::STATUS_OPERATION_FAILED, self::REASON_INVALID_MESSAGE, null, '', self::item(self::TAG_RESULT_MESSAGE, self::TYPE_TEXT_STRING, 'server secret')), self::OPERATION_ENCRYPT);
            $this->fail('Expected failure.');
        } catch (OperationFailed $e) {
            $this->assertSame(self::REASON_INVALID_MESSAGE, $e->reason);
            $this->assertFalse($e->hasOperation);
            $this->assertStringNotContainsString('server secret', $e->getMessage());
        }
        try {
            $client->parseResponse(self::response(KmipVersion::Version20, null, self::STATUS_OPERATION_FAILED, self::REASON_GENERAL_FAILURE), self::OPERATION_DECRYPT);
            $this->fail('Expected failure.');
        } catch (OperationFailed $e) {
            $this->assertFalse($e->hasOperation);
        }
    }

    #[DataProvider('invalidResponses')]
    public function testInvalidResponseIsRejected(KmipVersion $version, ?int $operation, int $status, ?int $reason, ?string $payload, string $headerExtra, string $batchExtra, bool $omitTimestamp = false, int $batchCount = self::SINGLE_BATCH_COUNT)
    {
        $this->expectException(RuntimeException::class);
        self::client(KmipVersion::Version20)->parseResponse(self::response($version, $operation, $status, $reason, $payload, $headerExtra, $batchExtra, $omitTimestamp, $batchCount), self::OPERATION_ENCRYPT);
    }

    public static function invalidResponses(): iterable
    {
        $valid = self::item(self::TAG_UNIQUE_IDENTIFIER, self::TYPE_TEXT_STRING, 'key').self::item(self::TAG_DATA, self::TYPE_BYTE_STRING, '').self::item(self::TAG_AUTHENTICATED_ENCRYPTION_TAG, self::TYPE_BYTE_STRING, str_repeat('t', self::AES_GCM_TAG_LENGTH));
        yield 'wrong success version' => [KmipVersion::Version14, self::OPERATION_ENCRYPT, self::STATUS_SUCCESS, null, '', '', ''];
        yield 'wrong operation' => [KmipVersion::Version20, self::OPERATION_DECRYPT, self::STATUS_SUCCESS, null, '', '', ''];
        yield 'no operation on success' => [KmipVersion::Version20, null, self::STATUS_SUCCESS, null, '', '', ''];
        yield 'unknown header field' => [KmipVersion::Version20, self::OPERATION_ENCRYPT, self::STATUS_SUCCESS, null, '', self::item(self::TAG_UNKNOWN_TEST_FIELD, self::TYPE_TEXT_STRING, 'x'), ''];
        yield 'duplicate header field' => [KmipVersion::Version20, self::OPERATION_ENCRYPT, self::STATUS_SUCCESS, null, '', self::item(self::TAG_BATCH_COUNT, self::TYPE_INTEGER, pack('N', self::SINGLE_BATCH_COUNT)), ''];
        yield 'failure without reason' => [KmipVersion::Version20, self::OPERATION_ENCRYPT, self::STATUS_OPERATION_FAILED, null, null, '', ''];
        yield 'pending result' => [KmipVersion::Version20, self::OPERATION_ENCRYPT, self::STATUS_OPERATION_PENDING, null, null, '', ''];
        yield 'undone result' => [KmipVersion::Version20, self::OPERATION_ENCRYPT, self::STATUS_OPERATION_UNDONE, null, null, '', ''];
        yield 'failure at unrelated version' => [KmipVersion::Version14, null, self::STATUS_OPERATION_FAILED, self::REASON_INVALID_MESSAGE, null, '', ''];
        yield 'header out of order' => [KmipVersion::Version20, self::OPERATION_ENCRYPT, self::STATUS_SUCCESS, null, '', self::item(self::TAG_CLIENT_CORRELATION_VALUE, self::TYPE_TEXT_STRING, 'client').self::item(self::TAG_NONCE, self::TYPE_STRUCTURE, ''), ''];
        yield 'batch out of order' => [KmipVersion::Version20, self::OPERATION_ENCRYPT, self::STATUS_SUCCESS, null, '', '', self::item(self::TAG_UNIQUE_BATCH_ITEM_ID, self::TYPE_BYTE_STRING, 'id')];
        yield 'reason on success' => [KmipVersion::Version20, self::OPERATION_ENCRYPT, self::STATUS_SUCCESS, self::REASON_ITEM_NOT_FOUND, $valid, '', ''];
        yield 'empty nonce' => [KmipVersion::Version20, self::OPERATION_ENCRYPT, self::STATUS_SUCCESS, null, $valid, self::item(self::TAG_NONCE, self::TYPE_STRUCTURE, ''), ''];
        yield 'missing time stamp' => [KmipVersion::Version20, self::OPERATION_ENCRYPT, self::STATUS_SUCCESS, null, $valid, '', '', true];
        yield 'two batches declared' => [KmipVersion::Version20, self::OPERATION_ENCRYPT, self::STATUS_SUCCESS, null, $valid, '', '', false, self::INVALID_BATCH_COUNT];
        yield 'duplicate payload field' => [KmipVersion::Version20, self::OPERATION_ENCRYPT, self::STATUS_SUCCESS, null, $valid.self::item(self::TAG_DATA, self::TYPE_BYTE_STRING, 'again'), '', ''];
    }

    public function testOptionalHeaderAndNoncriticalExtensionAreAccepted()
    {
        $extension = self::item(self::TAG_MESSAGE_EXTENSION, self::TYPE_STRUCTURE,
            self::item(self::TAG_VENDOR_IDENTIFICATION, self::TYPE_TEXT_STRING, 'vendor')
            .self::item(self::TAG_CRITICALITY_INDICATOR, self::TYPE_BOOLEAN, str_repeat("\0", 8))
            .self::item(self::TAG_VENDOR_EXTENSION, self::TYPE_STRUCTURE, self::item(self::TAG_VENDOR_DEFINED_TEST_FIELD, self::TYPE_TEXT_STRING, 'anything'))
        );
        $payload = self::item(self::TAG_UNIQUE_IDENTIFIER, self::TYPE_TEXT_STRING, 'key').self::item(self::TAG_DATA, self::TYPE_BYTE_STRING, '').self::item(self::TAG_AUTHENTICATED_ENCRYPTION_TAG, self::TYPE_BYTE_STRING, str_repeat('t', self::AES_GCM_TAG_LENGTH));
        $frame = self::response(KmipVersion::Version20, self::OPERATION_ENCRYPT, self::STATUS_SUCCESS, null, $payload, self::item(self::TAG_CLIENT_CORRELATION_VALUE, self::TYPE_TEXT_STRING, 'client'), $extension);
        $this->assertSame('key', self::client(KmipVersion::Version20)->parseResponse($frame, self::OPERATION_ENCRYPT)[self::TAG_UNIQUE_IDENTIFIER]['value']);

        $criticalOffset = strpos($frame, self::item(self::TAG_CRITICALITY_INDICATOR, self::TYPE_BOOLEAN, str_repeat("\0", 8)));
        $this->assertIsInt($criticalOffset);
        $frame[$criticalOffset + self::CRITICALITY_VALUE_LAST_BYTE_OFFSET] = "\x01";
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('critical');
        self::client(KmipVersion::Version20)->parseResponse($frame, self::OPERATION_ENCRYPT);
    }

    #[DataProvider('protocolVersions')]
    public function testDateTimeExtendedInVendorExtensionRespectsProtocolVersion(KmipVersion $version)
    {
        $extension = self::item(self::TAG_MESSAGE_EXTENSION, self::TYPE_STRUCTURE,
            self::item(self::TAG_VENDOR_IDENTIFICATION, self::TYPE_TEXT_STRING, 'vendor')
            .self::item(self::TAG_CRITICALITY_INDICATOR, self::TYPE_BOOLEAN, str_repeat("\0", 8))
            .self::item(self::TAG_VENDOR_EXTENSION, self::TYPE_STRUCTURE, self::item(self::TAG_VENDOR_DEFINED_TEST_FIELD, self::TYPE_DATE_TIME_EXTENDED, hex2bin('00000000000f4241')))
        );
        $payload = self::item(self::TAG_UNIQUE_IDENTIFIER, self::TYPE_TEXT_STRING, 'key').self::item(self::TAG_DATA, self::TYPE_BYTE_STRING, '').self::item(self::TAG_AUTHENTICATED_ENCRYPTION_TAG, self::TYPE_BYTE_STRING, str_repeat('t', self::AES_GCM_TAG_LENGTH));
        $frame = self::response($version, self::OPERATION_ENCRYPT, self::STATUS_SUCCESS, null, $payload, '', $extension);

        if (KmipVersion::Version14 === $version) {
            $this->expectException(RuntimeException::class);
        }

        $this->assertSame('key', self::client($version)->parseResponse($frame, self::OPERATION_ENCRYPT)[self::TAG_UNIQUE_IDENTIFIER]['value']);
    }

    #[DataProvider('protocolVersions')]
    public function testResponseHeaderTimeStampAlwaysRequiresLegacyDateTime(KmipVersion $version)
    {
        $payload = self::item(self::TAG_UNIQUE_IDENTIFIER, self::TYPE_TEXT_STRING, 'key').self::item(self::TAG_DATA, self::TYPE_BYTE_STRING, '').self::item(self::TAG_AUTHENTICATED_ENCRYPTION_TAG, self::TYPE_BYTE_STRING, str_repeat('t', self::AES_GCM_TAG_LENGTH));
        $frame = self::response($version, self::OPERATION_ENCRYPT, self::STATUS_SUCCESS, null, $payload);
        $frame = str_replace(self::item(self::TAG_TIME_STAMP, self::TYPE_DATE_TIME, pack('N2', 0, 1)), self::item(self::TAG_TIME_STAMP, self::TYPE_DATE_TIME_EXTENDED, pack('N2', 0, 1)), $frame);

        $this->expectException(RuntimeException::class);
        self::client($version)->parseResponse($frame, self::OPERATION_ENCRYPT);
    }

    public function testAllDefinedOptionalResponseHeaderFieldsAreAccepted()
    {
        $nonce = self::item(self::TAG_NONCE, self::TYPE_STRUCTURE, self::item(self::TAG_NONCE_ID, self::TYPE_BYTE_STRING, 'id').self::item(self::TAG_NONCE_VALUE, self::TYPE_BYTE_STRING, 'server'));
        $extra = $nonce
            .self::item(self::TAG_SERVER_HASHED_PASSWORD, self::TYPE_BYTE_STRING, 'hash')
            .self::item(self::TAG_ATTESTATION_TYPE, self::TYPE_ENUMERATION, pack('N', 1))
            .self::item(self::TAG_CLIENT_CORRELATION_VALUE, self::TYPE_TEXT_STRING, 'client')
            .self::item(self::TAG_SERVER_CORRELATION_VALUE, self::TYPE_TEXT_STRING, 'server');
        $payload = self::item(self::TAG_UNIQUE_IDENTIFIER, self::TYPE_TEXT_STRING, 'key').self::item(self::TAG_DATA, self::TYPE_BYTE_STRING, '').self::item(self::TAG_AUTHENTICATED_ENCRYPTION_TAG, self::TYPE_BYTE_STRING, str_repeat('t', self::AES_GCM_TAG_LENGTH));

        $this->assertSame('key', self::client(KmipVersion::Version20)->parseResponse(self::response(KmipVersion::Version20, self::OPERATION_ENCRYPT, self::STATUS_SUCCESS, null, $payload, $extra), self::OPERATION_ENCRYPT)[self::TAG_UNIQUE_IDENTIFIER]['value']);
    }

    public function testWrongRootAndGarbageAreRejected()
    {
        $frame = self::response(KmipVersion::Version20, self::OPERATION_ENCRYPT, self::STATUS_SUCCESS, null, '');
        foreach ([self::item(self::TAG_REQUEST_MESSAGE, self::TYPE_STRUCTURE, substr($frame, self::ITEM_HEADER_LENGTH)), $frame."\0"] as $malformed) {
            try {
                self::client(KmipVersion::Version20)->parseResponse($malformed, self::OPERATION_ENCRYPT);
                $this->fail('Malformed response must fail.');
            } catch (RuntimeException) {
                $this->assertTrue(true);
            }
        }
    }

    #[DataProvider('malformedResponseEnvelopes')]
    public function testMalformedResponseEnvelopeIsRejected(string $frame, string $message)
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);
        self::client(KmipVersion::Version20)->parseResponse($frame, self::OPERATION_ENCRYPT);
    }

    #[DataProvider('unsupportedResultStatuses')]
    public function testUnsupportedResultStatusIsReportedClearly(int $status, string $expectedMessage)
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($expectedMessage);

        self::client(KmipVersion::Version20)->parseResponse(self::response(KmipVersion::Version20, self::OPERATION_ENCRYPT, $status, null), self::OPERATION_ENCRYPT);
    }

    public static function unsupportedResultStatuses(): iterable
    {
        yield 'pending' => [self::STATUS_OPERATION_PENDING, 'KMIP operation is pending, which this client does not support.'];
        yield 'undone' => [self::STATUS_OPERATION_UNDONE, 'KMIP operation was undone.'];
    }

    public static function malformedResponseEnvelopes(): iterable
    {
        yield 'oversized frame' => [str_repeat('x', Ttlv::MAX_FRAME + 1), 'KMIP response exceeds the maximum frame size.'];
        yield 'multiple roots' => [self::item(self::TAG_RESPONSE_MESSAGE, self::TYPE_STRUCTURE, '').self::item(self::TAG_RESPONSE_MESSAGE, self::TYPE_STRUCTURE, ''), 'Invalid KMIP response message.'];
        yield 'missing batch item' => [self::item(self::TAG_RESPONSE_MESSAGE, self::TYPE_STRUCTURE, self::item(self::TAG_RESPONSE_HEADER, self::TYPE_STRUCTURE, '')), 'KMIP response must contain one batch item.'];

        $valid = self::response(KmipVersion::Version20, self::OPERATION_ENCRYPT, self::STATUS_SUCCESS, null, '');
        $message = Ttlv::children(Ttlv::items($valid, KmipVersion::Version20)[0], self::TAG_RESPONSE_MESSAGE, KmipVersion::Version20);
        $header = $message[0]['value'];
        $batch = $message[1]['value'];
        $major = self::item(self::TAG_PROTOCOL_VERSION_MAJOR, self::TYPE_INTEGER, pack('N', self::VERSION_20_MAJOR));
        $minor = self::item(self::TAG_PROTOCOL_VERSION_MINOR, self::TYPE_INTEGER, pack('N', self::VERSION_20_MINOR));
        $version = self::item(self::TAG_PROTOCOL_VERSION, self::TYPE_STRUCTURE, $major.$minor);
        $missingMinor = str_replace($version, self::item(self::TAG_PROTOCOL_VERSION, self::TYPE_STRUCTURE, $major), $header);
        yield 'missing version minor' => [self::responseWithFields($missingMinor, $batch), 'Invalid KMIP response version.'];

        $status = self::item(self::TAG_RESULT_STATUS, self::TYPE_ENUMERATION, pack('N', self::STATUS_SUCCESS));
        yield 'missing result status' => [self::responseWithFields($header, str_replace($status, '', $batch)), 'Missing KMIP result status.'];

        $extension = self::item(self::TAG_MESSAGE_EXTENSION, self::TYPE_STRUCTURE, self::item(self::TAG_VENDOR_IDENTIFICATION, self::TYPE_TEXT_STRING, 'vendor'));
        yield 'incomplete extension' => [self::response(KmipVersion::Version20, self::OPERATION_ENCRYPT, self::STATUS_SUCCESS, null, '', '', $extension), 'Malformed KMIP message extension.'];
    }

    public function testVersion14RejectsServerHashedPasswordInResponseHeader()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('KMIP response header field is unavailable in this protocol version.');
        self::client(KmipVersion::Version14)->parseResponse(self::response(KmipVersion::Version14, self::OPERATION_ENCRYPT, self::STATUS_SUCCESS, null, '', self::item(self::TAG_SERVER_HASHED_PASSWORD, self::TYPE_BYTE_STRING, 'hash')), self::OPERATION_ENCRYPT);
    }

    public function testUniqueBatchItemIdentifierIsAccepted()
    {
        $valid = self::response(KmipVersion::Version20, self::OPERATION_ENCRYPT, self::STATUS_SUCCESS, null, self::validEncryptPayload());
        $message = Ttlv::children(Ttlv::items($valid, KmipVersion::Version20)[0], self::TAG_RESPONSE_MESSAGE, KmipVersion::Version20);
        $batch = $message[1]['value'];
        $status = self::item(self::TAG_RESULT_STATUS, self::TYPE_ENUMERATION, pack('N', self::STATUS_SUCCESS));
        $batch = str_replace($status, self::item(self::TAG_UNIQUE_BATCH_ITEM_ID, self::TYPE_BYTE_STRING, 'id').$status, $batch);

        $this->assertSame('key', self::client(KmipVersion::Version20)->parseResponse(self::responseWithFields($message[0]['value'], $batch), self::OPERATION_ENCRYPT)[self::TAG_UNIQUE_IDENTIFIER]['value']);
    }

    #[DataProvider('invalidCriticalityIndicators')]
    public function testProtocolClientRejectsMalformedCriticalityIndicators(string $value)
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Malformed KMIP criticality indicator.');
        (new \ReflectionMethod(ProtocolClient::class, 'requireCriticalityIndicator'))->invoke(null, ['tag' => self::TAG_CRITICALITY_INDICATOR, 'type' => Ttlv::BOOLEAN, 'value' => $value]);
    }

    public static function invalidCriticalityIndicators(): iterable
    {
        yield 'short Boolean' => [str_repeat("\0", 7)];
        yield 'nonzero leading byte' => ["\1".str_repeat("\0", 7)];
        yield 'value other than zero or one' => [str_repeat("\0", 7)."\2"];
    }

    #[DataProvider('invalidEncryptPayloads')]
    public function testEncryptResponseRequiresAuthenticatedFields(string $payload, string $message)
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);
        self::client(KmipVersion::Version20)->parseResponse(self::response(KmipVersion::Version20, self::OPERATION_ENCRYPT, self::STATUS_SUCCESS, null, $payload), self::OPERATION_ENCRYPT);
    }

    public static function invalidEncryptPayloads(): iterable
    {
        $id = self::item(self::TAG_UNIQUE_IDENTIFIER, self::TYPE_TEXT_STRING, 'key');
        $data = self::item(self::TAG_DATA, self::TYPE_BYTE_STRING, 'plaintext');
        $tag = self::item(self::TAG_AUTHENTICATED_ENCRYPTION_TAG, self::TYPE_BYTE_STRING, str_repeat('t', self::AES_GCM_TAG_LENGTH));

        yield 'missing identifier' => [$data.$tag, 'Missing KMIP Encrypt response field.'];
        yield 'missing data' => [$id.$tag, 'Missing KMIP Encrypt response field.'];
        yield 'missing authentication tag' => [$id.$data, 'Missing KMIP Encrypt response field.'];
        yield 'wrong IV type' => [$id.$data.self::item(ProtocolClient::IV, self::TYPE_ENUMERATION, pack('N', 1)).$tag, 'Unexpected KMIP tag or type.'];
    }

    public function testVersion14AcceptsUnrestrictedVendorIdentification()
    {
        $extension = self::item(self::TAG_MESSAGE_EXTENSION, self::TYPE_STRUCTURE,
            self::item(self::TAG_VENDOR_IDENTIFICATION, self::TYPE_TEXT_STRING, 'vendor-name')
            .self::item(self::TAG_CRITICALITY_INDICATOR, self::TYPE_BOOLEAN, str_repeat("\0", 8))
            .self::item(self::TAG_VENDOR_EXTENSION, self::TYPE_STRUCTURE, self::item(self::TAG_VENDOR_DEFINED_TEST_FIELD, self::TYPE_TEXT_STRING, 'data'))
        );
        $payload = self::item(self::TAG_UNIQUE_IDENTIFIER, self::TYPE_TEXT_STRING, 'key').self::item(self::TAG_DATA, self::TYPE_BYTE_STRING, '').self::item(self::TAG_AUTHENTICATED_ENCRYPTION_TAG, self::TYPE_BYTE_STRING, str_repeat('t', self::AES_GCM_TAG_LENGTH));

        $this->assertSame('key', self::client(KmipVersion::Version14)->parseResponse(self::response(KmipVersion::Version14, self::OPERATION_ENCRYPT, self::STATUS_SUCCESS, null, $payload, '', $extension), self::OPERATION_ENCRYPT)[self::TAG_UNIQUE_IDENTIFIER]['value']);

        $this->expectException(RuntimeException::class);
        self::client(KmipVersion::Version20)->parseResponse(self::response(KmipVersion::Version20, self::OPERATION_ENCRYPT, self::STATUS_SUCCESS, null, $payload, '', $extension), self::OPERATION_ENCRYPT);
    }

    public function testInvalidVendorIdentificationIsRejected()
    {
        $extension = self::item(self::TAG_MESSAGE_EXTENSION, self::TYPE_STRUCTURE,
            self::item(self::TAG_VENDOR_IDENTIFICATION, self::TYPE_TEXT_STRING, 'vendor!')
            .self::item(self::TAG_CRITICALITY_INDICATOR, self::TYPE_BOOLEAN, str_repeat("\0", 8))
            .self::item(self::TAG_VENDOR_EXTENSION, self::TYPE_STRUCTURE, self::item(self::TAG_VENDOR_DEFINED_TEST_FIELD, self::TYPE_TEXT_STRING, 'data'))
        );
        $payload = self::item(self::TAG_UNIQUE_IDENTIFIER, self::TYPE_TEXT_STRING, 'key').self::item(self::TAG_DATA, self::TYPE_BYTE_STRING, '').self::item(self::TAG_AUTHENTICATED_ENCRYPTION_TAG, self::TYPE_BYTE_STRING, str_repeat('t', self::AES_GCM_TAG_LENGTH));

        $this->expectException(RuntimeException::class);
        self::client(KmipVersion::Version20)->parseResponse(self::response(KmipVersion::Version20, self::OPERATION_ENCRYPT, self::STATUS_SUCCESS, null, $payload, '', $extension), self::OPERATION_ENCRYPT);
    }

    private static function client(KmipVersion $version, ?string $username = null, ?string $password = null): ProtocolClient
    {
        return new ProtocolClient(new StreamTransport('localhost', self::DEFAULT_PORT, '/missing/cert', '/missing/key', null, 'localhost', null, self::TIMEOUT_SECONDS), $version, $username, $password);
    }

    private static function validEncryptPayload(): string
    {
        return self::item(self::TAG_UNIQUE_IDENTIFIER, self::TYPE_TEXT_STRING, 'key')
            .self::item(self::TAG_DATA, self::TYPE_BYTE_STRING, '')
            .self::item(self::TAG_AUTHENTICATED_ENCRYPTION_TAG, self::TYPE_BYTE_STRING, str_repeat('t', self::AES_GCM_TAG_LENGTH));
    }

    private static function responseWithFields(string $header, string $batch): string
    {
        return self::item(self::TAG_RESPONSE_MESSAGE, self::TYPE_STRUCTURE,
            self::item(self::TAG_RESPONSE_HEADER, self::TYPE_STRUCTURE, $header)
            .self::item(self::TAG_BATCH_ITEM, self::TYPE_STRUCTURE, $batch)
        );
    }

    private static function response(KmipVersion|string $version, ?int $operation, int $status, int|string|null $reason, ?string $payload = null, string $headerExtra = '', string $batchExtra = '', bool $omitTimestamp = false, int $batchCount = self::SINGLE_BATCH_COUNT): string
    {
        $version = $version instanceof KmipVersion ? $version->value : $version;
        [$major, $minor] = array_map(intval(...), explode('.', $version));
        $header = self::item(self::TAG_PROTOCOL_VERSION, self::TYPE_STRUCTURE, self::item(self::TAG_PROTOCOL_VERSION_MAJOR, self::TYPE_INTEGER, pack('N', $major)).self::item(self::TAG_PROTOCOL_VERSION_MINOR, self::TYPE_INTEGER, pack('N', $minor)))
            .($omitTimestamp ? '' : self::item(self::TAG_TIME_STAMP, self::TYPE_DATE_TIME, pack('N2', 0, 1))).$headerExtra.self::item(self::TAG_BATCH_COUNT, self::TYPE_INTEGER, pack('N', $batchCount));
        $batch = (null !== $operation ? self::item(self::TAG_OPERATION, self::TYPE_ENUMERATION, pack('N', $operation)) : '')
            .self::item(self::TAG_RESULT_STATUS, self::TYPE_ENUMERATION, pack('N', $status))
            .(null !== $reason ? Ttlv::integer(self::TAG_RESULT_REASON, $reason, Ttlv::ENUMERATION) : '')
            .(null !== $payload ? self::item(self::TAG_RESPONSE_PAYLOAD, self::TYPE_STRUCTURE, $payload) : '')
            .$batchExtra;

        return self::item(self::TAG_RESPONSE_MESSAGE, self::TYPE_STRUCTURE, self::item(self::TAG_RESPONSE_HEADER, self::TYPE_STRUCTURE, $header).self::item(self::TAG_BATCH_ITEM, self::TYPE_STRUCTURE, $batch));
    }

    private static function item(int $tag, int $type, string $value): string
    {
        $length = \strlen($value);

        return substr(pack('N', $tag), -self::TAG_LENGTH).\chr($type).pack('N', $length).$value.str_repeat("\0", (self::ITEM_ALIGNMENT - $length % self::ITEM_ALIGNMENT) % self::ITEM_ALIGNMENT);
    }
}
