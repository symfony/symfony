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
use Symfony\Component\KeyManagement\Base64UrlSafe;
use Symfony\Component\KeyManagement\Bridge\GoogleCloudKms\ServiceAccountTokenProvider;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;
use Symfony\Component\KeyManagement\Exception\LogicException;
use Symfony\Component\KeyManagement\Exception\RuntimeException;

class ServiceAccountTokenProviderTest extends TestCase
{
    private static string $privateKeyPem;
    private static string $publicKeyPem;

    public static function setUpBeforeClass(): void
    {
        self::$privateKeyPem = file_get_contents(__DIR__.'/Fixtures/private_key.pem');
        self::$publicKeyPem = file_get_contents(__DIR__.'/Fixtures/public_key.pem');
    }

    public function testTokenIsObtainedByPostingASignedJwtAssertion()
    {
        $captured = null;
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured = [$method, $url, $options['body']];

            return new MockResponse(json_encode(['access_token' => 'ya29.token', 'expires_in' => 3600, 'token_type' => 'Bearer']));
        });

        $provider = new ServiceAccountTokenProvider($client, 'sa@my-proj.iam.gserviceaccount.com', self::$privateKeyPem);
        $this->assertSame('ya29.token', $provider->getToken());

        [$method, $url, $body] = $captured;
        $this->assertSame('POST', $method);
        $this->assertSame('https://oauth2.googleapis.com/token', $url);
        parse_str($body, $form);
        $this->assertSame('urn:ietf:params:oauth:grant-type:jwt-bearer', $form['grant_type']);

        [$header64, $claims64, $signature64] = explode('.', $form['assertion']);
        $header = json_decode(Base64UrlSafe::decode($header64), true);
        $claims = json_decode(Base64UrlSafe::decode($claims64), true);

        $this->assertSame(['alg' => 'RS256', 'typ' => 'JWT'], $header);
        $this->assertSame('sa@my-proj.iam.gserviceaccount.com', $claims['iss']);
        $this->assertSame('https://www.googleapis.com/auth/cloudkms', $claims['scope']);
        $this->assertSame('https://oauth2.googleapis.com/token', $claims['aud']);
        $this->assertGreaterThan(time(), $claims['exp']);
        // "iat" is backdated by 10s so a clock slightly ahead of Google's does not invalidate the JWT.
        $this->assertLessThanOrEqual(time() - 10, $claims['iat']);

        $signature = Base64UrlSafe::decode($signature64);
        $this->assertSame(1, openssl_verify($header64.'.'.$claims64, $signature, self::$publicKeyPem, \OPENSSL_ALGO_SHA256));
    }

    public function testTokenIsCachedAcrossCalls()
    {
        $calls = 0;
        $client = new MockHttpClient(static function () use (&$calls): MockResponse {
            ++$calls;

            return new MockResponse(json_encode(['access_token' => 'cached', 'expires_in' => 3600]));
        });

        $provider = new ServiceAccountTokenProvider($client, 'sa@x.iam.gserviceaccount.com', self::$privateKeyPem);
        $provider->getToken();
        $provider->getToken();
        $provider->getToken();

        $this->assertSame(1, $calls);
    }

    public function testHttpErrorSurfacesAsRuntimeException()
    {
        $client = new MockHttpClient(
            new MockResponse(json_encode(['error' => 'invalid_grant', 'error_description' => 'bad signature']), ['http_code' => 400]),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HTTP 400');
        (new ServiceAccountTokenProvider($client, 'sa@x.iam.gserviceaccount.com', self::$privateKeyPem))->getToken();
    }

    public function testInvalidPrivateKeySurfaces()
    {
        $this->expectException(InvalidArgumentException::class);
        (new ServiceAccountTokenProvider(new MockHttpClient([]), 'sa@x.iam.gserviceaccount.com', 'not a real PEM'))->getToken();
    }

    public function testFromJsonStringRequiresClientEmail()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('client_email');
        ServiceAccountTokenProvider::fromJsonString(new MockHttpClient([]), json_encode(['private_key' => self::$privateKeyPem]));
    }

    public function testFromJsonStringRequiresPrivateKey()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('private_key');
        ServiceAccountTokenProvider::fromJsonString(new MockHttpClient([]), json_encode(['client_email' => 'sa@x']));
    }

    public function testFromJsonFileMissingPathSurfaces()
    {
        $this->expectException(InvalidArgumentException::class);
        ServiceAccountTokenProvider::fromJsonFile(new MockHttpClient([]), '/nope/missing.json');
    }

    public function testFromJsonStringRejectsInvalidJson()
    {
        $this->expectException(InvalidArgumentException::class);
        ServiceAccountTokenProvider::fromJsonString(new MockHttpClient([]), '{not valid');
    }

    public function testNonJsonErrorBodySurfacesAsRuntimeExceptionWithoutTheBody()
    {
        $client = new MockHttpClient(new MockResponse('<html>load balancer error page</html>', ['http_code' => 503]));

        try {
            (new ServiceAccountTokenProvider($client, 'sa@x.iam.gserviceaccount.com', self::$privateKeyPem))->getToken();
            $this->fail('A RuntimeException should have been thrown.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('HTTP 503', $e->getMessage());
            $this->assertStringNotContainsString('<html>', $e->getMessage());
        }
    }

    public function testRedirectResponseSurfacesAsRuntimeException()
    {
        $client = new MockHttpClient(new MockResponse('<html>moved</html>', ['http_code' => 301]));

        try {
            (new ServiceAccountTokenProvider($client, 'sa@x.iam.gserviceaccount.com', self::$privateKeyPem))->getToken();
            $this->fail('A RuntimeException should have been thrown.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('HTTP 301', $e->getMessage());
            $this->assertStringNotContainsString('<html>', $e->getMessage());
        }
    }

    public function testNumericStringExpiresInIsAccepted()
    {
        $client = new MockHttpClient(new MockResponse(json_encode(['access_token' => 'ya29.token', 'expires_in' => '3599'])));

        $this->assertSame('ya29.token', (new ServiceAccountTokenProvider($client, 'sa@x.iam.gserviceaccount.com', self::$privateKeyPem))->getToken());
    }

    public function testNoPrintingToolShowsThePrivateKey()
    {
        $provider = new ServiceAccountTokenProvider(new MockHttpClient([]), 'sa@x.iam.gserviceaccount.com', self::$privateKeyPem);

        ob_start();
        var_dump($provider);
        print_r($provider);
        var_export($provider);
        $printed = ob_get_clean();

        $this->assertStringNotContainsString('PRIVATE KEY', $printed);
    }

    public function testSerializingIsRefused()
    {
        $provider = new ServiceAccountTokenProvider(new MockHttpClient([]), 'sa@x.iam.gserviceaccount.com', self::$privateKeyPem);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('cannot be serialized');

        serialize($provider);
    }
}
