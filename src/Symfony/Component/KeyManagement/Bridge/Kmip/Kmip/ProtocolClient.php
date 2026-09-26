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

use Symfony\Component\KeyManagement\Bridge\Kmip\KmipRequestClientInterface;
use Symfony\Component\KeyManagement\Bridge\Kmip\KmipVersion;
use Symfony\Component\KeyManagement\Exception\RuntimeException;
use Symfony\Component\KeyManagement\KeyMaterial;

/**
 * Builds and validates single-operation KMIP messages over the TLS transport.
 *
 * Requests contain one batch item and the selected protocol version. Optional username and password credentials are encoded in the request header inside the verified TLS connection.
 *
 * Responses are decoded with the selected TTLV version and checked for required envelope fields, field order, batch count and result status. A successful response must identify the requested operation; a failed response may omit it, but must match it when present. A valid Operation Failed result raises {@see OperationFailed}. Encryption schemes check cryptographic payload contents after this client has validated the response envelope.
 *
 * @author Antonio Pauletich <antonio.pauletich95@gmail.com>
 *
 * @internal
 */
final class ProtocolClient implements KmipRequestClientInterface
{
    use KeyMaterial;

    private const int TAG_ASYNCHRONOUS_CORRELATION_VALUE = 0x420006;
    private const int TAG_AUTHENTICATION = 0x42000C;
    private const int TAG_BATCH_COUNT = 0x42000D;
    private const int TAG_BATCH_ITEM = 0x42000F;
    private const int TAG_CREDENTIAL = 0x420023;
    private const int TAG_CREDENTIAL_TYPE = 0x420024;
    private const int TAG_CREDENTIAL_VALUE = 0x420025;
    private const int TAG_CRITICALITY_INDICATOR = 0x420026;
    private const int TAG_MESSAGE_EXTENSION = 0x420051;
    private const int TAG_OBJECT_TYPE = 0x420057;
    private const int TAG_OPERATION = 0x42005C;
    private const int TAG_PROTOCOL_VERSION = 0x420069;
    private const int TAG_PROTOCOL_VERSION_MAJOR = 0x42006A;
    private const int TAG_PROTOCOL_VERSION_MINOR = 0x42006B;
    private const int TAG_REQUEST_HEADER = 0x420077;
    private const int TAG_REQUEST_MESSAGE = 0x420078;
    private const int TAG_REQUEST_PAYLOAD = 0x420079;
    private const int TAG_RESPONSE_HEADER = 0x42007A;
    private const int TAG_RESPONSE_MESSAGE = 0x42007B;
    private const int TAG_RESPONSE_PAYLOAD = 0x42007C;
    private const int TAG_RESULT_MESSAGE = 0x42007D;
    private const int TAG_RESULT_REASON = 0x42007E;
    private const int TAG_RESULT_STATUS = 0x42007F;
    private const int TAG_TIME_STAMP = 0x420092;
    private const int TAG_UNIQUE_BATCH_ITEM_ID = 0x420093;
    private const int TAG_USERNAME = 0x420099;
    private const int TAG_VENDOR_EXTENSION = 0x42009C;
    private const int TAG_VENDOR_IDENTIFICATION = 0x42009D;
    private const int TAG_PASSWORD = 0x4200A1;
    private const int TAG_ATTESTATION_TYPE = 0x4200C7;
    private const int TAG_NONCE = 0x4200C8;
    private const int TAG_NONCE_ID = 0x4200C9;
    private const int TAG_NONCE_VALUE = 0x4200CA;
    private const int TAG_CORRELATION_VALUE = 0x4200D6;
    private const int TAG_CLIENT_CORRELATION_VALUE = 0x420105;
    private const int TAG_SERVER_CORRELATION_VALUE = 0x420106;
    private const int TAG_SERVER_HASHED_PASSWORD = 0x420155;

    private const int OPERATION_CREATE = 1;
    private const int CREDENTIAL_USERNAME_AND_PASSWORD = 1;
    private const int SINGLE_BATCH_ITEM = 1;
    private const int STATUS_SUCCESS = 0;
    private const int STATUS_OPERATION_FAILED = 1;
    private const int STATUS_OPERATION_PENDING = 2;
    private const int STATUS_OPERATION_UNDONE = 3;
    private const int RESPONSE_ROOT_ITEM_COUNT = 1;
    private const int RESPONSE_MESSAGE_ITEM_COUNT = 2;
    private const int ROOT_MESSAGE_INDEX = 0;
    private const int RESPONSE_HEADER_INDEX = 0;
    private const int RESPONSE_BATCH_INDEX = 1;
    private const int VERSION_FIELD_COUNT = 2;
    private const int NONCE_FIELD_COUNT = 2;
    private const int MESSAGE_EXTENSION_FIELD_COUNT = 3;
    /**
     * KMIP Date Time values occupy eight bytes on the wire.
     */
    private const int DATE_TIME_LENGTH = 8;
    /**
     * KMIP Boolean values occupy eight bytes on the wire.
     */
    private const int BOOLEAN_LENGTH = 8;
    private const int NO_PREVIOUS_FIELD_POSITION = -1;
    /**
     * Accepts a version 1.0 response to an outer protocol failure for server compatibility.
     */
    private const string LEGACY_ERROR_VERSION = '1.0';
    private const string VERSION_SEPARATOR = '.';
    /**
     * An empty regular expression in Unicode mode checks UTF-8 validity.
     */
    private const string UTF8_VALIDATION_PATTERN = '//u';
    /**
     * Accepted ASCII characters in KMIP 2.x vendor identifiers.
     */
    private const string VENDOR_IDENTIFICATION_PATTERN = '/^[A-Za-z0-9_.]+$/D';
    /**
     * The eight-byte TTLV Boolean false value for a noncritical message extension.
     */
    private const string NON_CRITICAL_EXTENSION_INDICATOR = "\0\0\0\0\0\0\0\0";
    /**
     * The eight-byte TTLV Boolean true value for a critical message extension.
     */
    private const string CRITICAL_EXTENSION_INDICATOR = "\0\0\0\0\0\0\0\1";
    /**
     * Allowed response header fields in wire order.
     *
     * @var list<int>
     */
    private const array RESPONSE_HEADER_FIELDS = [
        self::TAG_PROTOCOL_VERSION,
        self::TAG_TIME_STAMP,
        self::TAG_NONCE,
        self::TAG_SERVER_HASHED_PASSWORD,
        self::TAG_ATTESTATION_TYPE,
        self::TAG_CLIENT_CORRELATION_VALUE,
        self::TAG_SERVER_CORRELATION_VALUE,
        self::TAG_BATCH_COUNT,
    ];
    /**
     * Fields required in every response header.
     *
     * @var list<int>
     */
    private const array REQUIRED_RESPONSE_HEADER_FIELDS = [self::TAG_PROTOCOL_VERSION, self::TAG_TIME_STAMP, self::TAG_BATCH_COUNT];
    /**
     * Allowed response batch fields in wire order.
     *
     * @var list<int>
     */
    private const array RESPONSE_BATCH_FIELDS = [
        self::TAG_OPERATION,
        self::TAG_UNIQUE_BATCH_ITEM_ID,
        self::TAG_RESULT_STATUS,
        self::TAG_RESULT_REASON,
        self::TAG_RESULT_MESSAGE,
        self::TAG_ASYNCHRONOUS_CORRELATION_VALUE,
        self::TAG_RESPONSE_PAYLOAD,
        self::TAG_MESSAGE_EXTENSION,
    ];

    public function __construct(
        private readonly StreamTransport $transport,
        private readonly KmipVersion $version,
        private readonly ?string $username,
        #[\SensitiveParameter] ?string $password,
    ) {
        if (null !== $password) {
            $this->keepMaterial($password);
        }
    }

    /**
     * @return array<int, array{tag: int, type: int, value: string}>
     */
    public function request(int $operation, #[\SensitiveParameter] string $payload): array
    {
        return $this->parseResponse($this->transport->exchange($this->buildRequest($operation, $payload)), $operation);
    }

    public function buildRequest(int $operation, #[\SensitiveParameter] string $payload): string
    {
        [$major, $minor] = array_map(intval(...), explode(self::VERSION_SEPARATOR, $this->version->value));
        $version = Ttlv::structure(self::TAG_PROTOCOL_VERSION, Ttlv::integer(self::TAG_PROTOCOL_VERSION_MAJOR, $major).Ttlv::integer(self::TAG_PROTOCOL_VERSION_MINOR, $minor));
        $authentication = '';
        if (null !== $this->username) {
            $credentialValue = Ttlv::structure(self::TAG_CREDENTIAL_VALUE, Ttlv::text(self::TAG_USERNAME, $this->username).Ttlv::text(self::TAG_PASSWORD, $this->material()));
            $authentication = Ttlv::structure(self::TAG_AUTHENTICATION, Ttlv::structure(self::TAG_CREDENTIAL, Ttlv::integer(self::TAG_CREDENTIAL_TYPE, self::CREDENTIAL_USERNAME_AND_PASSWORD, Ttlv::ENUMERATION).$credentialValue));
        }
        $header = Ttlv::structure(self::TAG_REQUEST_HEADER, $version.$authentication.Ttlv::integer(self::TAG_BATCH_COUNT, self::SINGLE_BATCH_ITEM));
        $batch = Ttlv::structure(self::TAG_BATCH_ITEM, Ttlv::integer(self::TAG_OPERATION, $operation, Ttlv::ENUMERATION).Ttlv::structure(self::TAG_REQUEST_PAYLOAD, $payload));

        return Ttlv::structure(self::TAG_REQUEST_MESSAGE, $header.$batch);
    }

    /**
     * @return array<int, array{tag: int, type: int, value: string}>
     */
    public function parseResponse(#[\SensitiveParameter] string $frame, int $expectedOperation): array
    {
        if (\strlen($frame) > Ttlv::MAX_FRAME) {
            throw new RuntimeException('KMIP response exceeds the maximum frame size.');
        }
        $outer = Ttlv::items($frame, $this->version);
        if (self::RESPONSE_ROOT_ITEM_COUNT !== \count($outer)) {
            throw new RuntimeException('Invalid KMIP response message.');
        }
        $message = Ttlv::children($outer[self::ROOT_MESSAGE_INDEX], self::TAG_RESPONSE_MESSAGE, $this->version);
        if (self::RESPONSE_MESSAGE_ITEM_COUNT !== \count($message)) {
            throw new RuntimeException('KMIP response must contain one batch item.');
        }
        $header = self::fields(Ttlv::children($message[self::RESPONSE_HEADER_INDEX], self::TAG_RESPONSE_HEADER, $this->version), self::RESPONSE_HEADER_FIELDS, [self::TAG_ATTESTATION_TYPE]);
        foreach (self::REQUIRED_RESPONSE_HEADER_FIELDS as $tag) {
            if (!isset($header[$tag])) {
                throw new RuntimeException('Missing KMIP response header field.');
            }
        }
        $versionFields = self::fields(Ttlv::children($header[self::TAG_PROTOCOL_VERSION], self::TAG_PROTOCOL_VERSION, $this->version), [self::TAG_PROTOCOL_VERSION_MAJOR, self::TAG_PROTOCOL_VERSION_MINOR]);
        if (self::VERSION_FIELD_COUNT !== \count($versionFields)) {
            throw new RuntimeException('Invalid KMIP response version.');
        }
        $version = Ttlv::number($versionFields[self::TAG_PROTOCOL_VERSION_MAJOR], self::TAG_PROTOCOL_VERSION_MAJOR).self::VERSION_SEPARATOR.Ttlv::number($versionFields[self::TAG_PROTOCOL_VERSION_MINOR], self::TAG_PROTOCOL_VERSION_MINOR);
        if (Ttlv::DATE_TIME !== $header[self::TAG_TIME_STAMP]['type'] || self::DATE_TIME_LENGTH !== \strlen($header[self::TAG_TIME_STAMP]['value'])) {
            throw new RuntimeException('Invalid KMIP response time stamp.');
        }
        if (self::SINGLE_BATCH_ITEM !== Ttlv::number($header[self::TAG_BATCH_COUNT], self::TAG_BATCH_COUNT)) {
            throw new RuntimeException('Invalid KMIP response batch count.');
        }
        foreach ($header as $tag => $field) {
            if (\in_array($tag, self::REQUIRED_RESPONSE_HEADER_FIELDS, true)) {
                continue;
            }
            if (self::TAG_SERVER_HASHED_PASSWORD === $tag && KmipVersion::Version14 === $this->version) {
                throw new RuntimeException('KMIP response header field is unavailable in this protocol version.');
            }
            if (\in_array($tag, [self::TAG_CLIENT_CORRELATION_VALUE, self::TAG_SERVER_CORRELATION_VALUE], true)) {
                Ttlv::requireType($field, $tag, Ttlv::TEXT);
            }
            if (self::TAG_ATTESTATION_TYPE === $tag) {
                Ttlv::number($field, $tag, Ttlv::ENUMERATION);
            }
            if (self::TAG_NONCE === $tag) {
                $nonce = self::fields(Ttlv::children($field, $tag, $this->version), [self::TAG_NONCE_ID, self::TAG_NONCE_VALUE]);
                if (self::NONCE_FIELD_COUNT !== \count($nonce)) {
                    throw new RuntimeException('Malformed KMIP response nonce.');
                }
                Ttlv::requireType($nonce[self::TAG_NONCE_ID], self::TAG_NONCE_ID, Ttlv::BYTES);
                Ttlv::requireType($nonce[self::TAG_NONCE_VALUE], self::TAG_NONCE_VALUE, Ttlv::BYTES);
            }
            if (self::TAG_SERVER_HASHED_PASSWORD === $tag) {
                Ttlv::requireType($field, $tag, Ttlv::BYTES);
            }
        }

        $batch = self::fields(Ttlv::children($message[self::RESPONSE_BATCH_INDEX], self::TAG_BATCH_ITEM, $this->version), self::RESPONSE_BATCH_FIELDS);
        if (!isset($batch[self::TAG_RESULT_STATUS])) {
            throw new RuntimeException('Missing KMIP result status.');
        }
        $status = Ttlv::number($batch[self::TAG_RESULT_STATUS], self::TAG_RESULT_STATUS, Ttlv::ENUMERATION);
        if (isset($batch[self::TAG_OPERATION]) && $expectedOperation !== Ttlv::number($batch[self::TAG_OPERATION], self::TAG_OPERATION, Ttlv::ENUMERATION)) {
            throw new RuntimeException('KMIP response operation does not match the request.');
        }
        if (isset($batch[self::TAG_RESULT_MESSAGE])) {
            Ttlv::requireType($batch[self::TAG_RESULT_MESSAGE], self::TAG_RESULT_MESSAGE, Ttlv::TEXT);
        }
        if (isset($batch[self::TAG_UNIQUE_BATCH_ITEM_ID])) {
            Ttlv::requireType($batch[self::TAG_UNIQUE_BATCH_ITEM_ID], self::TAG_UNIQUE_BATCH_ITEM_ID, Ttlv::BYTES);
        }
        if (isset($batch[self::TAG_MESSAGE_EXTENSION])) {
            $this->checkExtension($batch[self::TAG_MESSAGE_EXTENSION]);
        }
        if (self::STATUS_OPERATION_FAILED === $status) {
            if (!isset($batch[self::TAG_RESULT_REASON]) || isset($batch[self::TAG_RESPONSE_PAYLOAD]) || !\in_array($version, [$this->version->value, self::LEGACY_ERROR_VERSION], true) || (self::LEGACY_ERROR_VERSION === $version && isset($batch[self::TAG_OPERATION]))) {
                throw new RuntimeException('Malformed KMIP operation failure.');
            }
            $reason = Ttlv::number($batch[self::TAG_RESULT_REASON], self::TAG_RESULT_REASON, Ttlv::ENUMERATION);

            throw new OperationFailed($reason, isset($batch[self::TAG_OPERATION]));
        }
        if (self::STATUS_OPERATION_PENDING === $status) {
            throw new RuntimeException('KMIP operation is pending, which this client does not support.');
        }
        if (self::STATUS_OPERATION_UNDONE === $status) {
            throw new RuntimeException('KMIP operation was undone.');
        }
        if (self::STATUS_SUCCESS === $status && KmipVersion::Version14->value === $version && isset($batch[self::TAG_RESULT_REASON])) {
            Ttlv::number($batch[self::TAG_RESULT_REASON], self::TAG_RESULT_REASON, Ttlv::ENUMERATION);
        }
        if (self::STATUS_SUCCESS !== $status || $version !== $this->version->value || !isset($batch[self::TAG_OPERATION], $batch[self::TAG_RESPONSE_PAYLOAD]) || isset($batch[self::TAG_RESULT_REASON]) && KmipVersion::Version14->value !== $version || isset($batch[self::TAG_RESULT_MESSAGE]) || isset($batch[self::TAG_ASYNCHRONOUS_CORRELATION_VALUE])) {
            throw new RuntimeException('Malformed KMIP operation success.');
        }

        $payloadFields = match ($expectedOperation) {
            self::OPERATION_CREATE => [self::TAG_OBJECT_TYPE, self::UNIQUE_IDENTIFIER],
            self::ENCRYPT => [self::UNIQUE_IDENTIFIER, self::DATA, self::IV, self::TAG_CORRELATION_VALUE, self::TAG],
            self::DECRYPT => [self::UNIQUE_IDENTIFIER, self::DATA, self::TAG_CORRELATION_VALUE],
            default => [self::UNIQUE_IDENTIFIER],
        };

        $payload = self::fields(Ttlv::children($batch[self::TAG_RESPONSE_PAYLOAD], self::TAG_RESPONSE_PAYLOAD, $this->version), $payloadFields);
        if (self::ENCRYPT === $expectedOperation) {
            foreach ([self::UNIQUE_IDENTIFIER => Ttlv::TEXT, self::DATA => Ttlv::BYTES, self::TAG => Ttlv::BYTES] as $tag => $type) {
                if (!isset($payload[$tag])) {
                    throw new RuntimeException('Missing KMIP Encrypt response field.');
                }
                Ttlv::requireType($payload[$tag], $tag, $type);
            }
            if (isset($payload[self::IV])) {
                Ttlv::requireType($payload[self::IV], self::IV, Ttlv::BYTES);
            }
        } elseif (self::DECRYPT === $expectedOperation) {
            if (!isset($payload[self::UNIQUE_IDENTIFIER], $payload[self::DATA])) {
                throw new RuntimeException('Missing KMIP Decrypt response field.');
            }
            Ttlv::requireType($payload[self::UNIQUE_IDENTIFIER], self::UNIQUE_IDENTIFIER, Ttlv::TEXT);
            Ttlv::requireType($payload[self::DATA], self::DATA, Ttlv::BYTES);
        }
        if (isset($payload[self::TAG_CORRELATION_VALUE])) {
            Ttlv::requireType($payload[self::TAG_CORRELATION_VALUE], self::TAG_CORRELATION_VALUE, Ttlv::BYTES);
        }

        return $payload;
    }

    public function __destruct()
    {
        $this->wipeMaterial();
    }

    /**
     * @param list<array{tag: int, type: int, value: string}> $items
     * @param list<int>                                       $allowed
     * @param list<int>                                       $repeatable
     *
     * @return array<int, array{tag: int, type: int, value: string}>
     */
    private static function fields(#[\SensitiveParameter] array $items, array $allowed, array $repeatable = []): array
    {
        $fields = [];
        $previous = self::NO_PREVIOUS_FIELD_POSITION;
        foreach ($items as $item) {
            $position = array_search($item['tag'], $allowed, true);
            if (false === $position || $position < $previous || isset($fields[$item['tag']]) && !\in_array($item['tag'], $repeatable, true)) {
                throw new RuntimeException(\sprintf('Unknown or duplicate KMIP field 0x%X.', $item['tag']));
            }
            if (\in_array($item['tag'], $repeatable, true)) {
                Ttlv::number($item, $item['tag'], Ttlv::ENUMERATION);
            }
            $fields[$item['tag']] = $item;
            $previous = $position;
        }

        return $fields;
    }

    /**
     * @param array{tag: int, type: int, value: string} $extension
     */
    private function checkExtension(#[\SensitiveParameter] array $extension): void
    {
        $fields = self::fields(Ttlv::children($extension, self::TAG_MESSAGE_EXTENSION, $this->version), [self::TAG_VENDOR_IDENTIFICATION, self::TAG_CRITICALITY_INDICATOR, self::TAG_VENDOR_EXTENSION]);
        if (self::MESSAGE_EXTENSION_FIELD_COUNT !== \count($fields)) {
            throw new RuntimeException('Malformed KMIP message extension.');
        }
        $vendor = Ttlv::requireType($fields[self::TAG_VENDOR_IDENTIFICATION], self::TAG_VENDOR_IDENTIFICATION, Ttlv::TEXT);
        if ('' === $vendor || !preg_match(self::UTF8_VALIDATION_PATTERN, $vendor) || KmipVersion::Version14 !== $this->version && !preg_match(self::VENDOR_IDENTIFICATION_PATTERN, $vendor)) {
            throw new RuntimeException('Invalid KMIP vendor identification.');
        }
        $critical = self::requireCriticalityIndicator($fields[self::TAG_CRITICALITY_INDICATOR]);
        Ttlv::children($fields[self::TAG_VENDOR_EXTENSION], self::TAG_VENDOR_EXTENSION, $this->version);
        if (self::CRITICAL_EXTENSION_INDICATOR === $critical) {
            throw new RuntimeException('Unsupported critical KMIP message extension.');
        }
    }

    /**
     * @param array{tag: int, type: int, value: string} $field
     */
    private static function requireCriticalityIndicator(array $field): string
    {
        $critical = Ttlv::requireType($field, self::TAG_CRITICALITY_INDICATOR, Ttlv::BOOLEAN);
        if (self::BOOLEAN_LENGTH !== \strlen($critical) || !\in_array($critical, [self::NON_CRITICAL_EXTENSION_INDICATOR, self::CRITICAL_EXTENSION_INDICATOR], true)) {
            throw new RuntimeException('Malformed KMIP criticality indicator.');
        }

        return $critical;
    }
}
