<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Functional;

use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\Signature\Algorithm\ES256;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\CompactSerializer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;
use Symfony\Component\Security\Http\Oidc\OidcLogoutToken;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[RequiresPhpExtension('openssl')]
class OidcBackChannelLogoutTest extends AbstractWebTestCase
{
    private const JWK = [
        'kty' => 'EC',
        'crv' => 'P-256',
        'x' => '0QEAsI1wGI-dmYatdUZoWSRWggLEpyzopuhwk-YUnA4',
        'y' => 'KYl-qyZ26HobuYwlQh-r0iHX61thfP82qqEku7i0woo',
        'd' => 'iA_TV2zvftni_9aFAQwFO_9aypfJFCSpcCyevDvz220',
    ];

    #[DataProvider('provideFirewallContexts')]
    public function testTheLoginOfAnEndedProviderSessionIsDeauthenticatedOnTheNextRequest(string $rootConfig, string $firewallContext)
    {
        $client = $this->createLoggedInClient($rootConfig, $firewallContext, 'session-42');
        $sessionCookie = $client->getCookieJar()->get('MOCKSESSID');

        $client->getCookieJar()->clear();
        $client->request('POST', '/oidc/backchannel-logout', ['logout_token' => $this->buildLogoutToken('session-42')]);

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertStringContainsString('no-store', $client->getResponse()->headers->get('Cache-Control'));

        $client->getCookieJar()->set($sessionCookie);
        $client->request('GET', '/oidc/start');

        $this->assertNull($client->getRequest()->getSession()->get('_security_'.$firewallContext));
        $this->assertNotSame($sessionCookie->getValue(), $client->getCookieJar()->get('MOCKSESSID')?->getValue(), 'The listener of TokenDeauthenticatedEvent invalidates the session on an OidcSessionEndedException.');
    }

    public static function provideFirewallContexts(): iterable
    {
        yield 'own context' => ['config_oidc_backchannel.yml', 'oidc'];
        yield 'context shared with a firewall declared first' => ['config_oidc_backchannel_shared_context.yml', 'shared'];
    }

    /**
     * Two firewalls of one context share the token the session holds, so a login the provider
     * ended must be refused on every one of them, and not only on the firewall it was made on.
     */
    public function testTheLoginIsAlsoRefusedOnAnotherFirewallOfTheContext()
    {
        $client = $this->createLoggedInClient('config_oidc_backchannel_shared_context.yml', 'shared', 'session-42');
        $sessionCookie = $client->getCookieJar()->get('MOCKSESSID');

        $client->getCookieJar()->clear();
        $client->request('POST', '/oidc/backchannel-logout', ['logout_token' => $this->buildLogoutToken('session-42')]);

        $this->assertSame(200, $client->getResponse()->getStatusCode());

        $client->getCookieJar()->set($sessionCookie);
        $client->request('GET', '/admin/protected');

        $this->assertNull($client->getRequest()->getSession()->get('_security_shared'));
    }

    public function testTheLoginOfAnotherProviderSessionIsKept()
    {
        $client = $this->createLoggedInClient('config_oidc_backchannel.yml', 'oidc', 'session-43');
        $sessionCookie = $client->getCookieJar()->get('MOCKSESSID');

        $client->getCookieJar()->clear();
        $client->request('POST', '/oidc/backchannel-logout', ['logout_token' => $this->buildLogoutToken('session-42')]);

        $this->assertSame(200, $client->getResponse()->getStatusCode());

        $client->getCookieJar()->set($sessionCookie);
        $client->request('GET', '/oidc/start');

        $this->assertNotNull($client->getRequest()->getSession()->get('_security_oidc'));
        $this->assertSame($sessionCookie->getValue(), $client->getCookieJar()->get('MOCKSESSID')?->getValue());
    }

    /**
     * The listener runs on every firewall, and still only ever refuses an OIDC login.
     *
     * A login made through another authenticator of the shared context carries no "oidc_sid",
     * so there is nothing for a logout token to name, whatever it says.
     */
    public function testALoginOfAnotherAuthenticatorOfTheContextIsLeftAlone()
    {
        $client = $this->createClient(['test_case' => 'OidcLoginRouteLoader', 'root_config' => 'config_oidc_backchannel_shared_context.yml']);
        $client->disableReboot();
        $client->loginUser(new InMemoryUser('john', 'test', ['ROLE_USER']), 'shared');
        $client->getContainer()->set(HttpClientInterface::class, new MockHttpClient($this->mockProvider()));
        $client->getContainer()->get('security.token_storage')->setToken(null);
        $sessionCookie = $client->getCookieJar()->get('MOCKSESSID');

        $client->getCookieJar()->clear();
        $client->request('POST', '/oidc/backchannel-logout', ['logout_token' => $this->buildLogoutToken('session-42')]);

        $this->assertSame(200, $client->getResponse()->getStatusCode());

        $client->getCookieJar()->set($sessionCookie);
        $client->request('GET', '/admin/protected');

        $this->assertNotNull($client->getRequest()->getSession()->get('_security_shared'));
        $this->assertSame('protected', $client->getResponse()->getContent());
    }

    private function createLoggedInClient(string $rootConfig, string $firewallContext, string $sid): KernelBrowser
    {
        $client = $this->createClient(['test_case' => 'OidcLoginRouteLoader', 'root_config' => $rootConfig]);
        $client->disableReboot();

        // the token names the firewall that minted it, "oidc" in both configurations, whatever
        // the context it is stored under; loginUser() would name it after the context instead
        $user = new InMemoryUser('john', 'test', ['ROLE_USER']);
        $token = new PostAuthenticationToken($user, 'oidc', $user->getRoles());
        $token->setAttribute('oidc_sid', $sid);
        $session = $client->getSession();
        $session->set('_security_'.$firewallContext, serialize($token));
        $session->save();

        $client->getContainer()->set(HttpClientInterface::class, new MockHttpClient($this->mockProvider()));

        return $client;
    }

    private function buildLogoutToken(string $sid): string
    {
        $payload = json_encode([
            'iss' => 'https://accounts.example.com',
            'aud' => 'client_id',
            'iat' => time(),
            'exp' => time() + 120,
            'jti' => bin2hex(random_bytes(8)),
            'events' => [OidcLogoutToken::EVENT => new \stdClass()],
            'sid' => $sid,
        ]);

        return (new CompactSerializer())->serialize(
            (new JWSBuilder(new AlgorithmManager([new ES256()])))
                ->withPayload($payload)
                ->addSignature(new JWK(self::JWK), ['alg' => 'ES256', 'kid' => 'signing-key', 'typ' => 'logout+jwt'])
                ->build()
        );
    }

    private function mockProvider(): \Closure
    {
        return static function (string $method, string $url): MockResponse {
            if (str_contains($url, '/.well-known/openid-configuration')) {
                return new JsonMockResponse([
                    'issuer' => 'https://accounts.example.com',
                    'authorization_endpoint' => 'https://accounts.example.com/authorize',
                    'token_endpoint' => 'https://accounts.example.com/token',
                    'userinfo_endpoint' => 'https://accounts.example.com/userinfo',
                    'jwks_uri' => 'https://accounts.example.com/jwks',
                ]);
            }

            if (str_ends_with($url, '/jwks')) {
                $publicKey = self::JWK;
                unset($publicKey['d']);

                return new JsonMockResponse(['keys' => [$publicKey + ['kid' => 'signing-key', 'use' => 'sig', 'alg' => 'ES256']]]);
            }

            throw new \LogicException(\sprintf('Unexpected request to "%s".', $url));
        };
    }
}
