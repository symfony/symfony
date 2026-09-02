<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Component\Security\Http\EventListener\ClearSiteDataLogoutListener;
use Symfony\Component\Security\Http\EventListener\DefaultLogoutListener;
use Symfony\Component\Security\Http\EventListener\OidcEndSessionListener;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Component\Security\Http\Oidc\OidcDiscovery;

class OidcEndSessionListenerTest extends TestCase
{
    public function testOnLogoutRedirectsToEndSessionEndpoint()
    {
        $discovery = $this->createDiscovery([
            'end_session_endpoint' => 'https://provider.example.com/logout',
        ]);

        $listener = new OidcEndSessionListener($discovery, new HttpUtils(), '/');

        $token = $this->createStub(TokenInterface::class);
        $token->method('hasAttribute')->willReturn(true);
        $token->method('getAttribute')->willReturn('my-id-token');

        $event = new LogoutEvent(Request::create('/logout'), $token);
        $listener->onLogout($event);

        $response = $event->getResponse();
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringStartsWith('https://provider.example.com/logout?', $response->getTargetUrl());
        $this->assertStringContainsString('id_token_hint=my-id-token', $response->getTargetUrl());
    }

    public function testOnLogoutIncludesPostLogoutRedirectUri()
    {
        $discovery = $this->createDiscovery([
            'end_session_endpoint' => 'https://provider.example.com/logout',
        ]);

        $listener = new OidcEndSessionListener($discovery, new HttpUtils(), '/logged-out');

        $token = $this->createStub(TokenInterface::class);
        $token->method('hasAttribute')->willReturn(true);
        $token->method('getAttribute')->willReturn('my-id-token');

        $event = new LogoutEvent(Request::create('/logout'), $token);
        $listener->onLogout($event);

        $url = $event->getResponse()->getTargetUrl();
        $this->assertStringContainsString('post_logout_redirect_uri=', $url);
        $this->assertStringContainsString('logged-out', $url);
    }

    public function testOnLogoutWithoutPostLogoutRedirectPath()
    {
        $discovery = $this->createDiscovery([
            'end_session_endpoint' => 'https://provider.example.com/logout',
        ]);

        $listener = new OidcEndSessionListener($discovery, new HttpUtils());

        $token = $this->createStub(TokenInterface::class);
        $token->method('hasAttribute')->willReturn(true);
        $token->method('getAttribute')->willReturn('my-id-token');

        $event = new LogoutEvent(Request::create('/logout'), $token);
        $listener->onLogout($event);

        $url = $event->getResponse()->getTargetUrl();
        $this->assertStringContainsString('id_token_hint=my-id-token', $url);
        $this->assertStringNotContainsString('post_logout_redirect_uri', $url);
    }

    public function testOnLogoutFallsBackToLocalLogoutWhenEndSessionEndpointMissing()
    {
        $discovery = $this->createDiscovery([
            'issuer' => 'https://provider.example.com',
        ]);

        $listener = new OidcEndSessionListener($discovery, new HttpUtils(), '/');

        $token = $this->createStub(TokenInterface::class);
        $token->method('hasAttribute')->willReturn(true);
        $token->method('getAttribute')->willReturn('my-id-token');

        $event = new LogoutEvent(Request::create('/logout'), $token);

        $listener->onLogout($event);

        $this->assertNull($event->getResponse());
    }

    public function testOnLogoutDoesNothingWithoutOidcIdToken()
    {
        $discovery = $this->createNeverQueriedDiscovery();

        $listener = new OidcEndSessionListener($discovery, new HttpUtils(), '/');

        $token = $this->createStub(TokenInterface::class);
        $token->method('hasAttribute')->willReturn(false);

        $event = new LogoutEvent(Request::create('/logout'), $token);
        $listener->onLogout($event);

        $this->assertNull($event->getResponse());
    }

    public function testOnLogoutDoesNothingWithoutToken()
    {
        $discovery = $this->createNeverQueriedDiscovery();

        $listener = new OidcEndSessionListener($discovery, new HttpUtils(), '/');

        $event = new LogoutEvent(Request::create('/logout'), null);
        $listener->onLogout($event);

        $this->assertNull($event->getResponse());
    }

    public function testOnLogoutRespectsAResponseSetByAnEarlierListener()
    {
        // whoever sets the logout response first wins, as for the default logout
        // response, so a listener customizing it keeps working with RP logout enabled
        $listener = new OidcEndSessionListener($this->createNeverQueriedDiscovery(), new HttpUtils(), '/');

        $token = $this->createStub(TokenInterface::class);
        $token->method('hasAttribute')->willReturn(true);
        $token->method('getAttribute')->willReturn('my-id-token');

        $event = new LogoutEvent(Request::create('/logout'), $token);
        $custom = new Response('custom goodbye page');
        $event->setResponse($custom);
        $listener->onLogout($event);

        $this->assertSame($custom, $event->getResponse());
    }

    public function testOnLogoutReplacesTheDefaultLogoutResponse()
    {
        $discovery = $this->createDiscovery([
            'end_session_endpoint' => 'https://provider.example.com/logout',
        ]);

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new DefaultLogoutListener(new HttpUtils(), '/after-logout'));
        $dispatcher->addSubscriber(new ClearSiteDataLogoutListener(['cookies']));
        $dispatcher->addSubscriber(new OidcEndSessionListener($discovery, new HttpUtils(), '/'));

        $token = $this->createStub(TokenInterface::class);
        $token->method('hasAttribute')->willReturn(true);
        $token->method('getAttribute')->willReturn('my-id-token');

        $event = new LogoutEvent(Request::create('/logout'), $token);
        $dispatcher->dispatch($event);

        $response = $event->getResponse();
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringStartsWith('https://provider.example.com/logout?', $response->getTargetUrl());
        // the listeners decorating the response, like Clear-Site-Data, act on the final one
        $this->assertTrue($response->headers->has('Clear-Site-Data'));
    }

    public function testOnLogoutKeepsTheQueryStringOfTheEndSessionEndpoint()
    {
        $discovery = $this->createDiscovery([
            'end_session_endpoint' => 'https://provider.example.com/logout?tenant=acme',
        ]);

        $listener = new OidcEndSessionListener($discovery, new HttpUtils(), '/');

        $token = $this->createStub(TokenInterface::class);
        $token->method('hasAttribute')->willReturn(true);
        $token->method('getAttribute')->willReturn('my-id-token');

        $event = new LogoutEvent(Request::create('/logout'), $token);
        $listener->onLogout($event);

        $url = $event->getResponse()->getTargetUrl();
        $this->assertStringStartsWith('https://provider.example.com/logout?tenant=acme&id_token_hint=', $url);
    }

    public function testOnLogoutFallsBackToLocalLogoutWhenEndSessionEndpointIsNotHttps()
    {
        $discovery = $this->createDiscovery([
            'end_session_endpoint' => 'http://provider.example.com/logout',
        ]);

        $listener = new OidcEndSessionListener($discovery, new HttpUtils(), '/');

        $token = $this->createStub(TokenInterface::class);
        $token->method('hasAttribute')->willReturn(true);
        $token->method('getAttribute')->willReturn('my-id-token');

        $event = new LogoutEvent(Request::create('/logout'), $token);
        $listener->onLogout($event);

        $this->assertNull($event->getResponse());
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

    /**
     * A discovery whose HTTP client fails the test if the document is ever fetched.
     */
    private function createNeverQueriedDiscovery(): OidcDiscovery
    {
        return new OidcDiscovery(
            new MockHttpClient(static fn (): MockResponse => throw new \LogicException('The discovery document must not be fetched.')),
            new ArrayAdapter(),
            'https://provider.example.com/.well-known/openid-configuration',
        );
    }
}
