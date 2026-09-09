<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\AccessToken\OAuth2;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\OAuth2User;
use Symfony\Component\Security\Http\AccessToken\OAuth2\Oauth2TokenHandler;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

class OAuth2TokenHandlerTest extends TestCase
{
    #[DataProvider('unreadableResponses')]
    public function testTurnsAnErrorOfTheAuthorizationServerIntoBadCredentials(MockResponse $response)
    {
        $this->expectException(BadCredentialsException::class);
        $this->expectExceptionMessage('Invalid credentials.');

        (new Oauth2TokenHandler(new MockHttpClient([$response])))->getUserBadgeFrom('a-secret-token');
    }

    public static function unreadableResponses(): iterable
    {
        yield 'the caller is refused' => [new MockResponse('{"error":"invalid_client"}', ['http_code' => 401])];
        yield 'the server is down' => [new MockResponse('', ['http_code' => 503])];
        yield 'the server is unreachable' => [new MockResponse('', ['error' => 'Could not resolve host: authorization-server.example.com'])];
        yield 'the body is not JSON' => [new MockResponse('<html>Bad Gateway</html>')];
        yield 'the body is empty' => [new MockResponse('')];
        yield 'the body is not an object' => [new MockResponse('"nope"')];
    }

    #[DataProvider('inactiveResponses')]
    public function testRejectsATokenTheServerDoesNotReportAsActive(array $claims)
    {
        $client = new MockHttpClient([new MockResponse(json_encode($claims, \JSON_THROW_ON_ERROR))]);

        $this->expectException(BadCredentialsException::class);
        $this->expectExceptionMessage('Invalid credentials.');

        (new Oauth2TokenHandler($client))->getUserBadgeFrom('a-secret-token');
    }

    /**
     * Everything but the boolean the specification defines, which is everything the truthy reading
     * of "active" used to honor, the very "false" of a server not serializing it as a JSON boolean
     * included.
     */
    public static function inactiveResponses(): iterable
    {
        yield 'inactive' => [['active' => false, 'sub' => 'jdoe']];
        yield 'missing' => [['sub' => 'jdoe']];
        yield 'stringly typed false' => [['active' => 'false', 'sub' => 'jdoe']];
        yield 'stringly typed true' => [['active' => 'true', 'sub' => 'jdoe']];
        yield 'the number one' => [['active' => 1, 'sub' => 'jdoe']];
        yield 'no' => [['active' => 'no', 'sub' => 'jdoe']];
        yield 'not a boolean at all' => [['active' => 'bogus', 'sub' => 'jdoe']];
    }

    /**
     * A provider that does not serialize the verified claims as JSON booleans must not make
     * the user report the opposite of what the claim says: (bool) "false" is true.
     */
    #[DataProvider('verifiedClaims')]
    public function testOnlyKeepsARecognizableBooleanForTheVerifiedClaims(mixed $value, ?bool $expected)
    {
        $claims = ['active' => true, 'sub' => 'jdoe', 'email_verified' => $value];
        $client = new MockHttpClient([new MockResponse(json_encode($claims, \JSON_THROW_ON_ERROR))]);

        $user = (new Oauth2TokenHandler($client))->getUserBadgeFrom('a-secret-token')->getUserLoader()();

        $this->assertSame($expected, $user->additionalClaims['emailVerified'] ?? null);
    }

    public static function verifiedClaims(): iterable
    {
        yield 'a boolean true' => [true, true];
        yield 'a boolean false' => [false, false];
        yield 'a stringly typed true' => ['true', true];
        yield 'a stringly typed false' => ['false', false];
        yield 'no' => ['no', false];
        yield 'off' => ['off', false];
        yield 'anything else' => ['maybe', null];
    }

    public function testIntrospectionDoesNotFollowRedirects()
    {
        // the options are asserted after the call: the handler turns every exception the
        // response factory raises into a BadCredentialsException, a failed assertion included
        $requestOptions = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requestOptions) {
            $requestOptions = $options;

            return new MockResponse('', ['http_code' => 307, 'response_headers' => ['location' => 'https://other.example.com/introspect']]);
        });

        try {
            (new Oauth2TokenHandler($client))->getUserBadgeFrom('a-secret-token');
            $this->fail('A BadCredentialsException should have been thrown.');
        } catch (BadCredentialsException) {
        }

        $this->assertSame(0, $requestOptions['max_redirects'] ?? null);
    }

    public function testGetsUserIdentifierFromOAuth2ServerResponse()
    {
        $accessToken = 'a-secret-token';
        $claims = [
            'active' => true,
            'client_id' => 'l238j323ds-23ij4',
            'username' => 'jdoe',
            'scope' => 'read write dolphin',
            'sub' => 'Z5O3upPC88QrAjx00dis',
            'aud' => 'https://protected.example.net/resource',
            'iss' => 'https://server.example.com/',
            'exp' => 1419356238,
            'iat' => 1419350238,
            'extension_field' => 'twenty-seven',
        ];

        $client = new MockHttpClient([
            new MockResponse(json_encode($claims, \JSON_THROW_ON_ERROR)),
        ]);

        $userBadge = (new Oauth2TokenHandler($client))->getUserBadgeFrom($accessToken);
        $actualUser = $userBadge->getUserLoader()();

        $this->assertInstanceOf(UserBadge::class, $userBadge);
        $this->assertSame('Z5O3upPC88QrAjx00dis', $userBadge->getUserIdentifier());
        $this->assertSame($claims, $userBadge->getAttributes());
        $this->assertInstanceOf(OAuth2User::class, $actualUser);
        $this->assertSame($claims, $userBadge->getAttributes());
        $this->assertSame($claims['sub'], $actualUser->getUserIdentifier());
    }
}
