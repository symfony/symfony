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
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcSignatureVerifier;
use Symfony\Component\Security\Http\Oidc\OidcDiscovery;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[RequiresPhpExtension('openssl')]
class OidcSignatureVerifierTest extends TestCase
{
    private const PUBLIC_JWK = [
        'kid' => 'signing-key',
        'kty' => 'EC',
        'crv' => 'P-256',
        'x' => '0QEAsI1wGI-dmYatdUZoWSRWggLEpyzopuhwk-YUnA4',
        'y' => 'KYl-qyZ26HobuYwlQh-r0iHX61thfP82qqEku7i0woo',
        'use' => 'sig',
        'alg' => 'ES256',
    ];

    /**
     * Another key pair entirely: a signature made with the key above cannot be verified with it.
     */
    private const FOREIGN_JWK = [
        'kid' => 'foreign-key',
        'kty' => 'EC',
        'crv' => 'P-256',
        'x' => 'N1aUu8Pd2WdClkpCQ4QCPnGjYe_bTmDgEaSoxy5LhTw',
        'y' => 'Yr1v-tCNxE8QgAGlartrJAi343bI8VlAaNvgCOp8Azs',
        'use' => 'sig',
        'alg' => 'ES256',
    ];

    public function testVerifyReturnsTheClaimsOfAValidlySignedToken()
    {
        $claims = ['iss' => 'https://provider.example.com', 'sub' => 'user-42', 'email' => 'test@example.com'];

        $this->assertSame($claims, $this->createVerifier()->verify($this->buildJws(json_encode($claims))));
    }

    public function testVerifyRejectsATamperedSignature()
    {
        // a valid JWS structure, whose signature comes from another payload
        [$header, $payload] = explode('.', $this->buildJws(json_encode(['sub' => 'user-42'])));
        [, , $foreignSignature] = explode('.', $this->buildJws(json_encode(['sub' => 'someone-else'])));

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('The ID token signature is invalid.');

        $this->createVerifier()->verify($header.'.'.$payload.'.'.$foreignSignature);
    }

    public function testVerifyRejectsAnEmptySignature()
    {
        [$header, $payload] = explode('.', $this->buildJws(json_encode(['sub' => 'user-42'])));

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('The ID token signature is invalid.');

        $this->createVerifier()->verify($header.'.'.$payload.'.');
    }

    public function testVerifyRejectsAnUnsignedToken()
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        // the "alg" header is rejected before the provider keys are even requested
        $httpClient->expects($this->never())->method('request');

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('The ID token is not signed with any of the expected algorithms ("ES256").');

        $this->createVerifier(httpClient: $httpClient)->verify($this->buildUnsecuredJws(['sub' => 'admin', 'roles' => ['ROLE_ADMIN']]));
    }

    public function testVerifyRejectsATokenWithoutAnyAlgorithm()
    {
        $token = $this->base64Url('{"typ":"JWT"}').'.'.$this->base64Url('{"sub":"user-42"}').'.'.$this->base64Url('signature');

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('The ID token is not signed with any of the expected algorithms ("ES256").');

        $this->createVerifier()->verify($token);
    }

    public function testVerifyRejectsAnAlgorithmOutsideTheAllowlist()
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('The ID token is not signed with any of the expected algorithms ("RS256", "PS256").');

        // the token is properly signed, but with an algorithm the provider is not configured for
        $this->createVerifier(algorithms: ['RS256', 'PS256'])->verify($this->buildJws(json_encode(['sub' => 'user-42'])));
    }

    public function testVerifyRejectsAnHmacAlgorithm()
    {
        // an HMAC algorithm is never supported, so the public keys of the provider can
        // never be turned into the shared secret of an "HS256" token (key confusion)
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Unsupported OIDC ID token signature algorithm "HS256".');

        $this->createVerifier(algorithms: ['HS256'])->verify($this->buildJws(json_encode(['sub' => 'user-42'])));
    }

    public function testVerifyRejectsATokenThatIsNotAJws()
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid ID token format.');

        $this->createVerifier()->verify('not-a-jwt');
    }

    public function testVerifyRejectsAnUnknownSigningKey()
    {
        // the provider publishes a valid key, under the "kid" the token announces, but
        // not the one it was signed with
        $verifier = $this->createVerifier(jwks: [['keys' => [['kid' => 'signing-key'] + self::FOREIGN_JWK]]]);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('The ID token signature is invalid.');

        $verifier->verify($this->buildJws(json_encode(['sub' => 'user-42'])));
    }

    public function testVerifyFailsWhenTheProviderPublishesNoUsableKey()
    {
        // the only key the provider publishes is scoped to encryption
        $verifier = $this->createVerifier(jwks: [['keys' => [['kid' => 'k', 'kty' => 'EC', 'use' => 'enc']]]]);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('published no signing key');

        $verifier->verify($this->buildJws(json_encode(['sub' => 'user-42'])));
    }

    public function testVerifyRejectsAPlainHttpJwksUri()
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        // the JWKS carries the keys the whole verification relies on: it must never
        // be requested over a transport a MITM can rewrite
        $httpClient->expects($this->never())->method('request');

        $verifier = $this->createVerifier(configuration: [
            'issuer' => 'https://provider.example.com',
            'jwks_uri' => 'http://provider.example.com/jwks',
        ], httpClient: $httpClient);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('The "jwks_uri" announced by the OIDC provider must use HTTPS');

        $verifier->verify($this->buildJws(json_encode(['sub' => 'user-42'])));
    }

    public function testVerifyFailsWhenTheJwksCannotBeFetched()
    {
        $verifier = $this->createVerifier(httpClient: new MockHttpClient(new JsonMockResponse(['error' => 'server_error'], ['http_code' => 500])));

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('The OIDC provider JWKS could not be fetched');

        $verifier->verify($this->buildJws(json_encode(['sub' => 'user-42'])));
    }

    public function testVerifyFailsWhenTheJwksIsNotValidJson()
    {
        $verifier = $this->createVerifier(httpClient: new MockHttpClient(new MockResponse('not-json', ['response_headers' => ['content-type' => 'application/json']])));

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('The OIDC provider JWKS could not be fetched');

        $verifier->verify($this->buildJws(json_encode(['sub' => 'user-42'])));
    }

    public function testVerifyFailsWhenTheProviderAnnouncesNoJwksUri()
    {
        $verifier = $this->createVerifier(configuration: ['issuer' => 'https://provider.example.com']);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('announces no "jwks_uri"');

        $verifier->verify($this->buildJws(json_encode(['sub' => 'user-42'])));
    }

    public function testVerifyRejectsAnInvalidPayload()
    {
        [$header, , $signature] = explode('.', $this->buildJws('not-json'));

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid ID token payload.');

        $this->createVerifier()->verify($header.'.'.$this->base64Url('not-json').'.'.$signature);
    }

    public function testVerifyFetchesTheProviderKeysOnce()
    {
        $requests = 0;
        $httpClient = new MockHttpClient(static function () use (&$requests): JsonMockResponse {
            ++$requests;

            return new JsonMockResponse(['keys' => [self::PUBLIC_JWK]]);
        });
        $verifier = $this->createVerifier(httpClient: $httpClient);
        $token = $this->buildJws(json_encode(['sub' => 'user-42']));

        $verifier->verify($token);
        $verifier->verify($token);

        $this->assertSame(1, $requests);
    }

    public function testVerifyRefetchesTheKeysWhenTheTokenAnnouncesAnUnknownKid()
    {
        // the JWKS is cached before the provider rotated its keys, the next one holds the new key
        $verifier = $this->createVerifier(jwks: [
            ['keys' => [self::FOREIGN_JWK]],
            ['keys' => [['kid' => 'rotated-key'] + self::PUBLIC_JWK]],
        ], clock: $clock = new MockClock());
        $this->warmTheJwksCache($verifier);

        $clock->sleep(120);
        $claims = $verifier->verify($this->buildJws(json_encode(['sub' => 'user-42']), 'rotated-key'));

        $this->assertSame(['sub' => 'user-42'], $claims);
    }

    public function testVerifyThrottlesTheRefetchOfTheProviderKeys()
    {
        $verifier = $this->createVerifier(jwks: [
            ['keys' => [self::FOREIGN_JWK]],
            ['keys' => [['kid' => 'rotated-key'] + self::PUBLIC_JWK]],
        ], clock: new MockClock());
        $this->warmTheJwksCache($verifier);

        // the JWKS was just fetched, so a token carrying an unknown "kid" does not get to
        // trigger another request: forged tokens cannot drive the outbound traffic
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('The ID token signature is invalid.');

        $verifier->verify($this->buildJws(json_encode(['sub' => 'user-42']), 'rotated-key'));
    }

    /**
     * Verifies a token announcing a "kid" the provider does publish, so that the JWKS ends
     * up cached without any rotation refetch being attempted.
     */
    private function warmTheJwksCache(OidcSignatureVerifier $verifier): void
    {
        try {
            $verifier->verify($this->buildJws(json_encode(['sub' => 'user-42']), self::FOREIGN_JWK['kid']));
            $this->fail('The foreign key should not have verified the signature.');
        } catch (AuthenticationException) {
        }
    }

    /**
     * @param list<array<string, mixed>> $jwks
     * @param list<string>               $algorithms
     */
    private function createVerifier(?array $jwks = null, array $algorithms = ['ES256'], ?array $configuration = null, ?HttpClientInterface $httpClient = null, ?MockClock $clock = null): OidcSignatureVerifier
    {
        $configuration ??= [
            'issuer' => 'https://provider.example.com',
            'jwks_uri' => 'https://provider.example.com/jwks',
        ];
        $jwks ??= [['keys' => [self::PUBLIC_JWK]]];

        $discovery = new OidcDiscovery(
            new MockHttpClient(new JsonMockResponse($configuration)),
            new ArrayAdapter(),
            'https://provider.example.com/.well-known/openid-configuration',
        );

        return new OidcSignatureVerifier(
            $discovery,
            new ArrayAdapter(),
            $httpClient ?? new MockHttpClient(array_map(static fn (array $jwkSet): JsonMockResponse => new JsonMockResponse($jwkSet), $jwks)),
            $algorithms,
            clock: $clock ?? new MockClock(),
        );
    }

    private function buildJws(string $payload, string $kid = 'signing-key'): string
    {
        // tip: use https://mkjwk.org/ to generate a JWK
        $jwk = new JWK([
            'kty' => 'EC',
            'crv' => 'P-256',
            'x' => '0QEAsI1wGI-dmYatdUZoWSRWggLEpyzopuhwk-YUnA4',
            'y' => 'KYl-qyZ26HobuYwlQh-r0iHX61thfP82qqEku7i0woo',
            'd' => 'iA_TV2zvftni_9aFAQwFO_9aypfJFCSpcCyevDvz220',
        ]);

        return (new CompactSerializer())->serialize(
            (new JWSBuilder(new AlgorithmManager([new ES256()])))
                ->withPayload($payload)
                ->addSignature($jwk, ['alg' => 'ES256', 'kid' => $kid])
                ->build()
        );
    }

    /**
     * Builds the "alg": "none" token of RFC 7519, Section 6: a JWS with no signature at all.
     */
    private function buildUnsecuredJws(array $claims): string
    {
        return $this->base64Url('{"alg":"none"}').'.'.$this->base64Url(json_encode($claims)).'.';
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
