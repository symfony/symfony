<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\HashiCorpVault\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\KeyManagement\Bridge\HashiCorpVault\StaticTokenProvider;
use Symfony\Component\KeyManagement\Bridge\HashiCorpVault\TokenProviderInterface;
use Symfony\Component\KeyManagement\Bridge\HashiCorpVault\TransitKms;
use Symfony\Component\KeyManagement\Ciphertext;
use Symfony\Component\KeyManagement\Exception\DecryptionFailedException;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;
use Symfony\Component\KeyManagement\Exception\KeyNotFoundException;
use Symfony\Component\KeyManagement\Exception\RuntimeException;
use Symfony\Component\KeyManagement\Exception\UnsupportedOperationException;

class TransitKmsTest extends TestCase
{
    private const string INVALID_TOKEN_BODY = '{"errors":["2 errors occurred:\n\t* permission denied\n\t* invalid token\n\n"]}';

    public function testEncryptSendsBase64PlaintextAndReturnsTheVaultBlob()
    {
        $captured = null;
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured = [$method, $url, json_decode($options['body'], true), $options['headers']];

            return new MockResponse(json_encode(['data' => ['ciphertext' => 'vault:v1:abc']]));
        }, 'https://vault.local/v1/');

        $kms = new TransitKms($client, new StaticTokenProvider('s.token'));
        $ciphertext = $kms->encrypt('app', 'hello');

        $this->assertSame('app', $ciphertext->keyId);
        $this->assertSame('vault:v1:abc', $ciphertext->blob);

        [$method, $url, $body, $headers] = $captured;
        $this->assertSame('POST', $method);
        $this->assertSame('https://vault.local/v1/transit/encrypt/app', $url);
        $this->assertSame(['plaintext' => base64_encode('hello'), 'context' => base64_encode("symfony/key-management/hashicorp-vault/transit/v1\0")], $body);
        $this->assertContains('X-Vault-Token: s.token', $headers);
    }

    public function testEncryptPrefixesAadBeforeEncodingTheContext()
    {
        $captured = null;
        $client = new MockHttpClient(static function ($m, $u, $o) use (&$captured) {
            if (str_contains($u, '/keys/')) {
                return new MockResponse(json_encode(['data' => ['derived' => true]]));
            }
            $captured = json_decode($o['body'], true);

            return new MockResponse(json_encode(['data' => ['ciphertext' => 'vault:v1:x']]));
        }, 'https://vault.local/v1/');

        (new TransitKms($client, new StaticTokenProvider('t')))->encrypt('app', 'hello', 'opaque-aad');

        $this->assertSame(base64_encode("symfony/key-management/hashicorp-vault/transit/v1\0opaque-aad"), $captured['context']);
    }

    #[DataProvider('provideDifferentAadValues')]
    public function testDecryptionRequiresTheSameAad(string $aad, string $otherAad)
    {
        $kms = $this->contextBoundKms();
        $ciphertext = $kms->encrypt('app', 'hello', $aad);

        $this->assertSame('hello', $kms->decrypt($ciphertext, $aad));

        $this->expectException(DecryptionFailedException::class);
        $kms->decrypt($ciphertext, $otherAad);
    }

    #[DataProvider('provideDifferentAadValues')]
    public function testUnwrappingRequiresTheSameAad(string $aad, string $otherAad)
    {
        $kms = $this->contextBoundKms();
        $dataKey = $kms->generateDataKey('app', 32, $aad);

        $this->assertSame(
            $dataKey->use(static fn (string $key): string => $key),
            $kms->unwrapDataKey($dataKey->wrapped, $aad)->use(static fn (string $key): string => $key),
        );

        $this->expectException(DecryptionFailedException::class);
        $kms->unwrapDataKey($dataKey->wrapped, $otherAad);
    }

    public static function provideDifferentAadValues(): iterable
    {
        $label = 'symfony/key-management/hashicorp-vault/transit/v1';
        $prefix = $label."\0";

        yield 'fallback to empty' => [$label, ''];
        yield 'empty to fallback' => ['', $label];
        yield 'prefix to empty' => [$prefix, ''];
        yield 'empty to prefix' => ['', $prefix];
        yield 'binary to prefix' => ["\0\xFF", $prefix."\0\xFF"];
        yield 'prefix to binary' => [$prefix."\0\xFF", "\0\xFF"];
        yield 'NUL suffix' => ['tenant', "tenant\0"];
        yield 'zero to empty' => ['0', ''];
    }

    public function testAadOnADerivedKeyIsAcceptedAndTheKeyIsReadOnce()
    {
        $requests = [];
        $client = new MockHttpClient(static function (string $method, string $url) use (&$requests): MockResponse {
            $requests[] = [$method, $url];
            if (str_contains($url, '/keys/')) {
                return new MockResponse(json_encode(['data' => ['derived' => true]]));
            }
            if (str_contains($url, '/decrypt/')) {
                return new MockResponse(json_encode(['data' => ['plaintext' => base64_encode('hello')]]));
            }

            return new MockResponse(json_encode(['data' => ['ciphertext' => 'vault:v1:x']]));
        }, 'https://vault.local/v1/');

        $kms = new TransitKms($client, new StaticTokenProvider('t'));
        $ciphertext = $kms->encrypt('app', 'hello', 'opaque-aad');
        $kms->decrypt($ciphertext, 'opaque-aad');

        $this->assertSame([
            ['GET', 'https://vault.local/v1/transit/keys/app'],
            ['POST', 'https://vault.local/v1/transit/encrypt/app'],
            ['POST', 'https://vault.local/v1/transit/decrypt/app'],
        ], $requests);
    }

    public function testAadOnANonDerivedKeyIsRefused()
    {
        $client = new MockHttpClient(
            new MockResponse(json_encode(['data' => ['derived' => false]])),
            'https://vault.local/v1/',
        );

        $this->expectException(UnsupportedOperationException::class);
        $this->expectExceptionMessage('derived');
        (new TransitKms($client, new StaticTokenProvider('t')))->encrypt('app', 'hello', 'opaque-aad');
    }

    public function testEmptyAadNeverTriggersTheKeyRead()
    {
        $urls = [];
        $client = new MockHttpClient(static function ($m, $u) use (&$urls) {
            $urls[] = $u;

            return new MockResponse(json_encode(['data' => ['ciphertext' => 'vault:v1:x']]));
        }, 'https://vault.local/v1/');

        (new TransitKms($client, new StaticTokenProvider('t')))->encrypt('app', 'hello');

        $this->assertSame(['https://vault.local/v1/transit/encrypt/app'], $urls);
    }

    public function testUnknownKeyOnTheAadCheckSurfacesAsKeyNotFoundOnEncrypt()
    {
        $client = new MockHttpClient(
            new MockResponse('', ['http_code' => 404]),
            'https://vault.local/v1/',
        );

        $this->expectException(KeyNotFoundException::class);
        (new TransitKms($client, new StaticTokenProvider('t')))->encrypt('missing', 'hello', 'opaque-aad');
    }

    public function testUnknownKeyOnTheAadCheckStaysMaskedOnDecrypt()
    {
        $client = new MockHttpClient(
            new MockResponse('', ['http_code' => 404]),
            'https://vault.local/v1/',
        );

        $this->expectException(DecryptionFailedException::class);
        (new TransitKms($client, new StaticTokenProvider('t')))->decrypt(new Ciphertext('vault:v1:x', 'missing'), 'opaque-aad');
    }

    public function testDecryptDecodesPlaintext()
    {
        $client = new MockHttpClient(
            new MockResponse(json_encode(['data' => ['plaintext' => base64_encode('hello')]])),
            'https://vault.local/v1/',
        );

        $plaintext = (new TransitKms($client, new StaticTokenProvider('t')))->decrypt(new Ciphertext('vault:v1:abc', 'app'));

        $this->assertSame('hello', $plaintext);
    }

    public function testDecryptOnHttp400IsADecryptionFailure()
    {
        $client = new MockHttpClient(
            new MockResponse(json_encode(['errors' => ['invalid ciphertext']]), ['http_code' => 400]),
            'https://vault.local/v1/',
        );

        $this->expectException(DecryptionFailedException::class);
        (new TransitKms($client, new StaticTokenProvider('t')))->decrypt(new Ciphertext('vault:v1:tampered', 'app'));
    }

    public function testDecryptOnHttp500IsNotASilentDecryptionFailure()
    {
        $client = new MockHttpClient(
            new MockResponse(json_encode(['errors' => ['internal']]), ['http_code' => 500]),
            'https://vault.local/v1/',
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HTTP 500');
        (new TransitKms($client, new StaticTokenProvider('t')))->decrypt(new Ciphertext('vault:v1:x', 'app'));
    }

    public static function provideNonDecryptClientErrors(): iterable
    {
        yield 'unauthorized' => [401, 'permission denied'];
        yield 'forbidden' => [403, 'permission denied'];
        yield 'rate limited' => [429, 'rate limit exceeded'];
    }

    #[DataProvider('provideNonDecryptClientErrors')]
    public function testDecryptDoesNotMaskAuthOrQuotaErrors(int $status, string $vaultMessage)
    {
        $client = new MockHttpClient(
            new MockResponse(json_encode(['errors' => [$vaultMessage]]), ['http_code' => $status]),
            'https://vault.local/v1/',
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(\sprintf('HTTP %d', $status));
        (new TransitKms($client, new StaticTokenProvider('t')))->decrypt(new Ciphertext('vault:v1:x', 'app'));
    }

    public function testHttp404OnEncryptSurfacesAsKeyNotFound()
    {
        $client = new MockHttpClient(
            new MockResponse('', ['http_code' => 404]),
            'https://vault.local/v1/',
        );

        $this->expectException(KeyNotFoundException::class);
        (new TransitKms($client, new StaticTokenProvider('t')))->encrypt('missing', 'hello');
    }

    public function testHttp404OnDecryptIsMaskedAsDecryptionFailure()
    {
        $client = new MockHttpClient(
            new MockResponse('', ['http_code' => 404]),
            'https://vault.local/v1/',
        );

        $this->expectException(DecryptionFailedException::class);
        (new TransitKms($client, new StaticTokenProvider('t')))->decrypt(new Ciphertext('vault:v1:x', 'missing'));
    }

    public function testNonJsonErrorBodySurfacesAsRuntimeExceptionWithoutTheBody()
    {
        $client = new MockHttpClient(
            new MockResponse('<html>load balancer error page</html>', ['http_code' => 503]),
            'https://vault.local/v1/',
        );

        try {
            (new TransitKms($client, new StaticTokenProvider('t')))->encrypt('app', 'hello');
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
            'https://vault.local/v1/',
        );

        try {
            (new TransitKms($client, new StaticTokenProvider('t')))->encrypt('app', 'hello');
            $this->fail('A RuntimeException should have been thrown.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('HTTP 301', $e->getMessage());
            $this->assertStringNotContainsString('<html>', $e->getMessage());
        }
    }

    public function testRedirectsAreNotFollowed()
    {
        $response = new MockResponse('', ['http_code' => 307, 'response_headers' => ['location' => 'https://elsewhere.example.com/']]);
        $client = (new MockHttpClient($response, 'https://vault.local/v1/'))->withOptions(['max_redirects' => 20]);

        try {
            (new TransitKms($client, new StaticTokenProvider('s.token')))->encrypt('app', 'hello');
            $this->fail('A RuntimeException should have been thrown.');
        } catch (RuntimeException) {
        }

        $this->assertSame(0, $response->getRequestOptions()['max_redirects']);
    }

    public function testGenerateDataKeyReturnsBothPlaintextAndWrapped()
    {
        $captured = null;
        $client = new MockHttpClient(static function ($m, $u, $o) use (&$captured) {
            $captured = [$u, json_decode($o['body'], true)];

            return new MockResponse(json_encode([
                'data' => [
                    'plaintext' => base64_encode(str_repeat("\xAA", 32)),
                    'ciphertext' => 'vault:v1:wrapped-dek',
                ],
            ]));
        }, 'https://vault.local/v1/');

        $dataKey = (new TransitKms($client, new StaticTokenProvider('t')))->generateDataKey('app', 32);

        [$url, $body] = $captured;
        $this->assertSame('https://vault.local/v1/transit/datakey/plaintext/app', $url);
        $this->assertSame(['bits' => 256, 'context' => base64_encode("symfony/key-management/hashicorp-vault/transit/v1\0")], $body);
        $this->assertSame('vault:v1:wrapped-dek', $dataKey->wrapped->blob);

        $extracted = $dataKey->use(static fn (string $p): string => $p);
        $this->assertSame(str_repeat("\xAA", 32), $extracted);
    }

    public function testEveryPathBindsTheSameContextWhenTheCallerBindsNothing()
    {
        $contexts = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$contexts): MockResponse {
            $contexts[] = json_decode($options['body'], true)['context'] ?? null;

            return new MockResponse(json_encode(['data' => [
                'plaintext' => base64_encode(str_repeat("\xAA", 32)),
                'ciphertext' => 'vault:v1:wrapped',
            ]]));
        }, 'https://vault.local/v1/');

        $kms = new TransitKms($client, new StaticTokenProvider('t'));
        $kms->encrypt('app', 'hello');
        $kms->decrypt(new Ciphertext('vault:v1:wrapped', 'app'));
        $kms->generateDataKey('app', 32);
        $kms->unwrapDataKey(new Ciphertext('vault:v1:wrapped', 'app'));

        $this->assertCount(4, $contexts);
        $this->assertIsString($contexts[0]);
        $this->assertNotSame('', $contexts[0]);
        $this->assertSame([$contexts[0]], array_unique($contexts), 'a data key wrapped on one path is rewrapped on another, so all four must bind the same.');
    }

    public function testTheDataKeyPathBindsAContextTheWrapAndTheUnwrapShare()
    {
        $requests = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $requests[] = [$url, json_decode($options['body'], true)];

            return new MockResponse(json_encode(['data' => [
                'plaintext' => base64_encode(str_repeat("\xAA", 32)),
                'ciphertext' => 'vault:v1:wrapped-dek',
            ]]));
        }, 'https://vault.local/v1/');

        $kms = new TransitKms($client, new StaticTokenProvider('t'));
        $kms->unwrapDataKey($kms->generateDataKey('app', 32)->wrapped);

        $this->assertCount(2, $requests, 'the key configuration is not read, so no policy has to grant it.');
        $this->assertStringContainsString('/datakey/plaintext/app', $requests[0][0]);
        $this->assertStringContainsString('/decrypt/app', $requests[1][0]);
        $this->assertArrayHasKey('context', $requests[0][1]);
        $this->assertArrayHasKey('context', $requests[1][1]);
        $this->assertSame($requests[0][1]['context'], $requests[1][1]['context'], 'Vault derives the same key on both paths, or the unwrap fails.');
    }

    public function testAFalsyAadOnTheDataKeyPathIsStillAuthenticated()
    {
        $contexts = [];
        $keyReads = 0;
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$contexts, &$keyReads): MockResponse {
            if (str_contains($url, '/keys/')) {
                ++$keyReads;

                return new MockResponse(json_encode(['data' => ['derived' => true]]));
            }
            $contexts[] = json_decode($options['body'], true)['context'] ?? null;

            return new MockResponse(json_encode(['data' => [
                'plaintext' => base64_encode(str_repeat("\xAA", 32)),
                'ciphertext' => 'vault:v1:wrapped-dek',
            ]]));
        }, 'https://vault.local/v1/');

        $kms = new TransitKms($client, new StaticTokenProvider('t'));
        $kms->unwrapDataKey($kms->generateDataKey('app', 32, '0')->wrapped, '0');

        $this->assertSame([base64_encode("symfony/key-management/hashicorp-vault/transit/v1\0".'0'), base64_encode("symfony/key-management/hashicorp-vault/transit/v1\0".'0')], $contexts, 'an AAD of "0" is not an absent one.');
        $this->assertSame(1, $keyReads, 'a bound AAD is checked against the key being derived, once per key.');
    }

    public function testAnAadOnTheDataKeyPathUsesTheSameFraming()
    {
        $contexts = [];
        $keyReads = 0;
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$contexts, &$keyReads): MockResponse {
            if (str_contains($url, '/keys/')) {
                ++$keyReads;

                return new MockResponse(json_encode(['data' => ['derived' => true]]));
            }
            $contexts[] = json_decode($options['body'], true)['context'] ?? null;

            return new MockResponse(json_encode(['data' => [
                'plaintext' => base64_encode(str_repeat("\xAA", 32)),
                'ciphertext' => 'vault:v1:wrapped-dek',
            ]]));
        }, 'https://vault.local/v1/');

        $kms = new TransitKms($client, new StaticTokenProvider('t'));
        $kms->unwrapDataKey($kms->generateDataKey('app', 32, 'tenant-a')->wrapped, 'tenant-a');

        $this->assertSame([base64_encode("symfony/key-management/hashicorp-vault/transit/v1\0tenant-a"), base64_encode("symfony/key-management/hashicorp-vault/transit/v1\0tenant-a")], $contexts);
        $this->assertSame(1, $keyReads, 'a bound AAD is checked against the key being derived, once per key.');
    }

    public function testGenerateDataKeyRejectsUnsupportedLengths()
    {
        $kms = new TransitKms(new MockHttpClient([], 'https://vault.local/v1/'), new StaticTokenProvider('t'));

        $this->expectException(InvalidArgumentException::class);
        $kms->generateDataKey('app', 24);
    }

    public static function provideSupportedDataKeyLengths(): iterable
    {
        yield '128 bits' => [16, 128];
        yield '256 bits' => [32, 256];
        yield '512 bits' => [64, 512];
    }

    #[DataProvider('provideSupportedDataKeyLengths')]
    public function testGenerateDataKeyAcceptsAllVaultSupportedLengths(int $byteLength, int $expectedBits)
    {
        $captured = null;
        $client = new MockHttpClient(static function ($m, $u, $o) use (&$captured, $byteLength) {
            $captured = json_decode($o['body'], true);

            return new MockResponse(json_encode([
                'data' => [
                    'plaintext' => base64_encode(str_repeat("\xCC", $byteLength)),
                    'ciphertext' => 'vault:v1:wrapped',
                ],
            ]));
        }, 'https://vault.local/v1/');

        (new TransitKms($client, new StaticTokenProvider('t')))->generateDataKey('app', $byteLength);

        $this->assertSame($expectedBits, $captured['bits']);
    }

    public function testUnwrapDataKeyRoutesThroughDecrypt()
    {
        $captured = null;
        $client = new MockHttpClient(static function ($m, $u, $o) use (&$captured) {
            $captured = $u;

            return new MockResponse(json_encode(['data' => ['plaintext' => base64_encode(str_repeat("\xCC", 32))]]));
        }, 'https://vault.local/v1/');

        $dataKey = (new TransitKms($client, new StaticTokenProvider('t')))->unwrapDataKey(new Ciphertext('vault:v1:wrapped', 'app'));

        $this->assertSame('https://vault.local/v1/transit/decrypt/app', $captured);
        $this->assertSame(str_repeat("\xCC", 32), $dataKey->use(static fn (string $p): string => $p));
        $this->assertSame('vault:v1:wrapped', $dataKey->wrapped->blob);
    }

    public function testCustomMountPointAndNamespace()
    {
        $captured = null;
        $client = new MockHttpClient(static function ($m, $u, $o) use (&$captured) {
            $captured = [$u, $o['headers']];

            return new MockResponse(json_encode(['data' => ['ciphertext' => 'vault:v1:x']]));
        }, 'https://vault.local/v1/');

        $kms = new TransitKms($client, new StaticTokenProvider('t'), 'kms-transit', 'tenant-acme');
        $kms->encrypt('app', 'hello');

        [$url, $headers] = $captured;
        $this->assertSame('https://vault.local/v1/kms-transit/encrypt/app', $url);
        $this->assertContains('X-Vault-Namespace: tenant-acme', $headers);
    }

    public function testNestedMountPointKeepsItsSlashes()
    {
        $captured = null;
        $client = new MockHttpClient(static function ($m, $u) use (&$captured) {
            $captured = $u;

            return new MockResponse(json_encode(['data' => ['ciphertext' => 'vault:v1:x']]));
        }, 'https://vault.local/v1/');

        (new TransitKms($client, new StaticTokenProvider('t'), 'kms/transit'))->encrypt('app', 'hello');

        $this->assertSame('https://vault.local/v1/kms/transit/encrypt/app', $captured);
    }

    public function testKeyIdIsUrlEncoded()
    {
        $captured = null;
        $client = new MockHttpClient(static function ($m, $u) use (&$captured) {
            $captured = $u;

            return new MockResponse(json_encode(['data' => ['ciphertext' => 'vault:v1:x']]));
        }, 'https://vault.local/v1/');

        (new TransitKms($client, new StaticTokenProvider('t')))->encrypt('keys/with/slashes', 'hello');

        $this->assertStringContainsString('/encrypt/keys%2Fwith%2Fslashes', $captured);
    }

    public function testMalformedSuccessResponseIsAClearRuntimeError()
    {
        $client = new MockHttpClient(
            new MockResponse(json_encode(['data' => ['unexpected' => true]])),
            'https://vault.local/v1/',
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('malformed');
        (new TransitKms($client, new StaticTokenProvider('t')))->encrypt('app', 'hello');
    }

    public function testAStringDataFieldIsAClearRuntimeError()
    {
        $client = new MockHttpClient(new MockResponse(json_encode(['data' => 'x'])), 'https://vault.local/v1/');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('malformed');
        (new TransitKms($client, new StaticTokenProvider('t')))->decrypt(new Ciphertext('vault:v1:abc', 'app'));
    }

    public function testOnlyStringErrorEntriesAreReported()
    {
        $client = new MockHttpClient(
            new MockResponse(json_encode(['errors' => [['nested'], 'permission denied']]), ['http_code' => 403]),
            'https://vault.local/v1/',
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('(HTTP 403): "permission denied"');
        (new TransitKms($client, new StaticTokenProvider('t')))->decrypt(new Ciphertext('vault:v1:x', 'app'));
    }

    public function testTransportErrorWhileReadingTheBodySurfacesAsRuntimeException()
    {
        $client = new MockHttpClient(new MockResponse((static function (): \Generator {
            yield '{"dat';
            yield '';
        })()), 'https://vault.local/v1/');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HTTP 200');
        (new TransitKms($client, new StaticTokenProvider('t')))->decrypt(new Ciphertext('vault:v1:abc', 'app'));
    }

    public function testTransportErrorWhileReadingAnErrorBodySurfacesAsRuntimeException()
    {
        $client = new MockHttpClient(new MockResponse((static function (): \Generator {
            yield '{"err';
            yield '';
        })(), ['http_code' => 500]), 'https://vault.local/v1/');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HTTP 500');
        (new TransitKms($client, new StaticTokenProvider('t')))->encrypt('app', 'hello');
    }

    public function testInvalidTokenIsRefreshedAndRetried()
    {
        $tokens = $this->tokenSequence('expired', 'fresh');
        $sentTokens = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$sentTokens): MockResponse {
            $sentTokens[] = $token = self::header($options['headers'], 'X-Vault-Token');

            return 'expired' === $token
                ? new MockResponse(self::INVALID_TOKEN_BODY, ['http_code' => 403])
                : new MockResponse(json_encode(['data' => ['ciphertext' => 'vault:v1:x']]));
        }, 'https://vault.local/v1/');

        $ciphertext = (new TransitKms($client, $tokens))->encrypt('app', 'hello');

        $this->assertSame('vault:v1:x', $ciphertext->blob);
        $this->assertSame(['expired', 'fresh'], $sentTokens);
        $this->assertSame(['expired'], $tokens->invalidated);
    }

    public function testPermissionDeniedWithoutInvalidTokenIsNotRetried()
    {
        $tokens = $this->tokenSequence('valid', 'unused');
        $client = new MockHttpClient([
            new MockResponse(json_encode(['errors' => ["1 error occurred:\n\t* permission denied\n\n"]]), ['http_code' => 403]),
        ], 'https://vault.local/v1/');

        try {
            (new TransitKms($client, $tokens))->encrypt('app', 'hello', 'opaque-aad');
            $this->fail('The 403 should be reported.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('HTTP 403', $e->getMessage());
            $this->assertStringContainsString('permission denied', $e->getMessage());
        }

        $this->assertSame(1, $client->getRequestsCount());
        $this->assertSame([], $tokens->invalidated);
    }

    public function testUnauthorizedIsNotRetried()
    {
        $tokens = $this->tokenSequence('valid', 'unused');
        $client = new MockHttpClient([
            new MockResponse(json_encode(['errors' => ['invalid token']]), ['http_code' => 401]),
        ], 'https://vault.local/v1/');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HTTP 401');

        try {
            (new TransitKms($client, $tokens))->encrypt('app', 'hello');
        } finally {
            $this->assertSame([], $tokens->invalidated);
        }
    }

    public function testInvalidReplacementTokenIsInvalidatedAndReported()
    {
        $tokens = $this->tokenSequence('expired', 'also-expired');
        $client = new MockHttpClient([
            new MockResponse(self::INVALID_TOKEN_BODY, ['http_code' => 403]),
            new MockResponse(self::INVALID_TOKEN_BODY, ['http_code' => 403]),
        ], 'https://vault.local/v1/');

        try {
            (new TransitKms($client, $tokens))->decrypt(new Ciphertext('vault:v1:x', 'app'));
            $this->fail('The second 403 should be reported.');
        } catch (RuntimeException $e) {
            $this->assertNotInstanceOf(DecryptionFailedException::class, $e);
            $this->assertStringContainsString('invalid token', $e->getMessage());
        }

        $this->assertSame(2, $client->getRequestsCount());
        $this->assertSame(['expired', 'also-expired'], $tokens->invalidated);
    }

    public function testReacquiringTheSameRejectedTokenIsNotRetriedAndLeavesItUncached()
    {
        $tokens = $this->tokenSequence('stale', 'stale');
        $client = new MockHttpClient([
            new MockResponse(self::INVALID_TOKEN_BODY, ['http_code' => 403]),
        ], 'https://vault.local/v1/');

        try {
            (new TransitKms($client, $tokens))->encrypt('app', 'hello');
            $this->fail('The 403 should be reported.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('HTTP 403', $e->getMessage());
        }

        $this->assertSame(1, $client->getRequestsCount());
        $this->assertSame(['stale', 'stale'], $tokens->invalidated);
    }

    public function testTheKeyReadOfTheAadPreflightIsRetriedToo()
    {
        $tokens = $this->tokenSequence('expired', 'fresh');
        $client = new MockHttpClient([
            new MockResponse(self::INVALID_TOKEN_BODY, ['http_code' => 403]),
            new MockResponse(json_encode(['data' => ['derived' => false]])),
        ], 'https://vault.local/v1/');

        $this->expectException(UnsupportedOperationException::class);
        (new TransitKms($client, $tokens))->encrypt('app', 'hello', 'opaque-aad');
    }

    public function testRefreshFailurePropagatesAndCancelsTheResponse()
    {
        $error = new RuntimeException('Login failed.');
        $tokens = new class($error) implements TokenProviderInterface {
            private int $calls = 0;

            public function __construct(private readonly RuntimeException $error)
            {
            }

            public function getToken(): string
            {
                if (1 < ++$this->calls) {
                    throw $this->error;
                }

                return 'expired';
            }

            public function invalidateToken(#[\SensitiveParameter] string $token): void
            {
            }
        };
        $canceledResponses = 0;
        $client = (new MockHttpClient([
            new MockResponse(self::INVALID_TOKEN_BODY, ['http_code' => 403]),
        ], 'https://vault.local/v1/'))->withOptions(['on_progress' => static function (int $downloaded, int $downloadSize, array $info) use (&$canceledResponses): void {
            if ($info['canceled'] ?? false) {
                ++$canceledResponses;
            }
        }]);

        try {
            (new TransitKms($client, $tokens))->encrypt('app', 'hello');
            $this->fail('The refresh failure should be reported.');
        } catch (RuntimeException $e) {
            $this->assertSame($error, $e);
        }

        $this->assertSame(1, $canceledResponses);
    }

    public function testTokenProviderTransportFailureIsWrapped()
    {
        $tokens = new class implements TokenProviderInterface {
            public function getToken(): string
            {
                throw new TransportException('Vault unavailable.');
            }

            public function invalidateToken(#[\SensitiveParameter] string $token): void
            {
            }
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to reach Vault.');
        (new TransitKms(new MockHttpClient([], 'https://vault.local/v1/'), $tokens))->encrypt('app', 'hello');
    }

    private function contextBoundKms(): TransitKms
    {
        $ciphertexts = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$ciphertexts): MockResponse {
            if (str_contains($url, '/keys/')) {
                return new MockResponse(json_encode(['data' => ['derived' => true]]));
            }

            $body = json_decode($options['body'], true);
            if (str_contains($url, '/decrypt/')) {
                [$context, $plaintext] = $ciphertexts[$body['ciphertext']];

                return $context === $body['context']
                    ? new MockResponse(json_encode(['data' => ['plaintext' => $plaintext]]))
                    : new MockResponse('{"errors":["cipher: message authentication failed"]}', ['http_code' => 400]);
            }

            $ciphertext = 'vault:v1:'.\count($ciphertexts);
            $plaintext = $body['plaintext'] ?? base64_encode(str_repeat("\xAA", intdiv($body['bits'], 8)));
            $ciphertexts[$ciphertext] = [$body['context'], $plaintext];

            return new MockResponse(json_encode(['data' => ['ciphertext' => $ciphertext, 'plaintext' => $plaintext]]));
        }, 'https://vault.local/v1/');

        return new TransitKms($client, new StaticTokenProvider('t'));
    }

    /**
     * @return TokenProviderInterface&object{invalidated: list<string>}
     */
    private function tokenSequence(string ...$tokens): TokenProviderInterface
    {
        return new class($tokens) implements TokenProviderInterface {
            /** @var list<string> */
            public array $invalidated = [];

            /**
             * @param list<string> $tokens
             */
            public function __construct(private array $tokens)
            {
            }

            public function getToken(): string
            {
                return array_shift($this->tokens) ?? throw new \LogicException('No token left.');
            }

            public function invalidateToken(#[\SensitiveParameter] string $token): void
            {
                $this->invalidated[] = $token;
            }
        };
    }

    /**
     * @param list<string> $headers
     */
    private static function header(array $headers, string $name): ?string
    {
        foreach ($headers as $header) {
            if (str_starts_with($header, $name.': ')) {
                return substr($header, \strlen($name) + 2);
            }
        }

        return null;
    }
}
