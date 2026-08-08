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

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\KeyManagement\Bridge\GoogleCloudKms\GoogleCloudKms;
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
