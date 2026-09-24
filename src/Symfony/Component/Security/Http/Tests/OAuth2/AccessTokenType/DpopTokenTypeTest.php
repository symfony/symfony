<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\OAuth2\AccessTokenType;

use Jose\Component\Core\JWK;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\OAuth2\AccessTokenType\DpopTokenType;
use Symfony\Component\Security\Http\OAuth2\Dpop\DpopProofFactory;
use Symfony\Contracts\HttpClient\ResponseInterface;

class DpopTokenTypeTest extends TestCase
{
    private const JWK = [
        'kty' => 'EC',
        'crv' => 'P-256',
        'x' => '0QEAsI1wGI-dmYatdUZoWSRWggLEpyzopuhwk-YUnA4',
        'y' => 'KYl-qyZ26HobuYwlQh-r0iHX61thfP82qqEku7i0woo',
        'd' => 'iA_TV2zvftni_9aFAQwFO_9aypfJFCSpcCyevDvz220',
    ];

    public function testReportsItsTokenType()
    {
        $this->assertSame('DPoP', $this->createTokenType()->getTokenType());
    }

    /**
     * RFC 9449, Section 7.1: a bound token is presented under the "DPoP" scheme, and the
     * proof of that very request travels beside it.
     */
    public function testPresentsTheTokenUnderItsOwnScheme()
    {
        $options = $this->createTokenType()->presentToken('access-123', 'GET', 'https://provider.example.com/userinfo?scope=x', []);

        $this->assertSame('DPoP access-123', $options['headers']['Authorization']);
        $proof = self::decodePayload($options['headers']['DPoP']);
        $this->assertSame('GET', $proof['htm']);
        $this->assertSame('https://provider.example.com/userinfo', $proof['htu']);
        $this->assertSame(rtrim(strtr(base64_encode(hash('sha256', 'access-123', true)), '+/', '-_'), '='), $proof['ath']);
    }

    /**
     * Section 5: the token request carries a proof although no access token exists yet, which
     * is how the provider learns the key to bind the token it is about to issue to.
     */
    public function testSignsATokenRequestWithoutAnAccessToken()
    {
        $options = $this->createTokenType()->prepareTokenRequest('https://provider.example.com/token', ['body' => ['grant_type' => 'authorization_code']]);

        $proof = self::decodePayload($options['headers']['DPoP']);
        $this->assertSame('POST', $proof['htm']);
        $this->assertArrayNotHasKey('ath', $proof);
        $this->assertSame(['grant_type' => 'authorization_code'], $options['body']);
    }

    public function testAcceptsATokenTheProviderBound()
    {
        $this->expectNotToPerformAssertions();

        $this->createTokenType()->checkTokenResponse(['access_token' => 'access-123', 'token_type' => 'DPoP']);
    }

    public function testAcceptsTheTokenTypeInAnyCase()
    {
        $this->expectNotToPerformAssertions();

        $this->createTokenType()->checkTokenResponse(['access_token' => 'access-123', 'token_type' => 'dpop']);
    }

    /**
     * Section 5: a provider may issue a token it did not bind, saying so with "Bearer", and
     * "the client MUST discard the response [...] if this protection is deemed important for
     * the security of the application".
     *
     * Holding a key and signing for every request is what deems it important.
     *
     * @param array<string, mixed> $tokenResponse
     */
    #[DataProvider('provideUnboundTokenResponses')]
    public function testRefusesATokenTheProviderDidNotBind(array $tokenResponse)
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('it did not bind the access token to the key of this client (RFC 9449, Section 5)');

        $this->createTokenType()->checkTokenResponse($tokenResponse);
    }

    public static function provideUnboundTokenResponses(): iterable
    {
        yield 'a bearer token' => [['access_token' => 'access-123', 'token_type' => 'Bearer']];
        yield 'no token type at all' => [['access_token' => 'access-123']];
        yield 'a token type that is no string' => [['access_token' => 'access-123', 'token_type' => 1]];
    }

    /**
     * Section 8: a server may refuse a proof until it carries a nonce it named itself.
     */
    public function testARefusalNamingANonceAsksForTheRequestAgain()
    {
        $tokenType = $this->createTokenType();

        $this->assertTrue($tokenType->onResponse(self::refusalNaming('nonce-1'), 'https://provider.example.com/token'));
        $proof = self::decodePayload($tokenType->prepareTokenRequest('https://provider.example.com/token', [])['headers']['DPoP']);
        $this->assertSame('nonce-1', $proof['nonce']);
    }

    /**
     * A challenge is where a protected resource says it, rather than a JSON error.
     */
    public function testAChallengeNamingANonceAsksForTheRequestAgain()
    {
        $response = self::respond(new MockResponse('', [
            'http_code' => 401,
            'response_headers' => ['WWW-Authenticate' => 'DPoP error="use_dpop_nonce"', 'DPoP-Nonce' => 'nonce-1'],
        ]));

        $this->assertTrue($this->createTokenType()->onResponse($response, 'https://resource.example.com/userinfo'));
    }

    /**
     * Sending the same proof again would be refused for the same reason.
     */
    public function testARequestIsNotRepeatedWhenTheServerNamesNoNewNonce()
    {
        $tokenType = $this->createTokenType();
        $url = 'https://provider.example.com/token';

        $this->assertTrue($tokenType->onResponse(self::refusalNaming('nonce-1'), $url));
        $this->assertFalse($tokenType->onResponse(self::refusalNaming('nonce-1'), $url));
    }

    public function testASuccessfulResponseAsksForNothing()
    {
        $response = self::respond(new MockResponse(json_encode(['access_token' => 'access-123']), ['response_headers' => ['DPoP-Nonce' => 'nonce-1']]));

        $this->assertFalse($this->createTokenType()->onResponse($response, 'https://provider.example.com/token'));
    }

    /**
     * Section 9: "a nonce is only accepted by the server that issued it".
     *
     * The provider and a protected resource are not the same server, so a nonce is kept by
     * the origin that named it and never sent to another.
     */
    public function testANonceIsKeptByTheServerThatNamedIt()
    {
        $tokenType = $this->createTokenType();
        $tokenType->onResponse(self::refusalNaming('provider-nonce'), 'https://provider.example.com/token');

        $toTheProvider = self::decodePayload($tokenType->prepareTokenRequest('https://provider.example.com/token', [])['headers']['DPoP']);
        $toTheResource = self::decodePayload($tokenType->presentToken('access-123', 'GET', 'https://resource.example.com/userinfo', [])['headers']['DPoP']);

        $this->assertSame('provider-nonce', $toTheProvider['nonce']);
        $this->assertArrayNotHasKey('nonce', $toTheResource);
    }

    /**
     * The origin is the whole of it: a server on another port is another server.
     */
    public function testAPortIsPartOfTheOrigin()
    {
        $tokenType = $this->createTokenType();
        $tokenType->onResponse(self::refusalNaming('nonce-1'), 'https://provider.example.com:8443/token');

        $onAnotherPort = self::decodePayload($tokenType->prepareTokenRequest('https://provider.example.com/token', [])['headers']['DPoP']);

        $this->assertArrayNotHasKey('nonce', $onAnotherPort);
    }

    /**
     * A nonce named at one path is the server's, not the path's.
     */
    public function testANonceIsSharedByEveryEndpointOfOneOrigin()
    {
        $tokenType = $this->createTokenType();
        $tokenType->onResponse(self::refusalNaming('nonce-1'), 'https://provider.example.com/token');

        $proof = self::decodePayload($tokenType->presentToken('access-123', 'GET', 'https://provider.example.com/userinfo', [])['headers']['DPoP']);

        $this->assertSame('nonce-1', $proof['nonce']);
    }

    private function createTokenType(): DpopTokenType
    {
        return new DpopTokenType(new DpopProofFactory(new JWK(self::JWK), 'ES256'));
    }

    private static function refusalNaming(string $nonce): ResponseInterface
    {
        return self::respond(new MockResponse(json_encode(['error' => 'use_dpop_nonce']), [
            'http_code' => 400,
            'response_headers' => ['DPoP-Nonce' => $nonce],
        ]));
    }

    /**
     * A mock response only carries its status and its headers once a client has served it.
     */
    private static function respond(MockResponse $response): ResponseInterface
    {
        return (new MockHttpClient($response))->request('POST', 'https://server.example.com/');
    }

    /**
     * @return array<string, mixed>
     */
    private static function decodePayload(string $proof): array
    {
        return json_decode(base64_decode(strtr(explode('.', $proof)[1], '-_', '+/')), true, flags: \JSON_THROW_ON_ERROR);
    }
}
