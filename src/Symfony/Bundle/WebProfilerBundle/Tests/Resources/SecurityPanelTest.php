<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\WebProfilerBundle\Tests\Resources;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\DataCollector\SecurityDataCollector;
use Symfony\Bundle\SecurityBundle\Security\FirewallConfig;
use Symfony\Bundle\SecurityBundle\Security\FirewallMap;
use Symfony\Bundle\WebProfilerBundle\Profiler\CodeExtension;
use Symfony\Bundle\WebProfilerBundle\Twig\WebProfilerExtension;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Dumper\MermaidDumper;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Authenticator\Debug\OidcLoginInspector;
use Symfony\Component\Security\Http\Authenticator\Debug\TraceableOidcClient;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcClientInterface;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcSignatureVerifier;
use Symfony\Component\Security\Http\Oidc\OidcDiscovery;
use Symfony\Contracts\Cache\ItemInterface;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

class SecurityPanelTest extends TestCase
{
    private const ID_TOKEN = 'eyJhbGciOiJSUzI1NiIsImtpZCI6ImtleS0xIn0.eyJzdWIiOiJ1c2VyLTQyIiwiZXhwIjoxNzAwMDAwMDAwfQ.raw-signature';
    private const DOCUMENT = [
        'issuer' => 'https://provider.example.com',
        'authorization_endpoint' => 'https://provider.example.com/authorize',
        'token_endpoint' => 'https://provider.example.com/token',
        'userinfo_endpoint' => 'https://provider.example.com/userinfo',
        'jwks_uri' => 'https://provider.example.com/jwks',
        'code_challenge_methods_supported' => ['S256'],
        'authorization_response_iss_parameter_supported' => true,
    ];

    protected function setUp(): void
    {
        foreach ([OidcLoginInspector::class, ArrayAdapter::class, MockClock::class, MockHttpClient::class] as $class) {
            if (!class_exists($class)) {
                $this->markTestSkipped(\sprintf('The "%s" class is not available.', $class));
            }
        }
        if (!is_dir(self::securityViews())) {
            $this->markTestSkipped('The views of the SecurityBundle are not available.');
        }
    }

    public function testPanelDescribesTheOidcLoginWithoutItsCredentials()
    {
        $panel = $this->renderPanel($this->createCollector(true));

        $this->assertStringContainsString('OpenID Connect', $panel);
        $this->assertStringContainsString('https://provider.example.com/.well-known/openid-configuration', $panel);
        $this->assertStringContainsString('https://provider.example.com/token', $panel);
        $this->assertStringContainsString('client_secret_basic', $panel);
        $this->assertStringContainsString('userinfo', $panel);
        $this->assertStringContainsString('the provider announces no &quot;end_session_endpoint&quot;', $panel);
        $this->assertStringContainsString('JWT, fingerprint <code>'.substr(hash('sha256', self::ID_TOKEN), 0, 8).'</code>', $panel);
        $this->assertStringContainsString('<code>Bearer</code> token, opaque, fingerprint <code>'.substr(hash('sha256', 'raw-access-token'), 0, 8).'</code>: only the provider can read it', $panel);
        $this->assertStringContainsString('renewed on the next request', $panel);
        $this->assertStringContainsString('urn:example:gold', $panel);
        $this->assertStringContainsString('pwd, otp', $panel);

        // the tokens are described, never shown, in every tab of the panel
        $this->assertStringNotContainsString('raw-', $panel);
        $this->assertStringNotContainsString(self::ID_TOKEN, $panel);
        $this->assertStringContainsString('******', $panel);
    }

    public function testPanelTellsWhenTheFirewallHasNoOidcLogin()
    {
        $panel = $this->renderPanel($this->createCollector(false));

        $this->assertStringContainsString('OpenID Connect', $panel);
        $this->assertStringContainsString('has no <code>oidc_login</code> authenticator', $panel);
    }

    public function testPanelRendersTheCallsAndTheSigningKeys()
    {
        // a failed code exchange, with the error fields of the provider, then a UserInfo call
        $httpException = null;
        try {
            (new MockHttpClient(new JsonMockResponse(['error' => 'invalid_grant', 'error_description' => 'Code not valid'], ['http_code' => 400])))->request('POST', 'https://provider.example.com/token')->getHeaders();
        } catch (ClientException $httpException) {
        }
        $client = $this->createStub(OidcClientInterface::class);
        $client->method('getClientAuthenticationMethod')->willReturn('client_secret_basic');
        $client->method('exchangeCode')->willThrowException(new \Symfony\Component\Security\Core\Exception\AuthenticationException('The OIDC token endpoint request failed.', previous: $httpException));
        $client->method('fetchUserInfo')->willReturn(['sub' => 'user-42', 'email' => 'user@example.com']);
        $traceable = new TraceableOidcClient($client);
        try {
            $traceable->exchangeCode('raw-code', 'https://app.example.com/callback', 'raw-verifier');
        } catch (\Symfony\Component\Security\Core\Exception\AuthenticationException) {
        }
        $traceable->fetchUserInfo('raw-access-token');

        $jwksCache = new ArrayAdapter();
        $jwksCache->get('oidc_jwks.'.hash('xxh128', 'https://provider.example.com/jwks'), static fn (ItemInterface $item): array => [
            'keys' => [['kid' => 'key-1', 'kty' => 'RSA', 'alg' => 'RS256', 'use' => 'sig', 'n' => 'public-modulus']],
            'fetched_at' => time() - 60,
        ]);

        $panel = $this->renderPanel($this->createCollector(true, $traceable, $jwksCache));

        $this->assertStringContainsString('authorization_code', $panel);
        $this->assertStringContainsString('failure', $panel);
        $this->assertStringContainsString('HTTP 400', $panel);
        $this->assertStringContainsString('invalid_grant', $panel);
        $this->assertStringContainsString('Code not valid', $panel);
        $this->assertStringContainsString('userinfo', $panel);
        $this->assertStringContainsString('user@example.com', $panel);
        $this->assertStringContainsString('key-1', $panel);
        $this->assertStringContainsString('ID token of the user', $panel);
        $this->assertStringNotContainsString('public-modulus', $panel);
        $this->assertStringNotContainsString('raw-', $panel);
    }

    public function testPanelRendersTheErrorsOfTheInspector()
    {
        // a pool that cannot be read, and a security token that cannot be read either
        $pool = new class extends ArrayAdapter {
            public function getItem(mixed $key): \Symfony\Component\Cache\CacheItem
            {
                throw new \RuntimeException('The pool is unreachable.');
            }
        };
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUserIdentifier')->willReturn('user-42');
        $token->method('getRoleNames')->willReturn([]);
        $token->method('hasAttribute')->willReturn(true);
        $token->method('getAttribute')->willThrowException(new \RuntimeException('The token is unreadable.'));

        $panel = $this->renderPanel($this->createCollector(true, null, null, $pool, $token));

        $this->assertStringContainsString('The discovery document could not be read: The pool is unreachable.', $panel);
        $this->assertStringContainsString('The tokens could not be read: The token is unreadable.', $panel);
    }

    private function createCollector(bool $withOidcLogin, ?TraceableOidcClient $traceable = null, ?ArrayAdapter $jwksCache = null, ?ArrayAdapter $discoveryCache = null, ?TokenInterface $token = null): SecurityDataCollector
    {
        if (null === $token) {
            $token = new UsernamePasswordToken(new InMemoryUser('user-42', null, ['ROLE_USER']), 'oidc', ['ROLE_USER']);
            $token->setAttribute('oidc_id_token', self::ID_TOKEN);
            $token->setAttribute('oidc_access_token', 'raw-access-token');
            $token->setAttribute('oidc_access_token_type', 'Bearer');
            $token->setAttribute('oidc_refresh_token', 'raw-refresh-token');
            $token->setAttribute('oidc_access_token_expires_at', time() + 10);
            $token->setAttribute('oidc_acr', 'urn:example:gold');
            $token->setAttribute('oidc_amr', ['pwd', 'otp']);
        }
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken($token);

        $firewallConfig = new FirewallConfig('oidc', 'security.user_checker', authenticators: ['oidc_login']);
        $firewallMap = $this->createStub(FirewallMap::class);
        $firewallMap->method('getFirewallConfig')->willReturn($firewallConfig);

        $inspectors = [];
        if ($withOidcLogin) {
            $discovery = new OidcDiscovery(new MockHttpClient(new JsonMockResponse(self::DOCUMENT)), $discoveryCache ?? new ArrayAdapter(), 'https://provider.example.com/.well-known/openid-configuration', 'https://provider.example.com');
            if (null === $discoveryCache) {
                $discovery->getConfiguration();
            }
            $verifier = null !== $jwksCache ? new OidcSignatureVerifier($discovery, $jwksCache, new MockHttpClient(), ['RS256'], 3600, true, new MockClock()) : null;
            if (null === $traceable) {
                $client = $this->createStub(OidcClientInterface::class);
                $client->method('getClientAuthenticationMethod')->willReturn('client_secret_basic');
                $traceable = new TraceableOidcClient($client);
            }
            $config = [
                'provider_uri' => 'https://provider.example.com',
                'http_client' => 'http_client',
                'client_id' => 'my-client',
                'scope' => ['openid', 'profile'],
                'check_path' => '/oidc/callback',
                'start_path' => '/oidc/start',
                'pkce' => ['enabled' => true, 'method' => 'S256'],
                'user_data_source' => 'userinfo',
                'user_identifier_claim' => 'sub',
                'id_token_signature' => ['required' => true, 'algorithms' => ['RS256'], 'enforce_key_usage_verification' => true],
                'allowed_time_drift' => 0,
                'max_age' => null,
                'discovery_cache_ttl' => 3600,
                'refresh_access_token' => ['enabled' => true, 'leeway' => 30],
                'enable_end_session' => true,
                'post_logout_redirect_path' => '/',
                'authorization_params' => ['prompt' => 'consent'],
            ];
            $inspectors['oidc'] = static fn () => new OidcLoginInspector('oidc', $discovery, $verifier, $traceable, $config, new MockClock());
        }

        $collector = new SecurityDataCollector($tokenStorage, null, null, null, $firewallMap, null, null, new MermaidDumper(), new ServiceLocator($inspectors));
        $collector->collect(new Request(), new Response());
        $collector->lateCollect();

        // the profiler renders the panel from the stored profile, long after the request
        return unserialize(serialize($collector));
    }

    private function renderPanel(SecurityDataCollector $collector): string
    {
        $loader = new FilesystemLoader();
        $loader->addPath(\dirname(__DIR__, 2).'/Resources/views', 'WebProfiler');
        $loader->addPath(self::securityViews(), 'Security');

        $twig = new Environment($loader, ['strict_variables' => true]);
        $twig->addExtension(new WebProfilerExtension());
        $twig->addExtension(new CodeExtension('', '', 'UTF-8'));
        $twig->addFunction(new TwigFunction('path', static fn (string $name): string => '/_profiler/'.$name));

        return $twig
            ->load('@Security/Collector/security.html.twig')
            ->renderBlock('panel', ['collector' => $collector])
        ;
    }

    private static function securityViews(): string
    {
        return \dirname(__DIR__, 3).'/SecurityBundle/Resources/views';
    }
}
