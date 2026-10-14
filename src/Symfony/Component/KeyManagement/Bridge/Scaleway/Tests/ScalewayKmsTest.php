<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\Scaleway\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\KeyManagement\Bridge\Scaleway\ScalewayKms;
use Symfony\Component\KeyManagement\Ciphertext;
use Symfony\Component\KeyManagement\Exception\DecryptionFailedException;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;
use Symfony\Component\KeyManagement\Exception\KeyNotFoundException;
use Symfony\Component\KeyManagement\Exception\RuntimeException;
use Symfony\Component\KeyManagement\Exception\UnsupportedOperationException;

class ScalewayKmsTest extends TestCase
{
    private const string BASE_URI = 'https://api.scaleway.com/key-manager/v1alpha1/regions/fr-par/';
    private const string KEY_ID = '11111111-2222-3333-4444-555555555555';

    public function testEncryptSendsBase64PlaintextAndReturnsTheScalewayBlob()
    {
        $captured = null;
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured = [$method, $url, json_decode($options['body'], true), $options['headers']];

            return new MockResponse(json_encode(['key_id' => self::KEY_ID, 'ciphertext' => 'c2NhbGV3YXk=']));
        }, self::BASE_URI);

        $ciphertext = (new ScalewayKms($client, 'scw-secret'))->encrypt(self::KEY_ID, 'hello');

        $this->assertSame(self::KEY_ID, $ciphertext->keyId);
        $this->assertSame('c2NhbGV3YXk=', $ciphertext->blob);

        [$method, $url, $body, $headers] = $captured;
        $this->assertSame('POST', $method);
        $this->assertSame(self::BASE_URI.'keys/'.self::KEY_ID.'/encrypt', $url);
        $this->assertSame(['plaintext' => base64_encode('hello')], $body);
        $this->assertContains('X-Auth-Token: scw-secret', $headers);
    }

    public function testDecryptSendsTheBlobAndDecodesThePlaintext()
    {
        $captured = null;
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured = [$method, $url, json_decode($options['body'], true)];

            return new MockResponse(json_encode(['key_id' => self::KEY_ID, 'plaintext' => base64_encode('hello'), 'ciphertext' => null]));
        }, self::BASE_URI);

        $plaintext = (new ScalewayKms($client, 'scw-secret'))->decrypt(new Ciphertext('c2NhbGV3YXk=', self::KEY_ID));

        $this->assertSame('hello', $plaintext);
        $this->assertSame(['POST', self::BASE_URI.'keys/'.self::KEY_ID.'/decrypt', ['ciphertext' => 'c2NhbGV3YXk=']], $captured);
    }

    public function testAadIsForwardedAsBase64AssociatedDataOnEncryptAndDecrypt()
    {
        $bodies = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$bodies): MockResponse {
            $bodies[] = json_decode($options['body'], true);

            return new MockResponse(json_encode(str_ends_with($url, '/encrypt')
                ? ['key_id' => self::KEY_ID, 'ciphertext' => 'c2NhbGV3YXk=']
                : ['key_id' => self::KEY_ID, 'plaintext' => base64_encode('hello')]));
        }, self::BASE_URI);

        $kms = new ScalewayKms($client, 'scw-secret');
        $kms->decrypt($kms->encrypt(self::KEY_ID, 'hello', 'opaque-aad'), 'opaque-aad');

        $this->assertSame(base64_encode('opaque-aad'), $bodies[0]['associated_data']);
        $this->assertSame(base64_encode('opaque-aad'), $bodies[1]['associated_data']);
    }

    public function testDeterministicEncryptionIsRefusedWithoutCallingScaleway()
    {
        $client = new MockHttpClient([], self::BASE_URI);

        $this->expectException(UnsupportedOperationException::class);
        (new ScalewayKms($client, 'scw-secret'))->encrypt(self::KEY_ID, 'hello', '', true);
    }

    public function testHttp404OnEncryptSurfacesAsKeyNotFound()
    {
        $client = new MockHttpClient(new MockResponse(json_encode(['type' => 'not_found', 'message' => 'resource is not found']), ['http_code' => 404]), self::BASE_URI);

        $this->expectException(KeyNotFoundException::class);
        (new ScalewayKms($client, 'scw-secret'))->encrypt(self::KEY_ID, 'hello');
    }

    public static function provideMaskedDecryptStatuses(): iterable
    {
        yield 'tampered ciphertext' => [400];
        yield 'unknown key' => [404];
    }

    #[DataProvider('provideMaskedDecryptStatuses')]
    public function testDecryptMasksClientErrorsAsDecryptionFailures(int $status)
    {
        $client = new MockHttpClient(new MockResponse(json_encode(['type' => 'invalid_arguments', 'message' => 'invalid argument(s)']), ['http_code' => $status]), self::BASE_URI);

        $this->expectException(DecryptionFailedException::class);
        (new ScalewayKms($client, 'scw-secret'))->decrypt(new Ciphertext('dGFtcGVyZWQ=', self::KEY_ID));
    }

    public static function provideUnmaskedErrors(): iterable
    {
        yield 'unauthorized' => [401, 'authentication is denied'];
        yield 'forbidden' => [403, 'insufficient permissions'];
        yield 'disabled key' => [412, 'precondition is not respected'];
        yield 'rate limited' => [429, 'quota exceeded'];
        yield 'server error' => [500, 'internal error'];
    }

    #[DataProvider('provideUnmaskedErrors')]
    public function testDecryptDoesNotMaskAuthQuotaOrServerErrors(int $status, string $message)
    {
        $client = new MockHttpClient(new MockResponse(json_encode(['type' => 'error', 'message' => $message]), ['http_code' => $status]), self::BASE_URI);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(\sprintf('Scaleway Key Manager request failed (HTTP %d): "%s".', $status, $message));
        (new ScalewayKms($client, 'scw-secret'))->decrypt(new Ciphertext('c2NhbGV3YXk=', self::KEY_ID));
    }

    public function testHelpMessageIsAppendedToTheErrorMessage()
    {
        $client = new MockHttpClient(new MockResponse(json_encode([
            'help_message' => 'asymmetric encryption does not support associated data',
            'message' => 'precondition is not respected',
            'precondition' => 'resource_not_usable',
            'type' => 'precondition_failed',
        ]), ['http_code' => 412]), self::BASE_URI);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Scaleway Key Manager request failed (HTTP 412): "precondition is not respected: asymmetric encryption does not support associated data".');
        (new ScalewayKms($client, 'scw-secret'))->encrypt(self::KEY_ID, 'hello', 'opaque-aad');
    }

    public function testNonJsonErrorBodyStillReportsTheStatus()
    {
        $client = new MockHttpClient(new MockResponse('<html>Bad Gateway</html>', ['http_code' => 502]), self::BASE_URI);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HTTP 502');
        (new ScalewayKms($client, 'scw-secret'))->encrypt(self::KEY_ID, 'hello');
    }

    public function testTransportErrorIsWrapped()
    {
        $client = new MockHttpClient(new MockResponse('', ['error' => 'Connection refused']), self::BASE_URI);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to reach Scaleway Key Manager.');
        (new ScalewayKms($client, 'scw-secret'))->encrypt(self::KEY_ID, 'hello');
    }

    public function testRedirectsAreNeverFollowedSoTheSecretKeyStaysOnTheApiHost()
    {
        $captured = null;
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured = $options['max_redirects'];

            return new MockResponse(json_encode(['key_id' => self::KEY_ID, 'ciphertext' => 'c2NhbGV3YXk=']));
        }, self::BASE_URI);

        (new ScalewayKms($client, 'scw-secret'))->encrypt(self::KEY_ID, 'hello');

        $this->assertSame(0, $captured);
    }

    public function testEncryptResponseWithoutCiphertextIsRejected()
    {
        $client = new MockHttpClient(new MockResponse(json_encode(['key_id' => self::KEY_ID])), self::BASE_URI);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('missing "ciphertext"');
        (new ScalewayKms($client, 'scw-secret'))->encrypt(self::KEY_ID, 'hello');
    }

    public function testDecryptResponseWithoutPlaintextIsRejected()
    {
        $client = new MockHttpClient(new MockResponse(json_encode(['key_id' => self::KEY_ID])), self::BASE_URI);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('missing "plaintext"');
        (new ScalewayKms($client, 'scw-secret'))->decrypt(new Ciphertext('c2NhbGV3YXk=', self::KEY_ID));
    }

    public function testDecryptResponseWithNonBase64PlaintextIsRejected()
    {
        $client = new MockHttpClient(new MockResponse(json_encode(['key_id' => self::KEY_ID, 'plaintext' => '%%%'])), self::BASE_URI);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('non-base64 plaintext');
        (new ScalewayKms($client, 'scw-secret'))->decrypt(new Ciphertext('c2NhbGV3YXk=', self::KEY_ID));
    }

    public static function provideDataKeys(): iterable
    {
        yield '32 bytes' => [32, ''];
        yield '32 bytes with AAD' => [32, 'opaque-aad'];
        yield '16 bytes' => [16, ''];
        yield '64 bytes' => [64, ''];
        yield '64 bytes with AAD' => [64, 'opaque-aad'];
    }

    #[DataProvider('provideDataKeys')]
    public function testGenerateDataKeyWrapsLocallyGeneratedBytesThroughEncrypt(int $length, string $aad)
    {
        $requests = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $requests[] = [$url, json_decode($options['body'], true)];

            return new MockResponse(json_encode(['key_id' => self::KEY_ID, 'ciphertext' => 'd3JhcHBlZA==']));
        }, self::BASE_URI);

        $dataKey = (new ScalewayKms($client, 'scw-secret'))->generateDataKey(self::KEY_ID, $length, $aad);

        $this->assertCount(1, $requests);
        [$url, $body] = $requests[0];
        $this->assertSame(self::BASE_URI.'keys/'.self::KEY_ID.'/encrypt', $url);
        $this->assertSame('' === $aad ? null : base64_encode($aad), $body['associated_data'] ?? null);
        $this->assertSame($length, \strlen(base64_decode($body['plaintext'])));
        $this->assertSame(base64_decode($body['plaintext']), $dataKey->use(static fn (string $p): string => $p));
        $this->assertSame('d3JhcHBlZA==', $dataKey->wrapped->blob);
        $this->assertSame(self::KEY_ID, $dataKey->wrapped->keyId);
    }

    public function testGenerateDataKeyRefusesKeysShorterThan16Bytes()
    {
        $client = new MockHttpClient([], self::BASE_URI);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Data key length must be at least 16 bytes, 8 given.');
        (new ScalewayKms($client, 'scw-secret'))->generateDataKey(self::KEY_ID, 8);
    }

    public function testUnwrapDataKeyDecryptsTheWrappedKeyWithItsAad()
    {
        $captured = null;
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured = [$url, json_decode($options['body'], true)];

            return new MockResponse(json_encode(['key_id' => self::KEY_ID, 'plaintext' => base64_encode(str_repeat("\xCC", 32))]));
        }, self::BASE_URI);

        $wrapped = new Ciphertext('d3JhcHBlZA==', self::KEY_ID);
        $dataKey = (new ScalewayKms($client, 'scw-secret'))->unwrapDataKey($wrapped, 'opaque-aad');

        $this->assertSame([self::BASE_URI.'keys/'.self::KEY_ID.'/decrypt', ['ciphertext' => 'd3JhcHBlZA==', 'associated_data' => base64_encode('opaque-aad')]], $captured);
        $this->assertSame($wrapped, $dataKey->wrapped);
        $this->assertSame(str_repeat("\xCC", 32), $dataKey->use(static fn (string $p): string => $p));
    }
}
