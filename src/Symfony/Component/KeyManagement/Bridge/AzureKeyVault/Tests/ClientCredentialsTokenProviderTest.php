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
use Symfony\Component\KeyManagement\Bridge\AzureKeyVault\ClientCredentialsTokenProvider;
use Symfony\Component\KeyManagement\Exception\RuntimeException;

class ClientCredentialsTokenProviderTest extends TestCase
{
    public function testTokenIsFetchedFromTheTenantTokenEndpoint()
    {
        $captured = null;
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured = [$method, $url, $options['body'] ?? null];

            return new MockResponse(json_encode(['access_token' => 'eyJ.token', 'expires_in' => 3600, 'token_type' => 'Bearer']));
        });

        $provider = new ClientCredentialsTokenProvider($client, 'tenant-id', 'client-id', 'client-secret');
        $token = $provider->getToken();

        $this->assertSame('eyJ.token', $token);

        [$method, $url, $body] = $captured;
        $this->assertSame('POST', $method);
        $this->assertSame('https://login.microsoftonline.com/tenant-id/oauth2/v2.0/token', $url);
        parse_str($body, $form);
        $this->assertSame('client_credentials', $form['grant_type']);
        $this->assertSame('client-id', $form['client_id']);
        $this->assertSame('client-secret', $form['client_secret']);
        $this->assertSame('https://vault.azure.net/.default', $form['scope']);
    }

    public function testTokenIsCachedAcrossCalls()
    {
        $calls = 0;
        $client = new MockHttpClient(static function () use (&$calls): MockResponse {
            ++$calls;

            return new MockResponse(json_encode(['access_token' => 'cached', 'expires_in' => 3600]));
        });

        $provider = new ClientCredentialsTokenProvider($client, 't', 'c', 's');
        $provider->getToken();
        $provider->getToken();
        $provider->getToken();

        $this->assertSame(1, $calls);
    }

    public function testOnlyTheMatchingCachedTokenIsInvalidated()
    {
        $calls = 0;
        $client = new MockHttpClient(static function () use (&$calls): MockResponse {
            return new MockResponse(json_encode(['access_token' => 'T'.++$calls, 'expires_in' => 3600]));
        });

        $provider = new ClientCredentialsTokenProvider($client, 't', 'c', 's');
        $provider->invalidateToken('missing');
        $this->assertSame(0, $calls);

        $this->assertSame('T1', $provider->getToken());
        $provider->invalidateToken('older');
        $this->assertSame('T1', $provider->getToken());
        $this->assertSame(1, $calls);

        $provider->invalidateToken('T1');
        $this->assertSame(1, $calls);
        $this->assertSame('T2', $provider->getToken());
        $provider->invalidateToken('T1');
        $this->assertSame('T2', $provider->getToken());
        $this->assertSame(2, $calls);
    }

    public function testHttpErrorSurfacesAsRuntimeException()
    {
        $client = new MockHttpClient(
            new MockResponse(json_encode(['error' => 'invalid_client', 'error_description' => 'AADSTS7000215']), ['http_code' => 401]),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HTTP 401');
        (new ClientCredentialsTokenProvider($client, 't', 'c', 's'))->getToken();
    }

    public function testMalformedResponseSurfacesAsRuntimeException()
    {
        $client = new MockHttpClient(new MockResponse(json_encode(['nope' => 'nope'])));

        $this->expectException(RuntimeException::class);
        (new ClientCredentialsTokenProvider($client, 't', 'c', 's'))->getToken();
    }

    public function testNonJsonErrorBodySurfacesAsRuntimeExceptionWithoutTheBody()
    {
        $client = new MockHttpClient(new MockResponse('<html>load balancer error page</html>', ['http_code' => 503]));

        try {
            (new ClientCredentialsTokenProvider($client, 't', 'c', 's'))->getToken();
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
            (new ClientCredentialsTokenProvider($client, 't', 'c', 's'))->getToken();
            $this->fail('A RuntimeException should have been thrown.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('HTTP 301', $e->getMessage());
            $this->assertStringNotContainsString('<html>', $e->getMessage());
        }
    }

    public function testTransportErrorWhileReadingTheBodySurfacesAsRuntimeException()
    {
        $client = new MockHttpClient(new MockResponse((static function (): \Generator {
            yield '{"acc';
            yield '';
        })()));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HTTP 200');
        (new ClientCredentialsTokenProvider($client, 't', 'c', 's'))->getToken();
    }

    public function testArrayValuedErrorDescriptionIsReportedAsUnknown()
    {
        $client = new MockHttpClient(new MockResponse(json_encode(['error_description' => ['a']]), ['http_code' => 400]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('(HTTP 400): "unknown error"');
        (new ClientCredentialsTokenProvider($client, 't', 'c', 's'))->getToken();
    }

    public function testNumericStringExpiresInIsAccepted()
    {
        $client = new MockHttpClient(new MockResponse(json_encode(['access_token' => 'eyJ.token', 'expires_in' => '3599'])));

        $this->assertSame('eyJ.token', (new ClientCredentialsTokenProvider($client, 't', 'c', 's'))->getToken());
    }
}
