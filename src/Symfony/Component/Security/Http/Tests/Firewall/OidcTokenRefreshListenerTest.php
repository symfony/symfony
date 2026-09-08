<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\Firewall;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcClient;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcIdToken;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcTokenRefresher;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;
use Symfony\Component\Security\Http\Firewall\OidcTokenRefreshListener;
use Symfony\Component\Security\Http\OAuth2\ClientAuthentication\ClientSecretPost;
use Symfony\Component\Security\Http\Oidc\OidcDiscovery;

class OidcTokenRefreshListenerTest extends TestCase
{
    private MockClock $clock;
    private TokenStorage $tokenStorage;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-09-06 12:00:00');
        $this->tokenStorage = new TokenStorage();
    }

    /**
     * A lazy firewall calls supports() before the security token is restored, and drops
     * every listener that answers false: anything but null evicts this one for good.
     */
    public function testSupportsDefersToAuthenticateSoLazyFirewallsKeepTheListener()
    {
        $this->tokenStorage->setToken($this->createToken());

        $this->assertNull($this->createListener(new JsonMockResponse(['access_token' => 'access-456']))->supports(new Request()));
    }

    public function testDoesNothingWithoutASecurityToken()
    {
        $this->createListener(new JsonMockResponse(['access_token' => 'access-456']))->authenticate($this->createRequestEvent());

        $this->assertNull($this->tokenStorage->getToken());
    }

    public function testDoesNothingWhenTheSecurityTokenCarriesNoRefreshToken()
    {
        $this->tokenStorage->setToken($token = $this->createToken(['oidc_refresh_token' => null]));

        $this->createListener(new JsonMockResponse(['access_token' => 'access-456']))->authenticate($this->createRequestEvent());

        $this->assertSame('access-123', $token->getAttribute('oidc_access_token'));
    }

    public function testRenewsTheExpiredAccessToken()
    {
        $this->tokenStorage->setToken($token = $this->createToken());

        $this->createListener(new JsonMockResponse(['access_token' => 'access-456', 'expires_in' => 300]))->authenticate($this->createRequestEvent());

        $this->assertSame($token, $this->tokenStorage->getToken());
        $this->assertSame('access-456', $token->getAttribute('oidc_access_token'));
    }

    public function testEndsTheSessionWhenTheProviderRejectsTheRefreshToken()
    {
        // the refresh token is gone for good, so the login this application kept can no
        // longer be backed by the provider session it came from
        $this->tokenStorage->setToken($this->createToken());

        $this->createListener(new JsonMockResponse(['error' => 'invalid_grant'], ['http_code' => 400]))->authenticate($this->createRequestEvent());

        $this->assertNull($this->tokenStorage->getToken());
    }

    public function testKeepsTheSessionWhenTheProviderCannotBeReached()
    {
        // an unreachable provider says nothing about the refresh token, so logging the
        // user out here would turn a provider outage into a mass logout
        $this->tokenStorage->setToken($token = $this->createToken());

        $this->createListener(new MockResponse('Service Unavailable', ['http_code' => 503]))->authenticate($this->createRequestEvent());

        $this->assertSame($token, $this->tokenStorage->getToken());
        $this->assertSame('access-123', $token->getAttribute('oidc_access_token'));
    }

    public function testLeavesAValidAccessTokenAlone()
    {
        $this->tokenStorage->setToken($token = $this->createToken(['oidc_access_token_expires_at' => $this->clock->now()->getTimestamp() + 300]));

        $this->createListener(new JsonMockResponse(['access_token' => 'access-456']))->authenticate($this->createRequestEvent());

        $this->assertSame('access-123', $token->getAttribute('oidc_access_token'));
    }

    private function createRequestEvent(): RequestEvent
    {
        return new RequestEvent($this->createStub(HttpKernelInterface::class), new Request(), HttpKernelInterface::MAIN_REQUEST);
    }

    private function createListener(MockResponse $tokenEndpointResponse): OidcTokenRefreshListener
    {
        $discovery = new OidcDiscovery(
            new MockHttpClient(static fn (): MockResponse => new JsonMockResponse([
                'issuer' => 'https://provider.example.com',
                'token_endpoint' => 'https://provider.example.com/token',
            ])),
            new ArrayAdapter(),
            'https://provider.example.com/.well-known/openid-configuration',
        );

        return new OidcTokenRefreshListener(
            $this->tokenStorage,
            new OidcTokenRefresher(
                new OidcClient(new MockHttpClient($tokenEndpointResponse), $discovery, 'test-client-id', new ClientSecretPost('test-client-secret')),
                $discovery,
                new OidcIdToken($this->clock),
                'test-client-id',
                clock: $this->clock,
            ),
        );
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createToken(array $attributes = []): TokenInterface
    {
        $token = new PostAuthenticationToken(new InMemoryUser('user-42', null), 'main', ['ROLE_USER']);
        $token->setAttributes(array_merge([
            'oidc_access_token' => 'access-123',
            'oidc_refresh_token' => 'refresh-123',
            'oidc_access_token_expires_at' => $this->clock->now()->getTimestamp() - 1,
        ], $attributes));

        return $token;
    }
}
