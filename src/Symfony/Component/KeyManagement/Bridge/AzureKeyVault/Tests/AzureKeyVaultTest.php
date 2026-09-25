<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\AzureKeyVault\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\KeyManagement\Base64UrlSafe;
use Symfony\Component\KeyManagement\Bridge\AzureKeyVault\AzureKeyVault;
use Symfony\Component\KeyManagement\Bridge\AzureKeyVault\ClientCredentialsTokenProvider;
use Symfony\Component\KeyManagement\Bridge\AzureKeyVault\TokenProviderInterface;
use Symfony\Component\KeyManagement\Ciphertext;
use Symfony\Component\KeyManagement\Exception\DecryptionFailedException;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;
use Symfony\Component\KeyManagement\Exception\KeyNotFoundException;
use Symfony\Component\KeyManagement\Exception\RuntimeException;
use Symfony\Component\KeyManagement\Exception\UnsupportedOperationException;

class AzureKeyVaultTest extends TestCase
{
    private const string VAULT = 'https://my-vault.vault.azure.net/';

    public function testEncryptWithRsaSendsBase64UrlValueAndReturnsAzureBlob()
    {
        $captured = null;
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured = [$method, $url, json_decode($options['body'], true), $options['headers']];

            return new MockResponse(json_encode(['kid' => self::VAULT.'keys/app-key/v1', 'value' => 'CipherFromAzure']));
        }, self::VAULT);

        $kms = new AzureKeyVault($client, $this->staticToken('TOKEN'), self::VAULT);
        $ciphertext = $kms->encrypt('app-key', 'hello');

        $this->assertSame('app-key/v1', $ciphertext->keyId);
        $this->assertSame('CipherFromAzure', $ciphertext->blob);

        [$method, $url, $body, $headers] = $captured;
        $this->assertSame('POST', $method);
        $this->assertSame(self::VAULT.'keys/app-key/encrypt?api-version=7.4', $url);
        $this->assertSame('RSA-OAEP-256', $body['alg']);
        $this->assertSame(Base64UrlSafe::encode('hello'), $body['value']);
        $this->assertContains('Authorization: Bearer TOKEN', $headers);
    }

    public function testVersionlessEncryptKeepsTheResolvedVersionAfterRotation()
    {
        $currentVersion = 'v1';
        $urls = [];
        $client = new MockHttpClient(static function (string $method, string $url) use (&$currentVersion, &$urls): MockResponse {
            $urls[] = $url;

            if (str_contains($url, '/encrypt?')) {
                return new MockResponse(json_encode(['kid' => self::VAULT.'keys/app-key/'.$currentVersion, 'value' => 'ciphertext']));
            }

            return new MockResponse(json_encode(['value' => Base64UrlSafe::encode('hello')]));
        }, self::VAULT);

        $kms = new AzureKeyVault($client, $this->staticToken('T'), self::VAULT);
        $ciphertext = $kms->encrypt('app-key', 'hello');
        $currentVersion = 'v2';

        $this->assertSame('app-key/v1', $ciphertext->keyId);
        $this->assertSame('hello', $kms->decrypt($ciphertext));
        $this->assertSame(self::VAULT.'keys/app-key/v1/decrypt?api-version=7.4', $urls[1]);
    }

    public function testVersionlessDataKeyWrapKeepsTheResolvedVersionAfterRotation()
    {
        $currentVersion = 'v1';
        $urls = [];
        $plaintext = null;
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$currentVersion, &$urls, &$plaintext): MockResponse {
            $urls[] = $url;

            if (str_contains($url, '/wrapkey?')) {
                $plaintext = Base64UrlSafe::decode(json_decode($options['body'], true)['value']);

                return new MockResponse(json_encode(['kid' => self::VAULT.'keys/app-key/'.$currentVersion, 'value' => 'wrapped']));
            }

            return new MockResponse(json_encode(['value' => Base64UrlSafe::encode($plaintext)]));
        }, self::VAULT);

        $kms = new AzureKeyVault($client, $this->staticToken('T'), self::VAULT);
        $dataKey = $kms->generateDataKey('app-key');
        $currentVersion = 'v2';

        $this->assertSame('app-key/v1', $dataKey->wrapped->keyId);
        $this->assertSame($dataKey->use(static fn (string $key): string => $key), $kms->unwrapDataKey($dataKey->wrapped)->use(static fn (string $key): string => $key));
        $this->assertSame(self::VAULT.'keys/app-key/v1/unwrapkey?api-version=7.4', $urls[1]);
    }

    public static function provideInvalidOperationKeyIds(): iterable
    {
        yield 'missing' => [null, 'app-key'];
        yield 'non-string' => [123, 'app-key'];
        yield 'versionless' => [self::VAULT.'keys/app-key', 'app-key'];
        yield 'another vault' => ['https://other.vault.azure.net/keys/app-key/v1', 'app-key'];
        yield 'another port' => ['https://my-vault.vault.azure.net:8443/keys/app-key/v1', 'app-key'];
        yield 'another key' => [self::VAULT.'keys/other/v1', 'app-key'];
        yield 'another version' => [self::VAULT.'keys/app-key/v2', 'app-key/v1'];
        yield 'unexpected path' => [self::VAULT.'secrets/app-key/v1', 'app-key'];
        yield 'query' => [self::VAULT.'keys/app-key/v1?x=1', 'app-key'];
        yield 'fragment' => [self::VAULT.'keys/app-key/v1#x', 'app-key'];
        yield 'credentials' => ['https://user@my-vault.vault.azure.net/keys/app-key/v1', 'app-key'];
        yield 'extra segment' => [self::VAULT.'keys/app-key/v1/extra', 'app-key'];
        yield 'malformed version' => [self::VAULT.'keys/app-key/v_1', 'app-key'];
    }

    public function testOperationKeyIdentifiersAreCaseInsensitive()
    {
        $client = new MockHttpClient(new MockResponse(json_encode(['kid' => 'https://MY-VAULT.VAULT.AZURE.NET/KEYS/APP-KEY/V1', 'value' => 'ciphertext'])), self::VAULT);

        $ciphertext = (new AzureKeyVault($client, $this->staticToken('T'), 'https://MY-VAULT.VAULT.AZURE.NET:443/'))->encrypt('app-key/v1', 'hello');

        $this->assertSame('APP-KEY/V1', $ciphertext->keyId);
    }

    public static function provideInvalidVaultBaseUris(): iterable
    {
        yield 'empty' => [''];
        yield 'missing host' => ['https:///'];
        yield 'insecure' => ['http://my-vault.vault.azure.net/'];
        yield 'path' => [self::VAULT.'keys/'];
        yield 'credentials' => ['https://user@my-vault.vault.azure.net/'];
        yield 'query' => [self::VAULT.'?x=1'];
        yield 'fragment' => [self::VAULT.'#x'];
        yield 'old positional algorithm' => ['A256GCM'];
    }

    #[DataProvider('provideInvalidVaultBaseUris')]
    public function testRejectsAnInvalidVaultBaseUri(string $vaultBaseUri)
    {
        $this->expectException(InvalidArgumentException::class);
        new AzureKeyVault($this->clientThatMustNotBeCalled(), $this->staticToken('T'), $vaultBaseUri);
    }

    #[DataProvider('provideInvalidOperationKeyIds')]
    public function testEncryptRejectsAnUnexpectedOperationKeyId(mixed $kid, string $requestedKeyId)
    {
        $client = new MockHttpClient(new MockResponse(json_encode(['kid' => $kid, 'value' => 'ciphertext'])), self::VAULT);

        $this->expectException(RuntimeException::class);
        (new AzureKeyVault($client, $this->staticToken('T'), self::VAULT))->encrypt($requestedKeyId, 'hello');
    }

    #[DataProvider('provideInvalidOperationKeyIds')]
    public function testGenerateDataKeyRejectsAnUnexpectedOperationKeyId(mixed $kid, string $requestedKeyId)
    {
        $client = new MockHttpClient(new MockResponse(json_encode(['kid' => $kid, 'value' => 'wrapped'])), self::VAULT);

        $this->expectException(RuntimeException::class);
        (new AzureKeyVault($client, $this->staticToken('T'), self::VAULT))->generateDataKey($requestedKeyId);
    }

    public function testRejectedCachedTokenIsRefreshedAndRetried()
    {
        $tokenRequests = 0;
        $kmsHeaders = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$tokenRequests, &$kmsHeaders): MockResponse {
            if (str_starts_with($url, 'https://login.microsoftonline.com/')) {
                return new MockResponse(json_encode(['access_token' => 'T'.++$tokenRequests, 'expires_in' => 3600]));
            }

            $kmsHeaders[] = $options['headers'];

            return 2 === \count($kmsHeaders)
                ? new MockResponse(json_encode(['error' => ['message' => 'expired token']]), ['http_code' => 401])
                : new MockResponse(json_encode(['kid' => self::VAULT.'keys/app-key/v1', 'value' => 'ciphertext']));
        }, self::VAULT);

        $kms = new AzureKeyVault($client, new ClientCredentialsTokenProvider($client, 'tenant', 'client', 'secret'), self::VAULT);
        $kms->encrypt('app-key', 'first');
        $kms->encrypt('app-key', 'second');
        $kms->encrypt('app-key', 'third');

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
            (new AzureKeyVault($this->clientThatMustNotBeCalled(), $provider, self::VAULT))->encrypt('app-key', 'hello');
            $this->fail('The transport error should be wrapped.');
        } catch (RuntimeException $e) {
            $this->assertSame('Failed to reach Azure Key Vault.', $e->getMessage());
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
            new MockResponse(json_encode(['kid' => self::VAULT.'keys/app-key/v1', 'value' => 'ciphertext'])),
        ], ['T1', 'T2', 'T3'], $tokenRequests, $kmsRequests);
        $kms = new AzureKeyVault($client, new ClientCredentialsTokenProvider($client, 'tenant', 'client', 'secret'), self::VAULT);

        try {
            $kms->encrypt('app-key', 'first');
            $this->fail('The second 401 should be reported.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('(HTTP 401): "replacement rejected"', $e->getMessage());
        }

        $this->assertCount(2, $kmsRequests);
        $this->assertSame(2, $tokenRequests);

        $kms->encrypt('app-key', 'second');
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
        $kms = new AzureKeyVault($client, new ClientCredentialsTokenProvider($client, 'tenant', 'client', 'secret'), self::VAULT);

        try {
            $kms->encrypt('app-key', 'first');
            $this->fail('The 403 should be reported.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('(HTTP 403): "permission denied"', $e->getMessage());
        }

        $this->assertSame(2, $tokenRequests);
        $this->assertCount(2, $kmsRequests);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('(HTTP 403): "permission denied"');
        try {
            $kms->encrypt('app-key', 'second');
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
            new MockResponse(json_encode(['kid' => self::VAULT.'keys/app-key/v1', 'value' => 'ciphertext'])),
        ], ['T1'], $tokenRequests, $kmsRequests);
        $kms = new AzureKeyVault($client, new ClientCredentialsTokenProvider($client, 'tenant', 'client', 'secret'), self::VAULT);

        try {
            $kms->encrypt('app-key', 'first');
            $this->fail('The KMS error should be reported.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString(\sprintf('(HTTP %d): "request denied"', $status), $e->getMessage());
        }

        $this->assertSame(1, $tokenRequests);
        $this->assertCount(1, $kmsRequests);

        $kms->encrypt('app-key', 'second');
        $this->assertSame(1, $tokenRequests);
        $this->assertContains('Authorization: Bearer T1', $kmsRequests[1][3]);
    }

    public function testReacquiringTheSameRejectedTokenLeavesItUncached()
    {
        $tokenRequests = 0;
        $kmsRequests = [];
        $client = $this->mockClient([
            new MockResponse(json_encode(['error' => ['message' => 'expired']]), ['http_code' => 401]),
            new MockResponse(json_encode(['kid' => self::VAULT.'keys/app-key/v1', 'value' => 'ciphertext'])),
        ], ['T1', 'T1', 'T2'], $tokenRequests, $kmsRequests);
        $kms = new AzureKeyVault($client, new ClientCredentialsTokenProvider($client, 'tenant', 'client', 'secret'), self::VAULT);

        try {
            $kms->encrypt('app-key', 'first');
            $this->fail('The 401 should be reported.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('(HTTP 401): "expired"', $e->getMessage());
        }

        $this->assertCount(1, $kmsRequests);
        $this->assertSame(2, $tokenRequests);

        $kms->encrypt('app-key', 'second');
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
            new MockResponse(json_encode(['kid' => self::VAULT.'keys/app-key/v1', 'value' => 'wrapped'])),
        ], ['T1', 'T2'], $tokenRequests, $kmsRequests)->withOptions(['on_progress' => static function (int $downloaded, int $downloadSize, array $info) use (&$canceledResponses): void {
            if ($info['canceled'] ?? false) {
                ++$canceledResponses;
            }
        }]);
        $kms = new AzureKeyVault($client, new ClientCredentialsTokenProvider($client, 'tenant', 'client', 'secret'), self::VAULT);

        $dataKey = $kms->generateDataKey('app-key');

        $this->assertSame(2, $tokenRequests);
        $this->assertCount(2, $kmsRequests);
        $this->assertSame('POST', $kmsRequests[0][0]);
        $this->assertSame($kmsRequests[0][0], $kmsRequests[1][0]);
        $this->assertSame($kmsRequests[0][1], $kmsRequests[1][1]);
        $this->assertSame($kmsRequests[0][2], $kmsRequests[1][2]);
        $this->assertContains('Authorization: Bearer T1', $kmsRequests[0][3]);
        $this->assertContains('Authorization: Bearer T2', $kmsRequests[1][3]);
        $this->assertSame(1, $canceledResponses);
        $this->assertSame(Base64UrlSafe::encode($dataKey->use(static fn (string $key): string => $key)), json_decode($kmsRequests[0][2], true)['value']);
        $this->assertSame(self::VAULT.'keys/app-key/wrapkey?api-version=7.4', $kmsRequests[1][1]);
    }

    public function testTokenAcquisitionFailureAfterUnauthorizedResponseIsReported()
    {
        $tokenRequests = 0;
        $kmsRequests = [];
        $client = $this->mockClient([
            new MockResponse(json_encode(['error' => ['message' => 'expired']]), ['http_code' => 401]),
        ], ['T1', new MockResponse(json_encode(['error' => 'invalid_client']), ['http_code' => 503])], $tokenRequests, $kmsRequests);
        $kms = new AzureKeyVault($client, new ClientCredentialsTokenProvider($client, 'tenant', 'client', 'secret'), self::VAULT);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Azure AD token request failed (HTTP 503)');
        try {
            $kms->encrypt('app-key', 'hello');
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
            (new AzureKeyVault($client, $provider, self::VAULT))->encrypt('app-key', 'hello');
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
            (new AzureKeyVault($client, $provider, self::VAULT))->encrypt('app-key', 'hello');
            $this->fail('The transport error should be wrapped.');
        } catch (RuntimeException $e) {
            $this->assertSame('Failed to reach Azure Key Vault.', $e->getMessage());
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
            new MockResponse(json_encode(['error' => ['message' => 'invalid ciphertext']]), ['http_code' => 400]),
        ], ['T1', 'T2'], $tokenRequests, $kmsRequests);
        $kms = new AzureKeyVault($client, new ClientCredentialsTokenProvider($client, 'tenant', 'client', 'secret'), self::VAULT);

        $this->expectException(DecryptionFailedException::class);
        try {
            $kms->decrypt(new Ciphertext('CipherFromAzure', 'app-key'));
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
        $kms = new AzureKeyVault($client, new ClientCredentialsTokenProvider($client, 'tenant', 'client', 'secret'), self::VAULT);

        try {
            $kms->decrypt(new Ciphertext('CipherFromAzure', 'app-key'));
            $this->fail('The second 401 should be reported.');
        } catch (RuntimeException $e) {
            $this->assertNotInstanceOf(DecryptionFailedException::class, $e);
            $this->assertStringContainsString('(HTTP 401): "replacement rejected"', $e->getMessage());
        }

        $this->assertSame(2, $tokenRequests);
        $this->assertCount(2, $kmsRequests);
    }

    public function testEncryptPinsSpecificKeyVersion()
    {
        $captured = null;
        $client = new MockHttpClient(static function (string $method, string $url) use (&$captured): MockResponse {
            $captured = $url;

            return new MockResponse(json_encode(['kid' => self::VAULT.'keys/app-key/abc123', 'value' => 'ct']));
        }, self::VAULT);

        $ciphertext = (new AzureKeyVault($client, $this->staticToken('T'), self::VAULT))->encrypt('app-key/abc123', 'hello');

        $this->assertSame('app-key/abc123', $ciphertext->keyId);
        $this->assertSame(self::VAULT.'keys/app-key/abc123/encrypt?api-version=7.4', $captured);
    }

    public function testEncryptRejectsKeyIdWithMoreThanOneSlash()
    {
        $kms = new AzureKeyVault(new MockHttpClient([], self::VAULT), $this->staticToken('T'), self::VAULT);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"<name>" or "<name>/<version>"');
        $kms->encrypt('app-key/v1/extra', 'hello');
    }

    public function testEncryptRejectsAnEmptyKeyName()
    {
        $kms = new AzureKeyVault(new MockHttpClient([], self::VAULT), $this->staticToken('T'), self::VAULT);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"<name>" or "<name>/<version>"');
        $kms->encrypt('', 'hello');
    }

    public function testEncryptRejectsAKeyIdWithAnEmptyVersion()
    {
        $kms = new AzureKeyVault(new MockHttpClient([], self::VAULT), $this->staticToken('T'), self::VAULT);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"<name>" or "<name>/<version>"');
        $kms->encrypt('app-key/', 'hello');
    }

    public function testEncryptRejectsAadOnRsa()
    {
        $kms = new AzureKeyVault(new MockHttpClient([], self::VAULT), $this->staticToken('T'), self::VAULT);

        $this->expectException(UnsupportedOperationException::class);
        $kms->encrypt('app-key', 'hello', 'tenant=acme');
    }

    public function testEncryptRejectsDeterministic()
    {
        $kms = new AzureKeyVault(new MockHttpClient([], self::VAULT), $this->staticToken('T'), self::VAULT);

        $this->expectException(UnsupportedOperationException::class);
        $kms->encrypt('app-key', 'hello', deterministic: true);
    }

    public function testEncryptWithAeadBundlesAlgorithmIvAndTagIntoTheBlob()
    {
        $captured = null;
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured = json_decode($options['body'], true);

            return new MockResponse(json_encode([
                'kid' => self::VAULT.'keys/app-key/v1',
                'value' => 'CTVAL',
                'iv' => 'IV',
                'tag' => 'TAG',
            ]));
        }, self::VAULT);

        $kms = new AzureKeyVault($client, $this->staticToken('T'), self::VAULT, 'A256GCM', 'A256GCM');
        $ciphertext = $kms->encrypt('app-key', 'hello', 'tenant=acme');

        $this->assertSame('app-key/v1', $ciphertext->keyId);
        $this->assertSame('A256GCM.IV.TAG.CTVAL', $ciphertext->blob);
        $this->assertSame(Base64UrlSafe::encode('tenant=acme'), $captured['aad']);
    }

    public function testDecryptRsaSendsTheBlobBackVerbatim()
    {
        $captured = null;
        $client = new MockHttpClient(static function ($method, $url, $options) use (&$captured): MockResponse {
            $captured = json_decode($options['body'], true);

            return new MockResponse(json_encode(['value' => Base64UrlSafe::encode('hello')]));
        }, self::VAULT);

        $plaintext = (new AzureKeyVault($client, $this->staticToken('T'), self::VAULT))->decrypt(new Ciphertext('CipherFromAzure', 'app-key'));

        $this->assertSame('hello', $plaintext);
        $this->assertSame('CipherFromAzure', $captured['value']);
    }

    public function testDecryptAeadParsesTheBundledBlob()
    {
        $captured = null;
        $client = new MockHttpClient(static function ($method, $url, $options) use (&$captured): MockResponse {
            $captured = json_decode($options['body'], true);

            return new MockResponse(json_encode(['value' => Base64UrlSafe::encode('hello')]));
        }, self::VAULT);

        $kms = new AzureKeyVault($client, $this->staticToken('T'), self::VAULT, 'A256GCM', 'A256GCM');
        $kms->decrypt(new Ciphertext('A256GCM.IV.TAG.CTVAL', 'app-key'), 'tenant=acme');

        $this->assertSame('IV', $captured['iv']);
        $this->assertSame('TAG', $captured['tag']);
        $this->assertSame('CTVAL', $captured['value']);
        $this->assertSame(Base64UrlSafe::encode('tenant=acme'), $captured['aad']);
    }

    public function testDecryptAeadBlobDoesNotRequireMatchingConfiguredAlgorithm()
    {
        $captured = null;
        $client = new MockHttpClient(static function ($method, $url, $options) use (&$captured): MockResponse {
            $captured = json_decode($options['body'], true);

            return new MockResponse(json_encode(['value' => Base64UrlSafe::encode('hello')]));
        }, self::VAULT);

        $kms = new AzureKeyVault($client, $this->staticToken('T'), self::VAULT);
        $plaintext = $kms->decrypt(new Ciphertext('A256GCM.IV.TAG.CTVAL', 'app-key'));

        $this->assertSame('hello', $plaintext);
        $this->assertSame('A256GCM', $captured['alg']);
        $this->assertSame('IV', $captured['iv']);
    }

    public function testDecryptRejectsAeadShapedBlobWithUnknownAlgorithm()
    {
        $kms = new AzureKeyVault(new MockHttpClient([], self::VAULT), $this->staticToken('T'), self::VAULT, 'A256GCM', 'A256GCM');

        $this->expectException(DecryptionFailedException::class);
        $kms->decrypt(new Ciphertext('CipherBlobWithoutPrefix', 'app-key'));
    }

    public function testUnwrapDataKeyAlsoAutoDetectsAeadAlgorithm()
    {
        $captured = null;
        $client = new MockHttpClient(static function ($method, $url, $options) use (&$captured): MockResponse {
            $captured = json_decode($options['body'], true);

            return new MockResponse(json_encode(['value' => Base64UrlSafe::encode(str_repeat("\xCC", 32))]));
        }, self::VAULT);

        $kms = new AzureKeyVault($client, $this->staticToken('T'), self::VAULT);
        $kms->unwrapDataKey(new Ciphertext('A256GCM.IV.TAG.WRAPPED', 'app-key'));

        $this->assertSame('A256GCM', $captured['alg']);
    }

    public function testDecryptOnHttp400IsADecryptionFailure()
    {
        $client = new MockHttpClient(
            new MockResponse(json_encode(['error' => ['code' => 'BadParameter', 'message' => 'invalid']]), ['http_code' => 400]),
            self::VAULT,
        );

        $this->expectException(DecryptionFailedException::class);
        (new AzureKeyVault($client, $this->staticToken('T'), self::VAULT))->decrypt(new Ciphertext('tampered', 'app-key'));
    }

    public function testDecryptOnHttp404IsAlsoADecryptionFailure()
    {
        $client = new MockHttpClient(
            new MockResponse(json_encode(['error' => ['code' => 'KeyNotFound', 'message' => 'no such key']]), ['http_code' => 404]),
            self::VAULT,
        );

        $this->expectException(DecryptionFailedException::class);
        (new AzureKeyVault($client, $this->staticToken('T'), self::VAULT))->decrypt(new Ciphertext('whatever', 'unknown-key'));
    }

    public function testEncryptOnHttp404IsKeyNotFound()
    {
        $client = new MockHttpClient(
            new MockResponse(json_encode(['error' => ['code' => 'KeyNotFound', 'message' => 'no such key']]), ['http_code' => 404]),
            self::VAULT,
        );

        $this->expectException(KeyNotFoundException::class);
        (new AzureKeyVault($client, $this->staticToken('T'), self::VAULT))->encrypt('unknown-key', 'hello');
    }

    public function testServerErrorBubblesAsRuntimeException()
    {
        $client = new MockHttpClient(
            new MockResponse(json_encode(['error' => ['code' => 'InternalError', 'message' => 'kaboom']]), ['http_code' => 500]),
            self::VAULT,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HTTP 500');
        (new AzureKeyVault($client, $this->staticToken('T'), self::VAULT))->encrypt('app-key', 'hello');
    }

    public function testNonJsonErrorBodySurfacesAsRuntimeExceptionWithoutTheBody()
    {
        $client = new MockHttpClient(
            new MockResponse('<html>load balancer error page</html>', ['http_code' => 503]),
            self::VAULT,
        );

        try {
            (new AzureKeyVault($client, $this->staticToken('T'), self::VAULT))->encrypt('app-key', 'hello');
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
            self::VAULT,
        );

        try {
            (new AzureKeyVault($client, $this->staticToken('T'), self::VAULT))->encrypt('app-key', 'hello');
            $this->fail('A RuntimeException should have been thrown.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('HTTP 301', $e->getMessage());
            $this->assertStringNotContainsString('<html>', $e->getMessage());
        }
    }

    public function testGenerateDataKeyDrawsLocallyAndCallsWrapKey()
    {
        $captured = null;
        $client = new MockHttpClient(static function ($method, $url, $options) use (&$captured): MockResponse {
            $captured = [$url, json_decode($options['body'], true)];

            return new MockResponse(json_encode(['kid' => self::VAULT.'keys/app-key/v1', 'value' => 'WrappedDek']));
        }, self::VAULT);

        $dataKey = (new AzureKeyVault($client, $this->staticToken('T'), self::VAULT))->generateDataKey('app-key', 32);

        [$url, $body] = $captured;
        $this->assertSame(self::VAULT.'keys/app-key/wrapkey?api-version=7.4', $url);
        $this->assertSame('RSA-OAEP-256', $body['alg']);
        $this->assertSame('app-key/v1', $dataKey->wrapped->keyId);
        $this->assertSame('WrappedDek', $dataKey->wrapped->blob);
    }

    public function testAeadDataKeyWrapPinsTheVersionAndUnwrapsAfterRotation()
    {
        $version = 'v1';
        $requests = [];
        $wrappedPlaintext = null;
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$version, &$requests, &$wrappedPlaintext): MockResponse {
            $body = json_decode($options['body'], true);
            $requests[] = [$url, $body];

            if (str_contains($url, '/wrapkey?')) {
                $wrappedPlaintext = $body['value'];

                return new MockResponse(json_encode([
                    'kid' => self::VAULT.'keys/app-key/'.$version,
                    'value' => 'CTVAL',
                    'iv' => 'IV',
                    'tag' => 'TAG',
                ]));
            }

            return new MockResponse(json_encode(['value' => $wrappedPlaintext]));
        }, self::VAULT);

        $kms = new AzureKeyVault($client, $this->staticToken('T'), self::VAULT, 'RSA-OAEP-256', 'A256GCM');
        $dataKey = $kms->generateDataKey('app-key', 32, 'tenant=acme');
        $version = 'v2';
        $unwrapped = $kms->unwrapDataKey($dataKey->wrapped, 'tenant=acme');

        $this->assertSame('app-key/v1', $dataKey->wrapped->keyId);
        $this->assertSame('A256GCM.IV.TAG.CTVAL', $dataKey->wrapped->blob);
        $this->assertSame($dataKey->use(static fn (string $key): string => $key), $unwrapped->use(static fn (string $key): string => $key));
        $this->assertSame(self::VAULT.'keys/app-key/wrapkey?api-version=7.4', $requests[0][0]);
        $this->assertSame(self::VAULT.'keys/app-key/v1/unwrapkey?api-version=7.4', $requests[1][0]);
        $this->assertSame('A256GCM', $requests[0][1]['alg']);
        $this->assertSame(Base64UrlSafe::encode('tenant=acme'), $requests[0][1]['aad']);
        $this->assertSame(['alg' => 'A256GCM', 'iv' => 'IV', 'tag' => 'TAG', 'value' => 'CTVAL', 'aad' => Base64UrlSafe::encode('tenant=acme')], $requests[1][1]);
    }

    public function testGenerateDataKeyRejectsTooShortLengths()
    {
        $kms = new AzureKeyVault(new MockHttpClient([], self::VAULT), $this->staticToken('T'), self::VAULT);

        $this->expectException(InvalidArgumentException::class);
        $kms->generateDataKey('app-key', 8);
    }

    public function testUnwrapDataKeyDecodesPlaintext()
    {
        $client = new MockHttpClient(
            new MockResponse(json_encode(['value' => Base64UrlSafe::encode(str_repeat("\xAB", 32))])),
            self::VAULT,
        );

        $dataKey = (new AzureKeyVault($client, $this->staticToken('T'), self::VAULT))->unwrapDataKey(new Ciphertext('WrappedDek', 'app-key'));

        $this->assertSame(str_repeat("\xAB", 32), $dataKey->use(static fn (string $p): string => $p));
    }

    public static function provideMalformedKeyIds(): iterable
    {
        yield 'too many segments' => ['a/b/c'];
        yield 'empty' => [''];
        yield 'empty version' => ['app-key/'];
        yield 'parent traversal' => ['../secrets'];
        yield 'name longer than 127 characters' => [str_repeat('a', 128)];
        yield 'name outside Azure grammar' => ['app_key'];
    }

    #[DataProvider('provideMalformedKeyIds')]
    public function testDecryptRejectsAMalformedKeyIdAsADecryptionFailure(string $keyId)
    {
        $kms = new AzureKeyVault($this->clientThatMustNotBeCalled(), $this->staticToken('T'), self::VAULT);

        $this->expectException(DecryptionFailedException::class);
        $kms->decrypt(new Ciphertext('blob', $keyId));
    }

    #[DataProvider('provideMalformedKeyIds')]
    public function testUnwrapDataKeyRejectsAMalformedKeyIdAsADecryptionFailure(string $keyId)
    {
        $kms = new AzureKeyVault($this->clientThatMustNotBeCalled(), $this->staticToken('T'), self::VAULT);

        $this->expectException(DecryptionFailedException::class);
        $kms->unwrapDataKey(new Ciphertext('blob', $keyId));
    }

    public function testTransportErrorWhileReadingTheBodySurfacesAsRuntimeException()
    {
        $client = new MockHttpClient(new MockResponse((static function (): \Generator {
            yield '{"val';
            yield '';
        })()), self::VAULT);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HTTP 200');
        (new AzureKeyVault($client, $this->staticToken('T'), self::VAULT))->decrypt(new Ciphertext('blob', 'app-key'));
    }

    public function testTransportErrorWhileReadingAnErrorBodySurfacesAsRuntimeException()
    {
        $client = new MockHttpClient(new MockResponse((static function (): \Generator {
            yield '{"err';
            yield '';
        })(), ['http_code' => 500]), self::VAULT);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HTTP 500');
        (new AzureKeyVault($client, $this->staticToken('T'), self::VAULT))->encrypt('app-key', 'hello');
    }

    public function testArrayValuedErrorMessageIsReportedAsUnknown()
    {
        $client = new MockHttpClient(
            new MockResponse(json_encode(['error' => ['message' => ['a']]]), ['http_code' => 403]),
            self::VAULT,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('(HTTP 403): "unknown error"');
        (new AzureKeyVault($client, $this->staticToken('T'), self::VAULT))->encrypt('app-key', 'hello');
    }

    private function clientThatMustNotBeCalled(): MockHttpClient
    {
        return new MockHttpClient(function (string $method, string $url): MockResponse {
            $this->fail(\sprintf('No request should have been sent, got "%s %s".', $method, $url));
        }, self::VAULT);
    }

    private function mockClient(array $kmsResponses, array $issuedTokens, int &$tokenRequests, array &$kmsRequests): MockHttpClient
    {
        return new MockHttpClient(static function (string $method, string $url, array $options) use (&$kmsResponses, &$issuedTokens, &$tokenRequests, &$kmsRequests): MockResponse {
            if (str_starts_with($url, 'https://login.microsoftonline.com/')) {
                ++$tokenRequests;
                $token = array_shift($issuedTokens);
                if (null === $token) {
                    throw new \LogicException('Unexpected token request.');
                }

                return $token instanceof MockResponse ? $token : new MockResponse(json_encode(['access_token' => $token, 'expires_in' => 3600]));
            }

            $kmsRequests[] = [$method, $url, $options['body'], $options['headers']];

            return array_shift($kmsResponses) ?? throw new \LogicException('Unexpected KMS request.');
        }, self::VAULT);
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
