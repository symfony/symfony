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

use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\Signature\Algorithm\ES256;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\CompactSerializer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcClient;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcIdToken;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcSignatureVerifier;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcTokenRefresher;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;
use Symfony\Component\Security\Http\OAuth2\ClientAuthentication\ClientSecretPost;
use Symfony\Component\Security\Http\Oidc\OidcDiscovery;

class OidcTokenRefresherTest extends TestCase
{
    private MockClock $clock;
    private OidcDiscovery $discovery;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-09-06 12:00:00');
        $this->discovery = new OidcDiscovery(
            new MockHttpClient(static fn (): MockResponse => new JsonMockResponse([
                'issuer' => 'https://provider.example.com',
                'token_endpoint' => 'https://provider.example.com/token',
                'jwks_uri' => 'https://provider.example.com/jwks',
            ])),
            new ArrayAdapter(),
            'https://provider.example.com/.well-known/openid-configuration',
        );
    }

    public function testRefreshIfNeededLeavesAValidAccessTokenAlone()
    {
        $refresher = $this->createRefresher(new JsonMockResponse(['access_token' => 'access-456']));
        $token = $this->createToken(['oidc_access_token_expires_at' => $this->clock->now()->getTimestamp() + 300]);

        $this->assertFalse($refresher->refreshIfNeeded($token));
        $this->assertSame('access-123', $token->getAttribute('oidc_access_token'));
    }

    public function testRefreshIfNeededDoesNothingWithoutARefreshToken()
    {
        $refresher = $this->createRefresher(new JsonMockResponse(['access_token' => 'access-456']));
        $token = $this->createToken([
            'oidc_refresh_token' => null,
            'oidc_access_token_expires_at' => $this->clock->now()->getTimestamp() - 1,
        ]);

        $this->assertFalse($refresher->refreshIfNeeded($token));
        $this->assertSame('access-123', $token->getAttribute('oidc_access_token'));
    }

    public function testRefreshIfNeededDoesNothingWithoutAKnownExpiry()
    {
        // RFC 6749 Section 5.1 makes "expires_in" optional: nothing then says the access
        // token went stale, so renewing it on every request would be the only alternative
        $refresher = $this->createRefresher(new JsonMockResponse(['access_token' => 'access-456']));
        $token = $this->createToken(['oidc_access_token_expires_at' => null]);

        $this->assertFalse($refresher->refreshIfNeeded($token));
        $this->assertSame('access-123', $token->getAttribute('oidc_access_token'));
    }

    public function testRefreshIfNeededRenewsWithinTheLeeway()
    {
        // the access token is handed to a downstream call, which must not receive one
        // expiring while it is in flight
        $refresher = $this->createRefresher(new JsonMockResponse(['access_token' => 'access-456', 'expires_in' => 300]), leeway: 30);
        $token = $this->createToken(['oidc_access_token_expires_at' => $this->clock->now()->getTimestamp() + 29]);

        $this->assertTrue($refresher->refreshIfNeeded($token));
        $this->assertSame('access-456', $token->getAttribute('oidc_access_token'));
        $this->assertSame($this->clock->now()->getTimestamp() + 300, $token->getAttribute('oidc_access_token_expires_at'));
    }

    public function testRefreshStoresARotatedRefreshToken()
    {
        $mockResponse = new JsonMockResponse(['access_token' => 'access-456', 'refresh_token' => 'refresh-456', 'expires_in' => 300]);
        $refresher = $this->createRefresher($mockResponse);
        $token = $this->createToken();

        $refresher->refresh($token);

        parse_str($mockResponse->getRequestOptions()['body'], $body);
        $this->assertSame('refresh-123', $body['refresh_token']);
        $this->assertSame('refresh-456', $token->getAttribute('oidc_refresh_token'));
    }

    public function testRefreshKeepsThePreviousRefreshTokenWhenTheProviderRotatesNone()
    {
        // RFC 6749 Section 6: issuing a new refresh token is a MAY, and the previous one
        // stays usable until the provider replaces it
        $refresher = $this->createRefresher(new JsonMockResponse(['access_token' => 'access-456', 'expires_in' => 300]));
        $token = $this->createToken();

        $refresher->refresh($token);

        $this->assertSame('refresh-123', $token->getAttribute('oidc_refresh_token'));
    }

    public function testRefreshClearsTheExpiryWhenTheProviderReportsNone()
    {
        $refresher = $this->createRefresher(new JsonMockResponse(['access_token' => 'access-456']));
        $token = $this->createToken(['oidc_access_token_expires_at' => $this->clock->now()->getTimestamp() - 1]);

        $refresher->refresh($token);

        $this->assertSame('access-456', $token->getAttribute('oidc_access_token'));
        $this->assertNull($token->getAttribute('oidc_access_token_expires_at'));
    }

    public function testRefreshStoresARefreshedIdToken()
    {
        $refreshedIdToken = $this->buildIdToken();
        $refresher = $this->createRefresher(new JsonMockResponse(['access_token' => 'access-456', 'id_token' => $refreshedIdToken]));
        $token = $this->createToken();

        $refresher->refresh($token);

        $this->assertSame($refreshedIdToken, $token->getAttribute('oidc_id_token'));
    }

    public function testRefreshKeepsThePreviousIdTokenWhenTheProviderReturnsNone()
    {
        $refresher = $this->createRefresher(new JsonMockResponse(['access_token' => 'access-456']));
        $token = $this->createToken();
        $previousIdToken = $token->getAttribute('oidc_id_token');

        $refresher->refresh($token);

        $this->assertSame($previousIdToken, $token->getAttribute('oidc_id_token'));
    }

    public function testRefreshRejectsARefreshedIdTokenOfAnotherSubject()
    {
        // OIDC Core 1.0 Section 12.2: the refreshed ID token describes the very same
        // authentication, so a different "sub" would silently switch the logged-in user
        $refresher = $this->createRefresher(new JsonMockResponse([
            'access_token' => 'access-456',
            'id_token' => $this->buildIdToken(['sub' => 'user-99']),
        ]));
        $token = $this->createToken();

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('The "sub" claim of the refreshed ID token does not match');

        $refresher->refresh($token);
    }

    /**
     * Without the ID token of the original authentication there is nothing to bind the refreshed
     * one to, so nothing says it still describes the user this security token authenticated.
     */
    public function testRefreshRejectsARefreshedIdTokenWithNothingToBindItTo()
    {
        $refresher = $this->createRefresher(new JsonMockResponse(['access_token' => 'access-456', 'id_token' => $this->buildIdToken()]));
        $token = $this->createToken(['oidc_id_token' => null]);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('carries no OIDC ID token to compare the refreshed one against');

        $refresher->refresh($token);
    }

    public function testRefreshRejectsARefreshedIdTokenOfAnotherIssuer()
    {
        $refresher = $this->createRefresher(new JsonMockResponse([
            'access_token' => 'access-456',
            'id_token' => $this->buildIdToken(['iss' => 'https://evil.example.com']),
        ]));
        $token = $this->createToken();

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid ID token');

        $refresher->refresh($token);
    }

    public function testRefreshDoesNotStoreAnythingWhenTheRefreshedIdTokenIsRejected()
    {
        $refresher = $this->createRefresher(new JsonMockResponse([
            'access_token' => 'access-456',
            'refresh_token' => 'refresh-456',
            'id_token' => $this->buildIdToken(['sub' => 'user-99']),
        ]));
        $token = $this->createToken();

        try {
            $refresher->refresh($token);
            $this->fail(\sprintf('Expected an "%s" to be thrown.', AuthenticationException::class));
        } catch (AuthenticationException) {
        }

        $this->assertSame('access-123', $token->getAttribute('oidc_access_token'));
        $this->assertSame('refresh-123', $token->getAttribute('oidc_refresh_token'));
    }

    public function testRefreshVerifiesTheSignatureOfARefreshedIdToken()
    {
        $refresher = $this->createRefresher(
            new JsonMockResponse(['access_token' => 'access-456', 'id_token' => $this->buildForgedIdToken()]),
            signatureVerifier: $this->createSignatureVerifier(),
        );
        $token = $this->createToken();

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('signature');

        $refresher->refresh($token);
    }

    public function testRefreshAcceptsASignedRefreshedIdToken()
    {
        $refreshedIdToken = $this->buildSignedIdToken();
        $refresher = $this->createRefresher(
            new JsonMockResponse(['access_token' => 'access-456', 'id_token' => $refreshedIdToken]),
            signatureVerifier: $this->createSignatureVerifier(),
        );
        $token = $this->createToken();

        $refresher->refresh($token);

        $this->assertSame($refreshedIdToken, $token->getAttribute('oidc_id_token'));
    }

    public function testRefreshThrowsWithoutARefreshToken()
    {
        $refresher = $this->createRefresher(new JsonMockResponse(['access_token' => 'access-456']));
        $token = $this->createToken(['oidc_refresh_token' => null]);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('carries no OIDC refresh token');

        $refresher->refresh($token);
    }

    public function testRefreshRejectsAResponseWithoutAnAccessToken()
    {
        $refresher = $this->createRefresher(new JsonMockResponse(['token_type' => 'Bearer']));
        $token = $this->createToken();

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('does not contain a valid "access_token"');

        $refresher->refresh($token);
    }

    private function createRefresher(MockResponse $tokenEndpointResponse, ?OidcSignatureVerifier $signatureVerifier = null, int $leeway = 30): OidcTokenRefresher
    {
        return new OidcTokenRefresher(
            new OidcClient(new MockHttpClient($tokenEndpointResponse), $this->discovery, 'test-client-id', new ClientSecretPost('test-client-secret')),
            $this->discovery,
            new OidcIdToken($this->clock),
            'test-client-id',
            $signatureVerifier,
            $leeway,
            $this->clock,
        );
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createToken(array $attributes = []): TokenInterface
    {
        $token = new PostAuthenticationToken(new InMemoryUser('user-42', null), 'main', ['ROLE_USER']);
        $token->setAttributes(array_merge([
            'oidc_id_token' => $this->buildIdToken(),
            'oidc_access_token' => 'access-123',
            'oidc_refresh_token' => 'refresh-123',
            'oidc_access_token_expires_at' => $this->clock->now()->getTimestamp() - 1,
        ], $attributes));

        return $token;
    }

    /**
     * @param array<string, mixed> $extraClaims
     */
    private function buildIdTokenClaims(array $extraClaims = []): array
    {
        return array_merge([
            'iss' => 'https://provider.example.com',
            'aud' => 'test-client-id',
            'sub' => 'user-42',
            'exp' => $this->clock->now()->getTimestamp() + 3600,
            'iat' => $this->clock->now()->getTimestamp(),
        ], $extraClaims);
    }

    private function buildIdToken(array $extraClaims = []): string
    {
        $encode = static fn (array $value): string => rtrim(strtr(base64_encode(json_encode($value)), '+/', '-_'), '=');

        return $encode(['alg' => 'RS256', 'typ' => 'JWT']).'.'.$encode($this->buildIdTokenClaims($extraClaims)).'.'.rtrim(strtr(base64_encode('fake-signature'), '+/', '-_'), '=');
    }

    private function buildSignedIdToken(array $extraClaims = []): string
    {
        return (new CompactSerializer())->serialize(
            (new JWSBuilder(new AlgorithmManager([new ES256()])))
                ->withPayload(json_encode($this->buildIdTokenClaims($extraClaims)))
                ->addSignature(new JWK([
                    'kty' => 'EC',
                    'crv' => 'P-256',
                    'x' => '0QEAsI1wGI-dmYatdUZoWSRWggLEpyzopuhwk-YUnA4',
                    'y' => 'KYl-qyZ26HobuYwlQh-r0iHX61thfP82qqEku7i0woo',
                    'd' => 'iA_TV2zvftni_9aFAQwFO_9aypfJFCSpcCyevDvz220',
                ]), ['alg' => 'ES256', 'kid' => 'signing-key'])
                ->build()
        );
    }

    private function buildForgedIdToken(array $extraClaims = []): string
    {
        [$header, $payload] = explode('.', $this->buildSignedIdToken($extraClaims));

        return $header.'.'.$payload.'.'.rtrim(strtr(base64_encode('forged-signature'), '+/', '-_'), '=');
    }

    private function createSignatureVerifier(): OidcSignatureVerifier
    {
        return new OidcSignatureVerifier(
            $this->discovery,
            new ArrayAdapter(),
            new MockHttpClient(new JsonMockResponse(['keys' => [[
                'kid' => 'signing-key',
                'kty' => 'EC',
                'crv' => 'P-256',
                'x' => '0QEAsI1wGI-dmYatdUZoWSRWggLEpyzopuhwk-YUnA4',
                'y' => 'KYl-qyZ26HobuYwlQh-r0iHX61thfP82qqEku7i0woo',
                'use' => 'sig',
                'alg' => 'ES256',
            ]]])),
            ['ES256'],
            clock: $this->clock,
        );
    }
}
