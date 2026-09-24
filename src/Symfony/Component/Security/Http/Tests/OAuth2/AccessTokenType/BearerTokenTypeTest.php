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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\OAuth2\AccessTokenType\BearerTokenType;

class BearerTokenTypeTest extends TestCase
{
    public function testReportsItsTokenType()
    {
        $this->assertSame('Bearer', (new BearerTokenType())->getTokenType());
    }

    /**
     * RFC 6750, Section 2.1: the token travels in the "Authorization" header, which is what
     * the "auth_bearer" option of the HTTP client writes.
     */
    public function testPresentsTheTokenAsABearerToken()
    {
        $options = (new BearerTokenType())->presentToken('access-123', 'GET', 'https://provider.example.com/userinfo', ['max_redirects' => 0]);

        $this->assertSame(['max_redirects' => 0, 'auth_bearer' => 'access-123'], $options);
    }

    /**
     * Nothing is proven at the token endpoint by a client that proves nothing anywhere.
     */
    public function testAddsNothingToATokenRequest()
    {
        $options = (new BearerTokenType())->prepareTokenRequest('https://provider.example.com/token', ['body' => ['grant_type' => 'authorization_code']]);

        $this->assertSame(['body' => ['grant_type' => 'authorization_code']], $options);
    }

    /**
     * @param array<string, mixed> $tokenResponse
     */
    #[DataProvider('provideAcceptedTokenResponses')]
    public function testAcceptsATokenItCanPresent(array $tokenResponse)
    {
        $this->expectNotToPerformAssertions();

        (new BearerTokenType())->checkTokenResponse($tokenResponse);
    }

    public static function provideAcceptedTokenResponses(): iterable
    {
        yield 'the type the specification writes' => [['access_token' => 'access-123', 'token_type' => 'Bearer']];
        yield 'another case, the value being case insensitive' => [['access_token' => 'access-123', 'token_type' => 'bearer']];
        yield 'no type at all, which a provider does leave out' => [['access_token' => 'access-123']];
        yield 'an empty type' => [['access_token' => 'access-123', 'token_type' => '']];
    }

    /**
     * RFC 6749, Section 7.1: "The client MUST NOT use an access token if it does not
     * understand the token type.".
     *
     * A bound token presented as a bearer token is refused by the resource server anyway
     * (RFC 9449, Section 7.1), and saying so here names what is wrong.
     */
    public function testRefusesATokenOfAnotherType()
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('issued an access token of the "DPoP" type, which this client cannot use');

        (new BearerTokenType())->checkTokenResponse(['access_token' => 'access-123', 'token_type' => 'DPoP']);
    }

    public function testRefusesATokenTypeThatIsNoString()
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('of the "array" type, which this client cannot use');

        (new BearerTokenType())->checkTokenResponse(['access_token' => 'access-123', 'token_type' => ['Bearer']]);
    }

    /**
     * Nothing a server answers asks a bearer token client to send its request again.
     */
    public function testNeverRepeatsARequest()
    {
        $response = (new MockHttpClient(new MockResponse('', [
            'http_code' => 401,
            'response_headers' => ['WWW-Authenticate' => 'DPoP error="use_dpop_nonce"', 'DPoP-Nonce' => 'nonce-1'],
        ])))->request('POST', 'https://provider.example.com/token');
        // the refusal is the caller's to report, and reading it here is what keeps the
        // response from throwing when nothing else looks at it
        $response->getContent(false);

        $this->assertFalse((new BearerTokenType())->onResponse($response, 'https://provider.example.com/token'));
    }
}
