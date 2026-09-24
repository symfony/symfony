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
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\KeyManagement\Bridge\GoogleCloudKms\GoogleCloudKms;
use Symfony\Component\KeyManagement\Bridge\GoogleCloudKms\ServiceAccountTokenProvider;
use Symfony\Component\KeyManagement\Bridge\GoogleCloudKms\TokenProviderInterface;
use Symfony\Component\KeyManagement\Ciphertext;
use Symfony\Component\KeyManagement\Exception\DecryptionFailedException;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;
use Symfony\Component\KeyManagement\Exception\KeyNotFoundException;
use Symfony\Component\KeyManagement\Exception\RuntimeException;
use Symfony\Component\KeyManagement\Exception\UnsupportedOperationException;

class GoogleCloudKmsTest extends TestCase
{
    private const string BASE = 'https://cloudkms.googleapis.com/v1/';
    private const string KEY = 'projects/my-proj/locations/global/keyRings/app/cryptoKeys/master';

    public function testEncryptSendsBase64PlaintextAndReturnsCloudKmsCiphertext()
    {
        $captured = null;
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured = [$method, $url, json_decode($options['body'], true), $options['headers']];

            return new MockResponse(json_encode(['name' => self::KEY.'/cryptoKeyVersions/1', 'ciphertext' => 'CipherFromGcp']));
        }, self::BASE);

        $kms = new GoogleCloudKms($client, $this->staticToken('TOKEN'));
        $ciphertext = $kms->encrypt(self::KEY, 'hello');

        $this->assertSame(self::KEY, $ciphertext->keyId);
        $this->assertSame('CipherFromGcp', $ciphertext->blob);

        [$method, $url, $body, $headers] = $captured;
        $this->assertSame('POST', $method);
        $this->assertSame(self::BASE.self::KEY.':encrypt', $url);
        $this->assertSame(base64_encode('hello'), $body['plaintext']);
        $this->assertContains('Authorization: Bearer TOKEN', $headers);
    }

    public function testRejectedCachedTokenIsRefreshedAndRetried()
    {
        $tokenRequests = 0;
        $kmsHeaders = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$tokenRequests, &$kmsHeaders): MockResponse {
            if ('https://oauth2.googleapis.com/token' === $url) {
                return new MockResponse(json_encode(['access_token' => 'T'.++$tokenRequests, 'expires_in' => 3600]));
            }

            $kmsHeaders[] = $options['headers'];

            return 2 === \count($kmsHeaders)
                ? new MockResponse(json_encode(['error' => ['message' => 'expired token']]), ['http_code' => 401])
                : new MockResponse(json_encode(['ciphertext' => 'ciphertext']));
        }, self::BASE);

        $provider = new ServiceAccountTokenProvider($client, 'sa@my-proj.iam.gserviceaccount.com', file_get_contents(__DIR__.'/Fixtures/private_key.pem'));
        $kms = new GoogleCloudKms($client, $provider);
        $kms->encrypt(self::KEY, 'first');
        $kms->encrypt(self::KEY, 'second');
        $kms->encrypt(self::KEY, 'third');

        $this->assertSame(2, $tokenRequests);
        $this->assertCount(4, $kmsHeaders);
        $this->assertContains('Authorization: Bearer T1', $kmsHeaders[0]);
        $this->assertContains('Authorization: Bearer T1', $kmsHeaders[1]);
        $this->assertContains('Authorization: Bearer T2', $kmsHeaders[2]);
        $this->assertContains('Authorization: Bearer T2', $kmsHeaders[3]);
    }

    public function testInitialTokenTransportFailureIsWrapped()
    {
        $transportError = new TransportException('Token endpoint unavailable.');
        $provider = new class($transportError) implements TokenProviderInterface {
            public function __construct(private TransportException $error)
            {
            }

            public function getToken(): string
            {
                throw $this->error;
            }

            public function invalidateToken(#[\SensitiveParameter] string $token): void
            {
            }
        };

        try {
            (new GoogleCloudKms($this->clientThatMustNotBeCalled(), $provider))->encrypt(self::KEY, 'hello');
            $this->fail('The transport error should be wrapped.');
        } catch (RuntimeException $e) {
            $this->assertSame('Failed to reach Google Cloud KMS.', $e->getMessage());
            $this->assertSame($transportError, $e->getPrevious());
        }
    }

    public function testSecondUnauthorizedResponseInvalidatesTheReplacementToken()
    {
        $tokenRequests = 0;
        $kmsRequests = [];
        $client = $this->mockClient([
            new MockResponse(json_encode(['error' => ['message' => 'expired']]), ['http_code' => 401]),
            new MockResponse(json_encode(['error' => ['message' => 'replacement rejected']]), ['http_code' => 401]),
            new MockResponse(json_encode(['ciphertext' => 'ciphertext'])),
        ], ['T1', 'T2', 'T3'], $tokenRequests, $kmsRequests);
        $kms = new GoogleCloudKms($client, new ServiceAccountTokenProvider($client, 'sa@my-proj.iam.gserviceaccount.com', file_get_contents(__DIR__.'/Fixtures/private_key.pem')));

        try {
            $kms->encrypt(self::KEY, 'first');
            $this->fail('The second 401 should be reported.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('(HTTP 401): "replacement rejected"', $e->getMessage());
        }

        $this->assertCount(2, $kmsRequests);
        $this->assertSame(2, $tokenRequests);

        $kms->encrypt(self::KEY, 'second');
        $this->assertSame(3, $tokenRequests);
        $this->assertContains('Authorization: Bearer T3', $kmsRequests[2][3]);
    }

    public function testForbiddenResponseAfterRetryKeepsTheReplacementToken()
    {
        $tokenRequests = 0;
        $kmsRequests = [];
        $client = $this->mockClient([
            new MockResponse(json_encode(['error' => ['message' => 'expired']]), ['http_code' => 401]),
            new MockResponse(json_encode(['error' => ['message' => 'permission denied']]), ['http_code' => 403]),
            new MockResponse(json_encode(['error' => ['message' => 'permission denied']]), ['http_code' => 403]),
        ], ['T1', 'T2'], $tokenRequests, $kmsRequests);
        $kms = new GoogleCloudKms($client, new ServiceAccountTokenProvider($client, 'sa@my-proj.iam.gserviceaccount.com', file_get_contents(__DIR__.'/Fixtures/private_key.pem')));

        try {
            $kms->encrypt(self::KEY, 'first');
            $this->fail('The 403 should be reported.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('(HTTP 403): "permission denied"', $e->getMessage());
        }

        $this->assertSame(2, $tokenRequests);
        $this->assertCount(2, $kmsRequests);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('(HTTP 403): "permission denied"');
        try {
            $kms->encrypt(self::KEY, 'second');
        } finally {
            $this->assertSame(2, $tokenRequests);
            $this->assertCount(3, $kmsRequests);
            $this->assertContains('Authorization: Bearer T2', $kmsRequests[2][3]);
        }
    }

    public static function provideNonRetryableStatuses(): iterable
    {
        yield 'forbidden' => [403];
        yield 'throttled' => [429];
        yield 'server error' => [500];
    }

    #[DataProvider('provideNonRetryableStatuses')]
    public function testOtherHttpErrorsKeepTheCachedToken(int $status)
    {
        $tokenRequests = 0;
        $kmsRequests = [];
        $client = $this->mockClient([
            new MockResponse(json_encode(['error' => ['message' => 'request denied']]), ['http_code' => $status]),
            new MockResponse(json_encode(['ciphertext' => 'ciphertext'])),
        ], ['T1'], $tokenRequests, $kmsRequests);
        $kms = new GoogleCloudKms($client, new ServiceAccountTokenProvider($client, 'sa@my-proj.iam.gserviceaccount.com', file_get_contents(__DIR__.'/Fixtures/private_key.pem')));

        try {
            $kms->encrypt(self::KEY, 'first');
            $this->fail('The KMS error should be reported.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString(\sprintf('(HTTP %d): "request denied"', $status), $e->getMessage());
        }

        $this->assertSame(1, $tokenRequests);
        $this->assertCount(1, $kmsRequests);

        $kms->encrypt(self::KEY, 'second');
        $this->assertSame(1, $tokenRequests);
        $this->assertContains('Authorization: Bearer T1', $kmsRequests[1][3]);
    }

    public function testReacquiringTheSameRejectedTokenLeavesItUncached()
    {
        $tokenRequests = 0;
        $kmsRequests = [];
        $client = $this->mockClient([
            new MockResponse(json_encode(['error' => ['message' => 'expired']]), ['http_code' => 401]),
            new MockResponse(json_encode(['ciphertext' => 'ciphertext'])),
        ], ['T1', 'T1', 'T2'], $tokenRequests, $kmsRequests);
        $kms = new GoogleCloudKms($client, new ServiceAccountTokenProvider($client, 'sa@my-proj.iam.gserviceaccount.com', file_get_contents(__DIR__.'/Fixtures/private_key.pem')));

        try {
            $kms->encrypt(self::KEY, 'first');
            $this->fail('The 401 should be reported.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('(HTTP 401): "expired"', $e->getMessage());
        }

        $this->assertCount(1, $kmsRequests);
        $this->assertSame(2, $tokenRequests);

        $kms->encrypt(self::KEY, 'second');
        $this->assertSame(3, $tokenRequests);
        $this->assertContains('Authorization: Bearer T2', $kmsRequests[1][3]);
    }

    public function testGenerateDataKeyRetryReusesTheSamePlaintext()
    {
        $tokenRequests = 0;
        $kmsRequests = [];
        $canceledResponses = 0;
        $client = $this->mockClient([
            new MockResponse(json_encode(['error' => ['message' => 'expired']]), ['http_code' => 401]),
            new MockResponse(json_encode(['ciphertext' => 'wrapped'])),
        ], ['T1', 'T2'], $tokenRequests, $kmsRequests)->withOptions(['on_progress' => static function (int $downloaded, int $downloadSize, array $info) use (&$canceledResponses): void {
            if ($info['canceled'] ?? false) {
                ++$canceledResponses;
            }
        }]);
        $kms = new GoogleCloudKms($client, new ServiceAccountTokenProvider($client, 'sa@my-proj.iam.gserviceaccount.com', file_get_contents(__DIR__.'/Fixtures/private_key.pem')));

        $dataKey = $kms->generateDataKey(self::KEY);

        $this->assertSame(2, $tokenRequests);
        $this->assertCount(2, $kmsRequests);
        $this->assertSame('POST', $kmsRequests[0][0]);
        $this->assertSame($kmsRequests[0][0], $kmsRequests[1][0]);
        $this->assertSame($kmsRequests[0][1], $kmsRequests[1][1]);
        $this->assertSame($kmsRequests[0][2], $kmsRequests[1][2]);
        $this->assertContains('Authorization: Bearer T1', $kmsRequests[0][3]);
        $this->assertContains('Authorization: Bearer T2', $kmsRequests[1][3]);
        $this->assertSame(1, $canceledResponses);
        $this->assertSame(base64_encode($dataKey->use(static fn (string $key): string => $key)), json_decode($kmsRequests[0][2], true)['plaintext']);
        $this->assertSame(self::BASE.self::KEY.':encrypt', $kmsRequests[1][1]);
    }

    public function testTokenAcquisitionFailureAfterUnauthorizedResponseIsReported()
    {
        $tokenRequests = 0;
        $kmsRequests = [];
        $client = $this->mockClient([
            new MockResponse(json_encode(['error' => ['message' => 'expired']]), ['http_code' => 401]),
        ], ['T1', new MockResponse(json_encode(['error' => 'invalid_client']), ['http_code' => 503])], $tokenRequests, $kmsRequests);
        $kms = new GoogleCloudKms($client, new ServiceAccountTokenProvider($client, 'sa@my-proj.iam.gserviceaccount.com', file_get_contents(__DIR__.'/Fixtures/private_key.pem')));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Google token request failed (HTTP 503)');
        try {
            $kms->encrypt(self::KEY, 'hello');
        } finally {
            $this->assertSame(2, $tokenRequests);
            $this->assertCount(1, $kmsRequests);
        }
    }

    public function testRefreshFailurePropagatesTheOriginalExceptionAndCancelsTheResponse()
    {
        $error = new RuntimeException('Refresh failed.');
        $provider = new class($error) implements TokenProviderInterface {
            private bool $issued = false;

            public function __construct(private RuntimeException $error)
            {
            }

            public function getToken(): string
            {
                if (!$this->issued) {
                    $this->issued = true;

                    return 'T1';
                }

                throw $this->error;
            }

            public function invalidateToken(#[\SensitiveParameter] string $token): void
            {
            }
        };

        $tokenRequests = 0;
        $kmsRequests = [];
        $canceledResponses = 0;
        $client = $this->mockClient([
            new MockResponse(json_encode(['error' => ['message' => 'expired']]), ['http_code' => 401]),
        ], [], $tokenRequests, $kmsRequests)->withOptions(['on_progress' => static function (int $downloaded, int $downloadSize, array $info) use (&$canceledResponses): void {
            if ($info['canceled'] ?? false) {
                ++$canceledResponses;
            }
        }]);

        try {
            (new GoogleCloudKms($client, $provider))->encrypt(self::KEY, 'hello');
            $this->fail('The refresh error should be reported.');
        } catch (RuntimeException $caught) {
            $this->assertSame($error, $caught);
        }

        $this->assertCount(1, $kmsRequests);
        $this->assertSame(0, $tokenRequests);
        $this->assertSame(1, $canceledResponses);
    }

    public function testReplacementTokenTransportFailureIsWrappedAndCancelsTheResponse()
    {
        $transportError = new TransportException('Token endpoint unavailable.');
        $provider = new class($transportError) implements TokenProviderInterface {
            private bool $issued = false;

            public function __construct(private TransportException $error)
            {
            }

            public function getToken(): string
            {
                if (!$this->issued) {
                    $this->issued = true;

                    return 'T1';
                }

                throw $this->error;
            }

            public function invalidateToken(#[\SensitiveParameter] string $token): void
            {
            }
        };

        $tokenRequests = 0;
        $kmsRequests = [];
        $canceledResponses = 0;
        $client = $this->mockClient([
            new MockResponse(json_encode(['error' => ['message' => 'expired']]), ['http_code' => 401]),
        ], [], $tokenRequests, $kmsRequests)->withOptions(['on_progress' => static function (int $downloaded, int $downloadSize, array $info) use (&$canceledResponses): void {
            if ($info['canceled'] ?? false) {
                ++$canceledResponses;
            }
        }]);

        try {
            (new GoogleCloudKms($client, $provider))->encrypt(self::KEY, 'hello');
            $this->fail('The transport error should be wrapped.');
        } catch (RuntimeException $e) {
            $this->assertSame('Failed to reach Google Cloud KMS.', $e->getMessage());
            $this->assertSame($transportError, $e->getPrevious());
        }

        $this->assertCount(1, $kmsRequests);
        $this->assertSame(1, $canceledResponses);
    }

    public function testDecryptAfterTokenRefreshStillMasksClientErrors()
    {
        $tokenRequests = 0;
        $kmsRequests = [];
        $client = $this->mockClient([
            new MockResponse(json_encode(['error' => ['message' => 'expired']]), ['http_code' => 401]),
            new MockResponse(json_encode(['error' => ['message' => 'invalid ciphertext']]), ['http_code' => 404]),
        ], ['T1', 'T2'], $tokenRequests, $kmsRequests);
        $kms = new GoogleCloudKms($client, new ServiceAccountTokenProvider($client, 'sa@my-proj.iam.gserviceaccount.com', file_get_contents(__DIR__.'/Fixtures/private_key.pem')));

        $this->expectException(DecryptionFailedException::class);
        try {
            $kms->decrypt(new Ciphertext('CipherFromGcp', self::KEY));
        } finally {
            $this->assertSame(2, $tokenRequests);
            $this->assertCount(2, $kmsRequests);
            $this->assertContains('Authorization: Bearer T1', $kmsRequests[0][3]);
            $this->assertContains('Authorization: Bearer T2', $kmsRequests[1][3]);
        }
    }

    public function testDecryptAfterRepeatedUnauthorizedResponsesReportsAuthenticationFailure()
    {
        $tokenRequests = 0;
        $kmsRequests = [];
        $client = $this->mockClient([
            new MockResponse(json_encode(['error' => ['message' => 'expired']]), ['http_code' => 401]),
            new MockResponse(json_encode(['error' => ['message' => 'replacement rejected']]), ['http_code' => 401]),
        ], ['T1', 'T2'], $tokenRequests, $kmsRequests);
        $kms = new GoogleCloudKms($client, new ServiceAccountTokenProvider($client, 'sa@my-proj.iam.gserviceaccount.com', file_get_contents(__DIR__.'/Fixtures/private_key.pem')));

        try {
            $kms->decrypt(new Ciphertext('CipherFromGcp', self::KEY));
            $this->fail('The second 401 should be reported.');
        } catch (RuntimeException $e) {
            $this->assertNotInstanceOf(DecryptionFailedException::class, $e);
            $this->assertStringContainsString('(HTTP 401): "replacement rejected"', $e->getMessage());
        }

        $this->assertSame(2, $tokenRequests);
        $this->assertCount(2, $kmsRequests);
    }

    public function testEncryptForwardsAadAsBase64Bytes()
    {
        $captured = null;
        $client = new MockHttpClient(static function ($method, $url, array $options) use (&$captured): MockResponse {
            $captured = json_decode($options['body'], true);

            return new MockResponse(json_encode(['ciphertext' => 'ct']));
        }, self::BASE);

        (new GoogleCloudKms($client, $this->staticToken('T')))->encrypt(self::KEY, 'hello', 'tenant=acme');

        $this->assertSame(base64_encode('tenant=acme'), $captured['additionalAuthenticatedData']);
    }

    public function testEncryptRejectsDeterministic()
    {
        $kms = new GoogleCloudKms(new MockHttpClient([], self::BASE), $this->staticToken('T'));

        $this->expectException(UnsupportedOperationException::class);
        $kms->encrypt(self::KEY, 'hello', deterministic: true);
    }

    public function testDecryptDecodesPlaintext()
    {
        $client = new MockHttpClient(
            new MockResponse(json_encode(['plaintext' => base64_encode('hello')])),
            self::BASE,
        );

        $plaintext = (new GoogleCloudKms($client, $this->staticToken('T')))->decrypt(new Ciphertext('CipherFromGcp', self::KEY));

        $this->assertSame('hello', $plaintext);
    }

    public function testDecryptStripsCryptoKeyVersionFromTheKeyId()
    {
        $captured = null;
        $client = new MockHttpClient(static function ($method, $url) use (&$captured): MockResponse {
            $captured = $url;

            return new MockResponse(json_encode(['plaintext' => base64_encode('hello')]));
        }, self::BASE);

        (new GoogleCloudKms($client, $this->staticToken('T')))->decrypt(new Ciphertext('CipherFromGcp', self::KEY.'/cryptoKeyVersions/3'));

        $this->assertSame(self::BASE.self::KEY.':decrypt', $captured);
    }

    public function testDecryptOnHttp400IsADecryptionFailure()
    {
        $client = new MockHttpClient(
            new MockResponse(json_encode(['error' => ['code' => 400, 'status' => 'INVALID_ARGUMENT', 'message' => 'invalid']]), ['http_code' => 400]),
            self::BASE,
        );

        $this->expectException(DecryptionFailedException::class);
        (new GoogleCloudKms($client, $this->staticToken('T')))->decrypt(new Ciphertext('tampered', self::KEY));
    }

    public function testDecryptOnHttp404IsAlsoADecryptionFailure()
    {
        $client = new MockHttpClient(
            new MockResponse(json_encode(['error' => ['status' => 'NOT_FOUND', 'message' => 'no such key']]), ['http_code' => 404]),
            self::BASE,
        );

        $this->expectException(DecryptionFailedException::class);
        (new GoogleCloudKms($client, $this->staticToken('T')))->decrypt(new Ciphertext('whatever', self::KEY));
    }

    public function testEncryptOnHttp404IsKeyNotFound()
    {
        $client = new MockHttpClient(
            new MockResponse(json_encode(['error' => ['status' => 'NOT_FOUND', 'message' => 'no such key']]), ['http_code' => 404]),
            self::BASE,
        );

        $this->expectException(KeyNotFoundException::class);
        (new GoogleCloudKms($client, $this->staticToken('T')))->encrypt(self::KEY, 'hello');
    }

    public function testServerErrorBubblesAsRuntimeException()
    {
        $client = new MockHttpClient(
            new MockResponse(json_encode(['error' => ['status' => 'INTERNAL', 'message' => 'kaboom']]), ['http_code' => 500]),
            self::BASE,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HTTP 500');
        (new GoogleCloudKms($client, $this->staticToken('T')))->encrypt(self::KEY, 'hello');
    }

    public function testNonJsonErrorBodySurfacesAsRuntimeExceptionWithoutTheBody()
    {
        $client = new MockHttpClient(
            new MockResponse('<html>load balancer error page</html>', ['http_code' => 503]),
            self::BASE,
        );

        try {
            (new GoogleCloudKms($client, $this->staticToken('T')))->encrypt(self::KEY, 'hello');
            $this->fail('A RuntimeException should have been thrown.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('HTTP 503', $e->getMessage());
            $this->assertStringNotContainsString('<html>', $e->getMessage());
        }
    }

    public function testRedirectResponseSurfacesAsRuntimeException()
    {
        $client = new MockHttpClient(
            new MockResponse('<html>moved</html>', ['http_code' => 301]),
            self::BASE,
        );

        try {
            (new GoogleCloudKms($client, $this->staticToken('T')))->encrypt(self::KEY, 'hello');
            $this->fail('A RuntimeException should have been thrown.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('HTTP 301', $e->getMessage());
            $this->assertStringNotContainsString('<html>', $e->getMessage());
        }
    }

    public function testGenerateDataKeyDrawsLocallyAndCallsEncrypt()
    {
        $captured = null;
        $client = new MockHttpClient(static function ($method, $url, array $options) use (&$captured): MockResponse {
            $captured = [$url, json_decode($options['body'], true)];

            return new MockResponse(json_encode(['ciphertext' => 'WrappedDek']));
        }, self::BASE);

        $dataKey = (new GoogleCloudKms($client, $this->staticToken('T')))->generateDataKey(self::KEY, 32);

        [$url, $body] = $captured;
        $this->assertSame(self::BASE.self::KEY.':encrypt', $url);
        $this->assertSame(self::KEY, $dataKey->wrapped->keyId);
        $this->assertSame('WrappedDek', $dataKey->wrapped->blob);
        $this->assertSame(32, \strlen(base64_decode($body['plaintext'], true)));
    }

    public function testGenerateDataKeyRejectsTooShortLengths()
    {
        $kms = new GoogleCloudKms(new MockHttpClient([], self::BASE), $this->staticToken('T'));

        $this->expectException(InvalidArgumentException::class);
        $kms->generateDataKey(self::KEY, 8);
    }

    public function testUnwrapDataKeyDecodesPlaintext()
    {
        $client = new MockHttpClient(
            new MockResponse(json_encode(['plaintext' => base64_encode(str_repeat("\xAB", 32))])),
            self::BASE,
        );

        $dataKey = (new GoogleCloudKms($client, $this->staticToken('T')))->unwrapDataKey(new Ciphertext('WrappedDek', self::KEY));

        $this->assertSame(str_repeat("\xAB", 32), $dataKey->use(static fn (string $p): string => $p));
    }

    public static function provideMalformedKeyIds(): iterable
    {
        yield 'absolute URL' => ['https://attacker.example/steal'];
        yield 'dot segments' => ['../../other'];
        yield 'query and fragment' => [self::KEY.'?x=1#f'];
        yield 'bare key name' => ['master'];
        yield 'empty' => [''];
    }

    #[DataProvider('provideMalformedKeyIds')]
    public function testEncryptRejectsAMalformedKeyIdWithoutSendingARequest(string $keyId)
    {
        $kms = new GoogleCloudKms($this->clientThatMustNotBeCalled(), $this->staticToken('T'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('projects/<project>/locations/<location>/keyRings/<ring>/cryptoKeys/<key>');
        $kms->encrypt($keyId, 'hello');
    }

    #[DataProvider('provideMalformedKeyIds')]
    public function testGenerateDataKeyRejectsAMalformedKeyIdWithoutSendingARequest(string $keyId)
    {
        $kms = new GoogleCloudKms($this->clientThatMustNotBeCalled(), $this->staticToken('T'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('projects/<project>/locations/<location>/keyRings/<ring>/cryptoKeys/<key>');
        $kms->generateDataKey($keyId);
    }

    #[DataProvider('provideMalformedKeyIds')]
    public function testDecryptRejectsAMalformedKeyIdAsADecryptionFailure(string $keyId)
    {
        $kms = new GoogleCloudKms($this->clientThatMustNotBeCalled(), $this->staticToken('T'));

        $this->expectException(DecryptionFailedException::class);
        $kms->decrypt(new Ciphertext('blob', $keyId));
    }

    #[DataProvider('provideMalformedKeyIds')]
    public function testUnwrapDataKeyRejectsAMalformedKeyIdAsADecryptionFailure(string $keyId)
    {
        $kms = new GoogleCloudKms($this->clientThatMustNotBeCalled(), $this->staticToken('T'));

        $this->expectException(DecryptionFailedException::class);
        $kms->unwrapDataKey(new Ciphertext('blob', $keyId));
    }

    public function testEncryptAcceptsAKeyIdPinnedToACryptoKeyVersion()
    {
        $captured = null;
        $client = new MockHttpClient(static function ($method, $url) use (&$captured): MockResponse {
            $captured = $url;

            return new MockResponse(json_encode(['ciphertext' => 'ct']));
        }, self::BASE);

        $ciphertext = (new GoogleCloudKms($client, $this->staticToken('T')))->encrypt(self::KEY.'/cryptoKeyVersions/3', 'hello');

        $this->assertSame(self::BASE.self::KEY.'/cryptoKeyVersions/3:encrypt', $captured);
        $this->assertSame(self::KEY.'/cryptoKeyVersions/3', $ciphertext->keyId);
    }

    public function testTransportErrorWhileReadingTheBodySurfacesAsRuntimeException()
    {
        $client = new MockHttpClient(new MockResponse((static function (): \Generator {
            yield '{"pla';
            yield '';
        })()), self::BASE);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HTTP 200');
        (new GoogleCloudKms($client, $this->staticToken('T')))->decrypt(new Ciphertext('blob', self::KEY));
    }

    public function testTransportErrorWhileReadingAnErrorBodySurfacesAsRuntimeException()
    {
        $client = new MockHttpClient(new MockResponse((static function (): \Generator {
            yield '{"err';
            yield '';
        })(), ['http_code' => 500]), self::BASE);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HTTP 500');
        (new GoogleCloudKms($client, $this->staticToken('T')))->encrypt(self::KEY, 'hello');
    }

    public function testArrayValuedErrorMessageIsReportedAsUnknown()
    {
        $client = new MockHttpClient(
            new MockResponse(json_encode(['error' => ['message' => ['x' => 1]]]), ['http_code' => 429]),
            self::BASE,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('(HTTP 429): "unknown error"');
        (new GoogleCloudKms($client, $this->staticToken('T')))->encrypt(self::KEY, 'hello');
    }

    public function testDecryptOfAnEmptyPlaintextReturnsAnEmptyString()
    {
        // proto3 JSON omits an empty bytes field, so only the checksum is there
        $client = new MockHttpClient(
            new MockResponse(json_encode(['name' => self::KEY.'/cryptoKeyVersions/1', 'plaintextCrc32c' => '0'])),
            self::BASE,
        );

        $this->assertSame('', (new GoogleCloudKms($client, $this->staticToken('T')))->decrypt(new Ciphertext('blob', self::KEY)));
    }

    private function clientThatMustNotBeCalled(): MockHttpClient
    {
        return new MockHttpClient(function (string $method, string $url): MockResponse {
            $this->fail(\sprintf('No request should have been sent, got "%s %s".', $method, $url));
        }, self::BASE);
    }

    private function mockClient(array $kmsResponses, array $issuedTokens, int &$tokenRequests, array &$kmsRequests): MockHttpClient
    {
        return new MockHttpClient(static function (string $method, string $url, array $options) use (&$kmsResponses, &$issuedTokens, &$tokenRequests, &$kmsRequests): MockResponse {
            if ('https://oauth2.googleapis.com/token' === $url) {
                ++$tokenRequests;
                $token = array_shift($issuedTokens);
                if (null === $token) {
                    throw new \LogicException('Unexpected token request.');
                }

                return $token instanceof MockResponse ? $token : new MockResponse(json_encode(['access_token' => $token, 'expires_in' => 3600]));
            }

            $kmsRequests[] = [$method, $url, $options['body'], $options['headers']];

            return array_shift($kmsResponses) ?? throw new \LogicException('Unexpected KMS request.');
        }, self::BASE);
    }

    private function staticToken(string $value): TokenProviderInterface
    {
        return new class($value) implements TokenProviderInterface {
            public function __construct(private readonly string $value)
            {
            }

            public function getToken(): string
            {
                return $this->value;
            }

            public function invalidateToken(#[\SensitiveParameter] string $token): void
            {
            }
        };
    }
}
