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

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\KeyManagement\Base64UrlSafe;
use Symfony\Component\KeyManagement\Bridge\AzureKeyVault\AzureKeyVault;
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

            return new MockResponse(json_encode(['kid' => 'https://my-vault.vault.azure.net/keys/app/v1', 'value' => 'CipherFromAzure']));
        }, self::VAULT);

        $kms = new AzureKeyVault($client, $this->staticToken('TOKEN'));
        $ciphertext = $kms->encrypt('app-key', 'hello');

        $this->assertSame('app-key', $ciphertext->keyId);
        $this->assertSame('CipherFromAzure', $ciphertext->blob);

        [$method, $url, $body, $headers] = $captured;
        $this->assertSame('POST', $method);
        $this->assertSame(self::VAULT.'keys/app-key/encrypt?api-version=7.4', $url);
        $this->assertSame('RSA-OAEP-256', $body['alg']);
        $this->assertSame(Base64UrlSafe::encode('hello'), $body['value']);
        $this->assertContains('Authorization: Bearer TOKEN', $headers);
    }

    public function testEncryptPinsSpecificKeyVersion()
    {
        $captured = null;
        $client = new MockHttpClient(static function (string $method, string $url) use (&$captured): MockResponse {
            $captured = $url;

            return new MockResponse(json_encode(['value' => 'ct']));
        }, self::VAULT);

        (new AzureKeyVault($client, $this->staticToken('T')))->encrypt('app-key/abc123', 'hello');

        $this->assertSame(self::VAULT.'keys/app-key/abc123/encrypt?api-version=7.4', $captured);
    }

    public function testEncryptRejectsKeyIdWithMoreThanOneSlash()
    {
        $kms = new AzureKeyVault(new MockHttpClient([], self::VAULT), $this->staticToken('T'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"<name>" or "<name>/<version>"');
        $kms->encrypt('app-key/v1/extra', 'hello');
    }

    public function testEncryptRejectsAnEmptyKeyName()
    {
        $kms = new AzureKeyVault(new MockHttpClient([], self::VAULT), $this->staticToken('T'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"<name>" or "<name>/<version>"');
        $kms->encrypt('', 'hello');
    }

    public function testEncryptRejectsAKeyIdWithAnEmptyVersion()
    {
        $kms = new AzureKeyVault(new MockHttpClient([], self::VAULT), $this->staticToken('T'));

        // "app-key/" would build the broken URL "keys/app-key//encrypt".
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"<name>" or "<name>/<version>"');
        $kms->encrypt('app-key/', 'hello');
    }

    public function testEncryptRejectsAadOnRsa()
    {
        $kms = new AzureKeyVault(new MockHttpClient([], self::VAULT), $this->staticToken('T'));

        $this->expectException(UnsupportedOperationException::class);
        $kms->encrypt('app-key', 'hello', 'tenant=acme');
    }

    public function testEncryptRejectsDeterministic()
    {
        $kms = new AzureKeyVault(new MockHttpClient([], self::VAULT), $this->staticToken('T'));

        $this->expectException(UnsupportedOperationException::class);
        $kms->encrypt('app-key', 'hello', deterministic: true);
    }

    public function testEncryptWithAeadBundlesAlgorithmIvAndTagIntoTheBlob()
    {
        $captured = null;
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured = json_decode($options['body'], true);

            return new MockResponse(json_encode([
                'value' => 'CTVAL',
                'iv' => 'IV',
                'tag' => 'TAG',
            ]));
        }, self::VAULT);

        $kms = new AzureKeyVault($client, $this->staticToken('T'), 'A256GCM', 'A256GCM');
        $ciphertext = $kms->encrypt('app-key', 'hello', 'tenant=acme');

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

        $plaintext = (new AzureKeyVault($client, $this->staticToken('T')))->decrypt(new Ciphertext('CipherFromAzure', 'app-key'));

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

        $kms = new AzureKeyVault($client, $this->staticToken('T'), 'A256GCM', 'A256GCM');
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

        // Bridge configured for RSA-OAEP-256 today, but a previous run wrote the blob
        // with A256GCM. Decrypt must read the algorithm from the blob prefix.
        $kms = new AzureKeyVault($client, $this->staticToken('T'));
        $plaintext = $kms->decrypt(new Ciphertext('A256GCM.IV.TAG.CTVAL', 'app-key'));

        $this->assertSame('hello', $plaintext);
        $this->assertSame('A256GCM', $captured['alg']);
        $this->assertSame('IV', $captured['iv']);
    }

    public function testDecryptRejectsAeadShapedBlobWithUnknownAlgorithm()
    {
        $kms = new AzureKeyVault(new MockHttpClient([], self::VAULT), $this->staticToken('T'), 'A256GCM', 'A256GCM');

        // Configured AEAD but the blob has neither dots nor a known prefix.
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

        $kms = new AzureKeyVault($client, $this->staticToken('T'));
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
        (new AzureKeyVault($client, $this->staticToken('T')))->decrypt(new Ciphertext('tampered', 'app-key'));
    }

    public function testDecryptOnHttp404IsAlsoADecryptionFailure()
    {
        $client = new MockHttpClient(
            new MockResponse(json_encode(['error' => ['code' => 'KeyNotFound', 'message' => 'no such key']]), ['http_code' => 404]),
            self::VAULT,
        );

        $this->expectException(DecryptionFailedException::class);
        (new AzureKeyVault($client, $this->staticToken('T')))->decrypt(new Ciphertext('whatever', 'unknown-key'));
    }

    public function testEncryptOnHttp404IsKeyNotFound()
    {
        $client = new MockHttpClient(
            new MockResponse(json_encode(['error' => ['code' => 'KeyNotFound', 'message' => 'no such key']]), ['http_code' => 404]),
            self::VAULT,
        );

        $this->expectException(KeyNotFoundException::class);
        (new AzureKeyVault($client, $this->staticToken('T')))->encrypt('unknown-key', 'hello');
    }

    public function testServerErrorBubblesAsRuntimeException()
    {
        $client = new MockHttpClient(
            new MockResponse(json_encode(['error' => ['code' => 'InternalError', 'message' => 'kaboom']]), ['http_code' => 500]),
            self::VAULT,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HTTP 500');
        (new AzureKeyVault($client, $this->staticToken('T')))->encrypt('app-key', 'hello');
    }

    public function testNonJsonErrorBodySurfacesAsRuntimeExceptionWithoutTheBody()
    {
        $client = new MockHttpClient(
            new MockResponse('<html>load balancer error page</html>', ['http_code' => 503]),
            self::VAULT,
        );

        try {
            (new AzureKeyVault($client, $this->staticToken('T')))->encrypt('app-key', 'hello');
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
            (new AzureKeyVault($client, $this->staticToken('T')))->encrypt('app-key', 'hello');
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

            return new MockResponse(json_encode(['value' => 'WrappedDek']));
        }, self::VAULT);

        $dataKey = (new AzureKeyVault($client, $this->staticToken('T')))->generateDataKey('app-key', 32);

        [$url, $body] = $captured;
        $this->assertSame(self::VAULT.'keys/app-key/wrapkey?api-version=7.4', $url);
        $this->assertSame('RSA-OAEP-256', $body['alg']);
        $this->assertSame('app-key', $dataKey->wrapped->keyId);
        $this->assertSame('WrappedDek', $dataKey->wrapped->blob);
    }

    public function testGenerateDataKeyRejectsTooShortLengths()
    {
        $kms = new AzureKeyVault(new MockHttpClient([], self::VAULT), $this->staticToken('T'));

        $this->expectException(InvalidArgumentException::class);
        $kms->generateDataKey('app-key', 8);
    }

    public function testUnwrapDataKeyDecodesPlaintext()
    {
        $client = new MockHttpClient(
            new MockResponse(json_encode(['value' => Base64UrlSafe::encode(str_repeat("\xAB", 32))])),
            self::VAULT,
        );

        $dataKey = (new AzureKeyVault($client, $this->staticToken('T')))->unwrapDataKey(new Ciphertext('WrappedDek', 'app-key'));

        $this->assertSame(str_repeat("\xAB", 32), $dataKey->use(static fn (string $p): string => $p));
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
        };
    }
}
