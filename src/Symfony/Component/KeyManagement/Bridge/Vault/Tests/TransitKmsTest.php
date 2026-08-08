<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\Vault\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\KeyManagement\Bridge\Vault\TransitKms;
use Symfony\Component\KeyManagement\Ciphertext;
use Symfony\Component\KeyManagement\Exception\DecryptionFailedException;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;
use Symfony\Component\KeyManagement\Exception\KeyNotFoundException;
use Symfony\Component\KeyManagement\Exception\RuntimeException;
use Symfony\Component\KeyManagement\Exception\UnsupportedOperationException;

class TransitKmsTest extends TestCase
{
    public function testEncryptSendsBase64PlaintextAndReturnsTheVaultBlob()
    {
        $captured = null;
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured = [$method, $url, json_decode($options['body'], true), $options['headers']];

            return new MockResponse(json_encode(['data' => ['ciphertext' => 'vault:v1:abc']]));
        }, 'https://vault.local/v1/');

        $kms = new TransitKms($client, 's.token');
        $ciphertext = $kms->encrypt('app', 'hello');

        $this->assertSame('app', $ciphertext->keyId);
        $this->assertSame('vault:v1:abc', $ciphertext->blob);

        [$method, $url, $body, $headers] = $captured;
        $this->assertSame('POST', $method);
        $this->assertSame('https://vault.local/v1/transit/encrypt/app', $url);
        $this->assertSame(['plaintext' => base64_encode('hello')], $body);
        $this->assertContains('X-Vault-Token: s.token', $headers);
    }

    public function testEncryptForwardsAadAsBase64ContextBytes()
    {
        $captured = null;
        $client = new MockHttpClient(static function ($m, $u, $o) use (&$captured) {
            if (str_contains($u, '/keys/')) {
                return new MockResponse(json_encode(['data' => ['derived' => true]]));
            }
            $captured = json_decode($o['body'], true);

            return new MockResponse(json_encode(['data' => ['ciphertext' => 'vault:v1:x']]));
        }, 'https://vault.local/v1/');

        (new TransitKms($client, 't'))->encrypt('app', 'hello', 'opaque-aad');

        $this->assertSame(base64_encode('opaque-aad'), $captured['context']);
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

        $kms = new TransitKms($client, 't');
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
        // Vault silently ignores the context parameter on non-derived keys, so
        // an AAD mismatch would decrypt fine; the bridge must refuse instead.
        $client = new MockHttpClient(
            new MockResponse(json_encode(['data' => ['derived' => false]])),
            'https://vault.local/v1/',
        );

        $this->expectException(UnsupportedOperationException::class);
        $this->expectExceptionMessage('derived');
        (new TransitKms($client, 't'))->encrypt('app', 'hello', 'opaque-aad');
    }

    public function testEmptyAadNeverTriggersTheKeyRead()
    {
        $urls = [];
        $client = new MockHttpClient(static function ($m, $u) use (&$urls) {
            $urls[] = $u;

            return new MockResponse(json_encode(['data' => ['ciphertext' => 'vault:v1:x']]));
        }, 'https://vault.local/v1/');

        (new TransitKms($client, 't'))->encrypt('app', 'hello');

        $this->assertSame(['https://vault.local/v1/transit/encrypt/app'], $urls);
    }

    public function testUnknownKeyOnTheAadCheckSurfacesAsKeyNotFoundOnEncrypt()
    {
        $client = new MockHttpClient(
            new MockResponse('', ['http_code' => 404]),
            'https://vault.local/v1/',
        );

        $this->expectException(KeyNotFoundException::class);
        (new TransitKms($client, 't'))->encrypt('missing', 'hello', 'opaque-aad');
    }

    public function testUnknownKeyOnTheAadCheckStaysMaskedOnDecrypt()
    {
        $client = new MockHttpClient(
            new MockResponse('', ['http_code' => 404]),
            'https://vault.local/v1/',
        );

        $this->expectException(DecryptionFailedException::class);
        (new TransitKms($client, 't'))->decrypt(new Ciphertext('vault:v1:x', 'missing'), 'opaque-aad');
    }

    public function testDecryptDecodesPlaintext()
    {
        $client = new MockHttpClient(
            new MockResponse(json_encode(['data' => ['plaintext' => base64_encode('hello')]])),
            'https://vault.local/v1/',
        );

        $plaintext = (new TransitKms($client, 't'))->decrypt(new Ciphertext('vault:v1:abc', 'app'));

        $this->assertSame('hello', $plaintext);
    }

    public function testDecryptOnHttp400IsADecryptionFailure()
    {
        $client = new MockHttpClient(
            new MockResponse(json_encode(['errors' => ['invalid ciphertext']]), ['http_code' => 400]),
            'https://vault.local/v1/',
        );

        $this->expectException(DecryptionFailedException::class);
        (new TransitKms($client, 't'))->decrypt(new Ciphertext('vault:v1:tampered', 'app'));
    }

    public function testDecryptOnHttp500IsNotASilentDecryptionFailure()
    {
        $client = new MockHttpClient(
            new MockResponse(json_encode(['errors' => ['internal']]), ['http_code' => 500]),
            'https://vault.local/v1/',
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HTTP 500');
        (new TransitKms($client, 't'))->decrypt(new Ciphertext('vault:v1:x', 'app'));
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
        (new TransitKms($client, 't'))->decrypt(new Ciphertext('vault:v1:x', 'app'));
    }

    public function testHttp404OnEncryptSurfacesAsKeyNotFound()
    {
        $client = new MockHttpClient(
            new MockResponse('', ['http_code' => 404]),
            'https://vault.local/v1/',
        );

        $this->expectException(KeyNotFoundException::class);
        (new TransitKms($client, 't'))->encrypt('missing', 'hello');
    }

    public function testHttp404OnDecryptIsMaskedAsDecryptionFailure()
    {
        $client = new MockHttpClient(
            new MockResponse('', ['http_code' => 404]),
            'https://vault.local/v1/',
        );

        // Distinguishing "unknown key id" from "wrong ciphertext" would be a
        // key-enumeration oracle.
        $this->expectException(DecryptionFailedException::class);
        (new TransitKms($client, 't'))->decrypt(new Ciphertext('vault:v1:x', 'missing'));
    }

    public function testNonJsonErrorBodySurfacesAsRuntimeExceptionWithoutTheBody()
    {
        $client = new MockHttpClient(
            new MockResponse('<html>load balancer error page</html>', ['http_code' => 503]),
            'https://vault.local/v1/',
        );

        try {
            (new TransitKms($client, 't'))->encrypt('app', 'hello');
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
            (new TransitKms($client, 't'))->encrypt('app', 'hello');
            $this->fail('A RuntimeException should have been thrown.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('HTTP 301', $e->getMessage());
            $this->assertStringNotContainsString('<html>', $e->getMessage());
        }
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

        $dataKey = (new TransitKms($client, 't'))->generateDataKey('app', 32);

        [$url, $body] = $captured;
        $this->assertSame('https://vault.local/v1/transit/datakey/plaintext/app', $url);
        $this->assertSame(['bits' => 256], $body);
        $this->assertSame('vault:v1:wrapped-dek', $dataKey->wrapped->blob);

        $extracted = $dataKey->use(static fn (string $p): string => $p);
        $this->assertSame(str_repeat("\xAA", 32), $extracted);
    }

    public function testGenerateDataKeyRejectsUnsupportedLengths()
    {
        $kms = new TransitKms(new MockHttpClient([], 'https://vault.local/v1/'), 't');

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

        (new TransitKms($client, 't'))->generateDataKey('app', $byteLength);

        $this->assertSame($expectedBits, $captured['bits']);
    }

    public function testUnwrapDataKeyRoutesThroughDecrypt()
    {
        $captured = null;
        $client = new MockHttpClient(static function ($m, $u, $o) use (&$captured) {
            $captured = $u;

            return new MockResponse(json_encode(['data' => ['plaintext' => base64_encode(str_repeat("\xCC", 32))]]));
        }, 'https://vault.local/v1/');

        $dataKey = (new TransitKms($client, 't'))->unwrapDataKey(new Ciphertext('vault:v1:wrapped', 'app'));

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

        $kms = new TransitKms($client, 't', 'kms-transit', 'tenant-acme');
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

        // A mount point like "kms/transit" must not be percent-encoded as a whole,
        // otherwise the slash becomes %2F and Vault returns 404.
        (new TransitKms($client, 't', 'kms/transit'))->encrypt('app', 'hello');

        $this->assertSame('https://vault.local/v1/kms/transit/encrypt/app', $captured);
    }

    public function testKeyIdIsUrlEncoded()
    {
        $captured = null;
        $client = new MockHttpClient(static function ($m, $u) use (&$captured) {
            $captured = $u;

            return new MockResponse(json_encode(['data' => ['ciphertext' => 'vault:v1:x']]));
        }, 'https://vault.local/v1/');

        (new TransitKms($client, 't'))->encrypt('keys/with/slashes', 'hello');

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
        (new TransitKms($client, 't'))->encrypt('app', 'hello');
    }

    public function testDeterministicModeIsRejected()
    {
        $client = new MockHttpClient([], 'https://vault.local/v1/');

        $this->expectException(UnsupportedOperationException::class);
        (new TransitKms($client, 't'))->encrypt('app', 'hello', deterministic: true);
    }
}
