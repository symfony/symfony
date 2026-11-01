<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\GoogleCloudKms\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\KeyManagement\Bridge\GoogleCloudKms\GoogleCloudKms;
use Symfony\Component\KeyManagement\Bridge\GoogleCloudKms\TokenProviderInterface;
use Symfony\Component\KeyManagement\Ciphertext;
use Symfony\Component\KeyManagement\Exception\DecryptionFailedException;
use Symfony\Component\KeyManagement\Exception\RuntimeException;

class GoogleCloudKmsIntegrityTest extends TestCase
{
    private const string BASE = 'https://cloudkms.googleapis.com/v1/';
    private const string KEY = 'projects/my-proj/locations/global/keyRings/app/cryptoKeys/master';
    private const string CIPHERTEXT = 'MTIzNDU2Nzg5';

    public static function provideChecksumInputs(): iterable
    {
        yield 'empty' => ['', '0'];
        yield 'Castagnoli test vector' => ['123456789', '3808858755'];
        yield 'binary' => ["\0\xFF\x80", '1097972865'];
    }

    #[DataProvider('provideChecksumInputs')]
    public function testEncryptSendsChecksumsOfTheUnencodedBytes(string $plaintext, string $checksum)
    {
        $request = null;
        $client = new MockHttpClient(static function ($method, $url, array $options) use (&$request, $plaintext): MockResponse {
            $request = json_decode($options['body'], true);

            return new MockResponse(json_encode(self::encryptedResponse('' === $plaintext ? ['verifiedAdditionalAuthenticatedDataCrc32c' => false] : [])));
        }, self::BASE);

        $ciphertext = $this->kms($client)->encrypt(self::KEY, $plaintext, $plaintext);

        $this->assertSame($checksum, $request['plaintextCrc32c'] ?? null);
        if ('' !== $plaintext) {
            $this->assertSame($checksum, $request['additionalAuthenticatedDataCrc32c'] ?? null);
        } else {
            $this->assertArrayNotHasKey('additionalAuthenticatedDataCrc32c', $request);
        }
        $this->assertSame(self::CIPHERTEXT, $ciphertext->blob);
        $this->assertSame(self::KEY, $ciphertext->keyId);
    }

    public function testDecryptSendsChecksumsOfTheUnencodedBytes()
    {
        $request = null;
        $client = new MockHttpClient(static function ($method, $url, array $options) use (&$request): MockResponse {
            $request = json_decode($options['body'], true);

            return new MockResponse(json_encode(['plaintext' => self::CIPHERTEXT, 'plaintextCrc32c' => '3808858755']));
        }, self::BASE);

        $this->assertSame('123456789', $this->kms($client)->decrypt(new Ciphertext(self::CIPHERTEXT, self::KEY), '123456789'));
        $this->assertSame('3808858755', $request['ciphertextCrc32c'] ?? null);
        $this->assertSame('3808858755', $request['additionalAuthenticatedDataCrc32c'] ?? null);
    }

    public static function provideInvalidEncryptIntegrityFields(): iterable
    {
        yield 'missing checksum' => ['ciphertextCrc32c', null];
        yield 'wrong checksum' => ['ciphertextCrc32c', '0'];
        yield 'malformed checksum' => ['ciphertextCrc32c', []];
        yield 'missing plaintext verification' => ['verifiedPlaintextCrc32c', null];
        yield 'unverified plaintext' => ['verifiedPlaintextCrc32c', false];
        yield 'nonboolean plaintext verification' => ['verifiedPlaintextCrc32c', 'true'];
        yield 'missing AAD verification' => ['verifiedAdditionalAuthenticatedDataCrc32c', null];
        yield 'unverified AAD' => ['verifiedAdditionalAuthenticatedDataCrc32c', false];
    }

    #[DataProvider('provideInvalidEncryptIntegrityFields')]
    public function testEncryptRejectsPersistentIntegrityFailures(string $field, mixed $value)
    {
        $requests = 0;
        $client = new MockHttpClient(static function () use (&$requests, $field, $value): MockResponse {
            ++$requests;

            return new MockResponse(json_encode(self::encryptedResponse([$field => $value])));
        }, self::BASE);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('integrity verification failed');
        try {
            $this->kms($client)->encrypt(self::KEY, 'hello', 'context');
        } finally {
            $this->assertSame(3, $requests);
        }
    }

    #[DataProvider('provideInvalidEncryptIntegrityFields')]
    public function testEncryptRetriesTransientIntegrityFailures(string $field, mixed $value)
    {
        $requests = [];
        $client = new MockHttpClient(static function ($method, $url, array $options) use (&$requests, $field, $value): MockResponse {
            $requests[] = $options['body'];

            return new MockResponse(json_encode(self::encryptedResponse(1 === \count($requests) ? [$field => $value] : [])));
        }, self::BASE);

        $this->assertSame(self::CIPHERTEXT, $this->kms($client)->encrypt(self::KEY, 'hello', 'context')->blob);
        $this->assertCount(2, $requests);
        $this->assertSame($requests[0], $requests[1]);
    }

    public static function provideUnexpectedKeyVersions(): iterable
    {
        yield 'missing' => [self::KEY, null];
        yield 'nonstring' => [self::KEY, []];
        yield 'unversioned' => [self::KEY, self::KEY];
        yield 'other key' => [self::KEY, self::KEY.'-other/cryptoKeyVersions/1'];
        yield 'nonnumeric version' => [self::KEY, self::KEY.'/cryptoKeyVersions/one'];
        yield 'nested version' => [self::KEY, self::KEY.'/cryptoKeyVersions/1/cryptoKeyVersions/2'];
        yield 'wrong pinned version' => [self::KEY.'/cryptoKeyVersions/1', self::KEY.'/cryptoKeyVersions/2'];
    }

    #[DataProvider('provideUnexpectedKeyVersions')]
    public function testEncryptRejectsUnexpectedKeyVersions(string $keyId, mixed $name)
    {
        $client = new MockHttpClient(new MockResponse(json_encode(self::encryptedResponse(['name' => $name]))), self::BASE);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('key version');
        $this->kms($client)->encrypt($keyId, 'hello');
    }

    public static function provideInvalidPlaintextChecksums(): iterable
    {
        yield 'missing' => [null];
        yield 'mismatched' => ['1'];
        yield 'malformed' => ['not-a-checksum'];
        yield 'negative' => [-1];
        yield 'out of range' => ['4294967296'];
        yield 'array' => [[]];
        yield 'boolean' => [false];
    }

    #[DataProvider('provideInvalidPlaintextChecksums')]
    public function testDecryptRejectsAnOmittedPlaintextWithAnInvalidChecksum(mixed $checksum)
    {
        $requests = 0;
        $client = new MockHttpClient(static function () use (&$requests, $checksum): MockResponse {
            ++$requests;

            return new MockResponse(json_encode(['plaintextCrc32c' => $checksum]));
        }, self::BASE);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('integrity verification failed');
        try {
            $this->kms($client)->decrypt(new Ciphertext(self::CIPHERTEXT, self::KEY));
        } finally {
            $this->assertSame(3, $requests);
        }
    }

    public function testDecryptRejectsPersistentPlaintextChecksumMismatches()
    {
        $requests = 0;
        $client = new MockHttpClient(static function () use (&$requests): MockResponse {
            ++$requests;

            return new MockResponse(json_encode(['plaintext' => self::CIPHERTEXT, 'plaintextCrc32c' => '0']));
        }, self::BASE);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('integrity verification failed');
        try {
            $this->kms($client)->decrypt(new Ciphertext(self::CIPHERTEXT, self::KEY));
        } finally {
            $this->assertSame(3, $requests);
        }
    }

    public function testDecryptRetriesTransientPlaintextChecksumMismatches()
    {
        $requests = [];
        $client = new MockHttpClient(static function ($method, $url, array $options) use (&$requests): MockResponse {
            $requests[] = $options['body'];

            return new MockResponse(json_encode(['plaintext' => self::CIPHERTEXT, 'plaintextCrc32c' => 1 === \count($requests) ? '0' : '3808858755']));
        }, self::BASE);

        $this->assertSame('123456789', $this->kms($client)->decrypt(new Ciphertext(self::CIPHERTEXT, self::KEY), 'context'));
        $this->assertCount(2, $requests);
        $this->assertSame($requests[0], $requests[1]);
    }

    public static function provideValidPlaintextResponses(): iterable
    {
        yield 'omitted empty plaintext' => [['plaintextCrc32c' => '0'], ''];
        yield 'numeric zero checksum' => [['plaintextCrc32c' => 0], ''];
        yield 'explicit empty plaintext' => [['plaintext' => '', 'plaintextCrc32c' => '0'], ''];
        yield 'unsigned checksum' => [['plaintext' => self::CIPHERTEXT, 'plaintextCrc32c' => '3808858755'], '123456789'];
        yield 'numeric checksum' => [['plaintext' => base64_encode("\0\xFF\x80"), 'plaintextCrc32c' => 1097972865], "\0\xFF\x80"];
    }

    #[DataProvider('provideValidPlaintextResponses')]
    public function testDecryptAcceptsValidChecksums(array $response, string $expected)
    {
        $client = new MockHttpClient(new MockResponse(json_encode($response)), self::BASE);

        $this->assertSame($expected, $this->kms($client)->decrypt(new Ciphertext(self::CIPHERTEXT, self::KEY)));
    }

    public static function provideDataKeyOperations(): iterable
    {
        yield ['generate'];
        yield ['wrap'];
        yield ['unwrap'];
    }

    #[DataProvider('provideDataKeyOperations')]
    public function testDataKeyOperationsRetryIntegrityFailuresWithTheSameKey(string $operation)
    {
        $requests = [];
        $client = new MockHttpClient(static function ($method, $url, array $options) use (&$requests, $operation): MockResponse {
            $requests[] = $options['body'];
            if ('unwrap' === $operation) {
                return new MockResponse(json_encode(['plaintext' => self::CIPHERTEXT, 'plaintextCrc32c' => 1 === \count($requests) ? '0' : '3808858755']));
            }

            return new MockResponse(json_encode(self::encryptedResponse(1 === \count($requests) ? ['ciphertextCrc32c' => '0'] : [])));
        }, self::BASE);
        $kms = $this->kms($client);
        $result = match ($operation) {
            'generate' => $kms->generateDataKey(self::KEY),
            'wrap' => $kms->wrapDataKey(self::KEY, '123456789'),
            'unwrap' => $kms->unwrapDataKey(new Ciphertext(self::CIPHERTEXT, self::KEY)),
        };

        $this->assertCount(2, $requests);
        $this->assertSame($requests[0], $requests[1]);
        if ('generate' === $operation) {
            $this->assertSame($result->use(static fn (string $key): string => base64_encode($key)), json_decode($requests[0], true)['plaintext']);
        } elseif ('unwrap' === $operation) {
            $this->assertSame('123456789', $result->use(static fn (string $key): string => $key));
        } else {
            $this->assertSame(self::CIPHERTEXT, $result->blob);
        }
    }

    public static function provideChecksumErrors(): iterable
    {
        yield 'encrypt plaintext' => [false, 'plaintext_crc32c', false];
        yield 'encrypt AAD' => [false, 'additional_authenticated_data_crc32c', false];
        yield 'decrypt ciphertext' => [true, 'ciphertext_crc32c', false];
        yield 'decrypt AAD' => [true, 'additional_authenticated_data_crc32c', false];
        yield 'persistent encryption failure' => [false, 'plaintext_crc32c', true];
        yield 'persistent decryption failure' => [true, 'ciphertext_crc32c', true];
    }

    #[DataProvider('provideChecksumErrors')]
    public function testChecksumErrorsFromTheServerAreRetriedWithoutExposingTheirDetails(bool $decrypt, string $field, bool $persistent)
    {
        $requests = [];
        $client = new MockHttpClient(static function ($method, $url, array $options) use (&$requests, $decrypt, $field, $persistent): MockResponse {
            $requests[] = $options['body'];
            if ($persistent || 1 === \count($requests)) {
                return new MockResponse(json_encode(['error' => ['status' => 'INVALID_ARGUMENT', 'message' => 'The checksum in field '.$field.' did not match the data in the request: SENSITIVE-CHECKSUM.']]), ['http_code' => 400]);
            }

            return new MockResponse(json_encode($decrypt ? ['plaintext' => self::CIPHERTEXT, 'plaintextCrc32c' => '3808858755'] : self::encryptedResponse()));
        }, self::BASE);
        $kms = $this->kms($client);

        try {
            $result = $decrypt ? $kms->decrypt(new Ciphertext(self::CIPHERTEXT, self::KEY), 'context') : $kms->encrypt(self::KEY, 'hello', 'context')->blob;
            $this->assertFalse($persistent);
            $this->assertSame($decrypt ? '123456789' : self::CIPHERTEXT, $result);
        } catch (RuntimeException $e) {
            $this->assertTrue($persistent);
            $this->assertNotInstanceOf(DecryptionFailedException::class, $e);
            $this->assertStringContainsString('integrity verification failed', $e->getMessage());
            $this->assertStringNotContainsString('SENSITIVE-CHECKSUM', (string) $e);
            $this->assertNull($e->getPrevious());
        }
        $this->assertCount($persistent ? 3 : 2, $requests);
        $this->assertCount(1, array_unique($requests));
    }

    private static function encryptedResponse(array $replace = []): array
    {
        return array_replace([
            'name' => self::KEY.'/cryptoKeyVersions/1',
            'ciphertext' => self::CIPHERTEXT,
            'ciphertextCrc32c' => '3808858755',
            'verifiedPlaintextCrc32c' => true,
            'verifiedAdditionalAuthenticatedDataCrc32c' => true,
        ], $replace);
    }

    private function kms(MockHttpClient $client): GoogleCloudKms
    {
        $tokens = $this->createStub(TokenProviderInterface::class);
        $tokens->method('getToken')->willReturn('token');

        return new GoogleCloudKms($client, $tokens);
    }
}
