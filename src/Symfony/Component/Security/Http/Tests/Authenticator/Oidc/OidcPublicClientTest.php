<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\Authenticator\Oidc;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcPublicClient;
use Symfony\Component\Security\Http\Oidc\OidcDiscovery;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[AllowMockObjectsWithoutExpectations]
class OidcPublicClientTest extends TestCase
{
    private OidcDiscovery $discovery;
    private HttpClientInterface $httpClient;

    protected function setUp(): void
    {
        $this->discovery = $this->createDiscovery([
            'token_endpoint' => 'https://provider.example.com/token',
            'userinfo_endpoint' => 'https://provider.example.com/userinfo',
            'issuer' => 'https://provider.example.com',
        ]);

        $this->httpClient = $this->createMock(HttpClientInterface::class);
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function createDiscovery(array $configuration): OidcDiscovery
    {
        return new OidcDiscovery(
            new MockHttpClient(static fn (): MockResponse => new JsonMockResponse($configuration)),
            new ArrayAdapter(),
            'https://provider.example.com/.well-known/openid-configuration',
        );
    }

    public function testExchangeCodeSendsNoClientCredentials()
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'access_token' => 'access-123',
            'id_token' => 'id-token-abc',
        ]);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('POST', 'https://provider.example.com/token', $this->callback(function (array $options) {
                $this->assertSame('authorization_code', $options['body']['grant_type']);
                $this->assertSame('auth-code', $options['body']['code']);
                $this->assertSame('https://app.example.com/callback', $options['body']['redirect_uri']);
                // the client_id and the code verifier are all a public client sends
                $this->assertSame('test-client-id', $options['body']['client_id']);
                $this->assertSame('my-code-verifier', $options['body']['code_verifier']);
                $this->assertArrayNotHasKey('client_secret', $options['body']);
                $this->assertArrayNotHasKey('auth_basic', $options);

                return true;
            }))
            ->willReturn($response);

        $client = $this->createClient();
        $tokens = $client->exchangeCode('auth-code', 'https://app.example.com/callback', 'my-code-verifier');

        $this->assertSame('access-123', $tokens['access_token']);
        $this->assertSame('id-token-abc', $tokens['id_token']);
    }

    public function testExchangeCodeRequiresPkce()
    {
        // without a secret and without PKCE, nothing binds the authorization code to
        // this client: an intercepted code could be redeemed by anyone
        $this->httpClient->expects($this->never())->method('request');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('must exchange the authorization code with PKCE');

        $this->createClient()->exchangeCode('auth-code', 'https://app.example.com/callback');
    }

    public function testExchangeCodeRejectsAnEmptyCodeVerifier()
    {
        // an empty verifier would make the exchange look protected while it is not
        $this->httpClient->expects($this->never())->method('request');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('must exchange the authorization code with PKCE');

        $this->createClient()->exchangeCode('auth-code', 'https://app.example.com/callback', '');
    }

    public function testFetchUserInfoUsesTheAccessToken()
    {
        // the UserInfo endpoint is a protected resource: it takes the access token as a
        // bearer credential, so it needs no client authentication at all
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'sub' => '123',
            'email' => 'test@example.com',
        ]);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('GET', 'https://provider.example.com/userinfo', [
                'auth_bearer' => 'access-token',
            ])
            ->willReturn($response);

        $client = $this->createClient();
        $claims = $client->fetchUserInfo('access-token');

        $this->assertSame('123', $claims['sub']);
        $this->assertSame('test@example.com', $claims['email']);
    }

    private function createClient(): OidcPublicClient
    {
        return new OidcPublicClient(
            httpClient: $this->httpClient,
            discovery: $this->discovery,
            clientId: 'test-client-id',
        );
    }
}
