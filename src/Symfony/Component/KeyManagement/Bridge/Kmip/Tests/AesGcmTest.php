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
use Symfony\Component\KeyManagement\Bridge\Kmip\Kmip\AesGcm;
use Symfony\Component\KeyManagement\Bridge\Kmip\Kmip\ProtocolClient;
use Symfony\Component\KeyManagement\Bridge\Kmip\Kmip\ProtocolVersion;
use Symfony\Component\KeyManagement\Bridge\Kmip\Kmip\RequestClientInterface;
use Symfony\Component\KeyManagement\Bridge\Kmip\Kmip\Ttlv;
use Symfony\Component\KeyManagement\Ciphertext;
use Symfony\Component\KeyManagement\Exception\DecryptionFailedException;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;
use Symfony\Component\KeyManagement\Exception\RuntimeException;

final class AesGcmTest extends TestCase
{
    private const int TAG_BLOCK_CIPHER_MODE = 0x420011;
    private const int TAG_CRYPTOGRAPHIC_ALGORITHM = 0x420028;
    private const int TAG_CRYPTOGRAPHIC_PARAMETERS = 0x42002B;
    private const int TAG_IV_LENGTH = 0x4200CD;
    private const int TAG_TAG_LENGTH = 0x4200CE;
    private const int ALGORITHM_AES = 3;
    private const int BLOCK_MODE_GCM = 9;
    private const int HEADER_LENGTH = 10;
    private const int TAG_LENGTH_BYTES = 16;

    #[DataProvider('ivLengths')]
    public function testEncryptAndDecryptSendAuthenticatedRequests(int $ivLength)
    {
        $requests = [];
        $client = self::client(static function (int $operation, string $payload) use (&$requests): array {
            $fields = self::fields($payload);
            $requests[] = [$operation, $fields];

            if (ProtocolClient::ENCRYPT === $operation) {
                return [
                    ProtocolClient::UNIQUE_IDENTIFIER => self::field(ProtocolClient::UNIQUE_IDENTIFIER, Ttlv::TEXT, 'key'),
                    ProtocolClient::DATA => self::field(ProtocolClient::DATA, Ttlv::BYTES, 'encrypted'),
                    ProtocolClient::TAG => self::field(ProtocolClient::TAG, Ttlv::BYTES, str_repeat('t', self::TAG_LENGTH_BYTES)),
                    ProtocolClient::IV => $fields[ProtocolClient::IV],
                ];
            }

            return [
                ProtocolClient::UNIQUE_IDENTIFIER => self::field(ProtocolClient::UNIQUE_IDENTIFIER, Ttlv::TEXT, 'key'),
                ProtocolClient::DATA => self::field(ProtocolClient::DATA, Ttlv::BYTES, 'plaintext'),
            ];
        });

        $cipher = new AesGcm($ivLength);
        $encrypted = $cipher->encrypt($client, 'key', 'plaintext', 'aad');
        $header = "\x01".pack('NN', self::ALGORITHM_AES, self::BLOCK_MODE_GCM).\chr($ivLength);

        $this->assertSame('key', $encrypted->keyId);
        $this->assertSame($header, substr($encrypted->blob, 0, self::HEADER_LENGTH));
        $this->assertSame(self::HEADER_LENGTH + $ivLength + self::TAG_LENGTH_BYTES + \strlen('encrypted'), \strlen($encrypted->blob));
        $this->assertSame('plaintext', $cipher->decrypt($client, $encrypted, 'aad'));
        $this->assertSame([ProtocolClient::ENCRYPT, ProtocolClient::DECRYPT], array_column($requests, 0));

        foreach ($requests as [, $fields]) {
            $this->assertSame('key', $fields[ProtocolClient::UNIQUE_IDENTIFIER]['value']);
            $this->assertSame($header.'aad', $fields[ProtocolClient::AAD]['value']);
            $this->assertSame($ivLength, \strlen($fields[ProtocolClient::IV]['value']));
            $parameters = Ttlv::children($fields[self::TAG_CRYPTOGRAPHIC_PARAMETERS], self::TAG_CRYPTOGRAPHIC_PARAMETERS, ProtocolVersion::Version20);
            $this->assertSame([self::TAG_BLOCK_CIPHER_MODE, self::TAG_CRYPTOGRAPHIC_ALGORITHM, self::TAG_IV_LENGTH, self::TAG_TAG_LENGTH], array_column($parameters, 'tag'));
            $this->assertSame(self::BLOCK_MODE_GCM, Ttlv::number($parameters[0], self::TAG_BLOCK_CIPHER_MODE, Ttlv::ENUMERATION));
            $this->assertSame(self::ALGORITHM_AES, Ttlv::number($parameters[1], self::TAG_CRYPTOGRAPHIC_ALGORITHM, Ttlv::ENUMERATION));
            $this->assertSame($ivLength * 8, Ttlv::number($parameters[2], self::TAG_IV_LENGTH));
            $this->assertSame(self::TAG_LENGTH_BYTES, Ttlv::number($parameters[3], self::TAG_TAG_LENGTH));
        }
        $this->assertSame('plaintext', $requests[0][1][ProtocolClient::DATA]['value']);
        $this->assertSame('encrypted', $requests[1][1][ProtocolClient::DATA]['value']);
        $this->assertSame(str_repeat('t', self::TAG_LENGTH_BYTES), $requests[1][1][ProtocolClient::TAG]['value']);
        $this->assertSame($requests[0][1][ProtocolClient::IV]['value'], $requests[1][1][ProtocolClient::IV]['value']);
    }

    public static function ivLengths(): iterable
    {
        yield 'default 12-byte IV' => [12];
        yield '16-byte IV' => [16];
    }

    public function testTheDefaultIvLengthIsTwelveBytes()
    {
        $encrypted = (new AesGcm())->encrypt(self::encryptingClient(), 'key', 'x', '');

        $this->assertSame(12, \ord($encrypted->blob[self::HEADER_LENGTH - 1]));
    }

    #[DataProvider('ivLengthChanges')]
    public function testTheStoredIvLengthIsReadAfterTheConfiguredOneChanges(int $oldLength, int $newLength)
    {
        $requests = [];
        $client = self::client(static function (int $operation, string $payload) use (&$requests): array {
            $requests[] = self::fields($payload);

            $response = [
                ProtocolClient::UNIQUE_IDENTIFIER => self::field(ProtocolClient::UNIQUE_IDENTIFIER, Ttlv::TEXT, 'key'),
                ProtocolClient::DATA => self::field(ProtocolClient::DATA, Ttlv::BYTES, ProtocolClient::ENCRYPT === $operation ? 'encrypted' : 'plaintext'),
            ];
            if (ProtocolClient::ENCRYPT === $operation) {
                $response[ProtocolClient::TAG] = self::field(ProtocolClient::TAG, Ttlv::BYTES, str_repeat('t', self::TAG_LENGTH_BYTES));
            }

            return $response;
        });

        $ciphertext = (new AesGcm($oldLength))->encrypt($client, 'key', 'plaintext', 'aad');

        $this->assertSame('plaintext', (new AesGcm($newLength))->decrypt($client, $ciphertext, 'aad'));
        $this->assertSame($requests[0][ProtocolClient::IV]['value'], $requests[1][ProtocolClient::IV]['value']);
        $this->assertSame($requests[0][ProtocolClient::AAD]['value'], $requests[1][ProtocolClient::AAD]['value']);
    }

    public static function ivLengthChanges(): iterable
    {
        yield '12 to 16 bytes' => [12, 16];
        yield '16 to 12 bytes' => [16, 12];
    }

    #[DataProvider('malformedCiphertexts')]
    public function testMalformedCiphertextIsRejectedBeforeARequest(string $blob)
    {
        $client = self::client(static fn (): array => throw new \LogicException('No request should be sent.'));

        $this->expectException(DecryptionFailedException::class);
        (new AesGcm())->decrypt($client, new Ciphertext($blob, 'key'), 'aad');
    }

    public static function malformedCiphertexts(): iterable
    {
        $tag = str_repeat('t', self::TAG_LENGTH_BYTES);

        yield 'shorter than a header and a tag' => ["\x01".pack('NN', self::ALGORITHM_AES, self::BLOCK_MODE_GCM)."\x0C"];
        yield 'unknown format' => ["\x02".pack('NN', self::ALGORITHM_AES, self::BLOCK_MODE_GCM)."\x0C".str_repeat('i', 12).$tag];
        yield 'another algorithm' => ["\x01".pack('NN', 0x1E, self::BLOCK_MODE_GCM)."\x0C".str_repeat('i', 12).$tag];
        yield 'another mode' => ["\x01".pack('NN', self::ALGORITHM_AES, 0x80000002)."\x0C".str_repeat('i', 12).$tag];
        yield 'IV shorter than 12 bytes' => ["\x01".pack('NN', self::ALGORITHM_AES, self::BLOCK_MODE_GCM)."\x0B".str_repeat('i', 11).$tag];
        yield 'truncated IV' => ["\x01".pack('NN', self::ALGORITHM_AES, self::BLOCK_MODE_GCM)."\x10".str_repeat('i', 12).$tag];
    }

    public function testEncryptRejectsTooLongCiphertext()
    {
        $client = self::client(static fn (): array => [
            ProtocolClient::UNIQUE_IDENTIFIER => self::field(ProtocolClient::UNIQUE_IDENTIFIER, Ttlv::TEXT, 'key'),
            ProtocolClient::DATA => self::field(ProtocolClient::DATA, Ttlv::BYTES, 'too long'),
            ProtocolClient::TAG => self::field(ProtocolClient::TAG, Ttlv::BYTES, str_repeat('t', self::TAG_LENGTH_BYTES)),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('KMIP returned invalid authenticated ciphertext.');
        (new AesGcm())->encrypt($client, 'key', 'x', 'aad');
    }

    #[DataProvider('invalidEncryptResponses')]
    public function testEncryptRejectsMissingOrAlteredFields(string $fault, string $message)
    {
        $client = self::client(static function (int $operation, string $payload) use ($fault): array {
            $fields = [
                ProtocolClient::UNIQUE_IDENTIFIER => self::field(ProtocolClient::UNIQUE_IDENTIFIER, Ttlv::TEXT, 'key'),
                ProtocolClient::DATA => self::field(ProtocolClient::DATA, Ttlv::BYTES, 'x'),
                ProtocolClient::TAG => self::field(ProtocolClient::TAG, Ttlv::BYTES, str_repeat('t', self::TAG_LENGTH_BYTES)),
            ];

            match ($fault) {
                'missing tag' => $fields[ProtocolClient::TAG] = null,
                'short tag' => $fields[ProtocolClient::TAG]['value'] = 't',
                'another key identifier' => $fields[ProtocolClient::UNIQUE_IDENTIFIER]['value'] = 'another-key',
                'empty key identifier' => $fields[ProtocolClient::UNIQUE_IDENTIFIER]['value'] = '',
                'changed IV' => $fields[ProtocolClient::IV] = self::field(ProtocolClient::IV, Ttlv::BYTES, str_repeat('i', 12)),
            };

            return array_filter($fields);
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);
        (new AesGcm())->encrypt($client, 'key', 'x', 'aad');
    }

    public static function invalidEncryptResponses(): iterable
    {
        yield 'missing tag' => ['missing tag', 'Missing KMIP response payload field.'];
        yield 'short tag' => ['short tag', 'KMIP returned invalid authenticated ciphertext.'];
        yield 'another key identifier' => ['another key identifier', 'KMIP returned a different key identifier.'];
        yield 'empty key identifier' => ['empty key identifier', 'KMIP returned a different key identifier.'];
        yield 'changed IV' => ['changed IV', 'KMIP returned a different encryption IV.'];
    }

    public function testDecryptRejectsAnotherKeyIdentifier()
    {
        $client = self::client(static fn (): array => [
            ProtocolClient::UNIQUE_IDENTIFIER => self::field(ProtocolClient::UNIQUE_IDENTIFIER, Ttlv::TEXT, 'another-key'),
            ProtocolClient::DATA => self::field(ProtocolClient::DATA, Ttlv::BYTES, 'x'),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('KMIP returned a different key identifier.');
        (new AesGcm())->decrypt($client, self::ciphertext('x'), 'aad');
    }

    public function testDecryptRejectsAChangedPlaintextLength()
    {
        $client = self::client(static fn (): array => [
            ProtocolClient::UNIQUE_IDENTIFIER => self::field(ProtocolClient::UNIQUE_IDENTIFIER, Ttlv::TEXT, 'key'),
            ProtocolClient::DATA => self::field(ProtocolClient::DATA, Ttlv::BYTES, 'longer'),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('KMIP returned invalid plaintext length.');
        (new AesGcm())->decrypt($client, self::ciphertext('x'), 'aad');
    }

    #[DataProvider('invalidIvLengths')]
    public function testInvalidIvLengthIsRejected(int $length)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The KMIP AES-GCM IV length must be between 12 and 255 bytes.');

        new AesGcm($length);
    }

    public static function invalidIvLengths(): iterable
    {
        yield 'too short' => [11];
        yield 'too long' => [256];
    }

    private static function ciphertext(string $encrypted): Ciphertext
    {
        return new Ciphertext("\x01".pack('NN', self::ALGORITHM_AES, self::BLOCK_MODE_GCM)."\x0C".str_repeat('i', 12).str_repeat('t', self::TAG_LENGTH_BYTES).$encrypted, 'key');
    }

    private static function encryptingClient(): RequestClientInterface
    {
        return self::client(static fn (int $operation, string $payload): array => [
            ProtocolClient::UNIQUE_IDENTIFIER => self::field(ProtocolClient::UNIQUE_IDENTIFIER, Ttlv::TEXT, 'key'),
            ProtocolClient::DATA => self::field(ProtocolClient::DATA, Ttlv::BYTES, 'x'),
            ProtocolClient::TAG => self::field(ProtocolClient::TAG, Ttlv::BYTES, str_repeat('t', self::TAG_LENGTH_BYTES)),
        ]);
    }

    private static function fields(string $payload): array
    {
        $fields = [];
        foreach (Ttlv::items($payload, ProtocolVersion::Version20) as $item) {
            $fields[$item['tag']] = $item;
        }

        return $fields;
    }

    private static function client(\Closure $handler): RequestClientInterface
    {
        return new class($handler) implements RequestClientInterface {
            public function __construct(private \Closure $handler)
            {
            }

            public function request(int $operation, #[\SensitiveParameter] string $payload): array
            {
                return ($this->handler)($operation, $payload);
            }
        };
    }

    private static function field(int $tag, int $type, string $value): array
    {
        return ['tag' => $tag, 'type' => $type, 'value' => $value];
    }
}
