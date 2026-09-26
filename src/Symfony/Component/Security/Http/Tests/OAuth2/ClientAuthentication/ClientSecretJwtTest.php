<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\OAuth2\ClientAuthentication;

use Jose\Component\Core\JWK;
use Jose\Component\Signature\Algorithm\HS256;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\Security\Http\OAuth2\ClientAuthentication\AbstractClientAssertion;
use Symfony\Component\Security\Http\OAuth2\ClientAuthentication\ClientSecretJwt;
use Symfony\Component\Security\Http\Oidc\OidcDiscovery;

class ClientSecretJwtTest extends TestCase
{
    /**
     * 32 bytes, the shortest secret RFC 7518, Section 3.2 allows "HS256" to be keyed with.
     */
    private const CLIENT_SECRET = 'a-client-secret-of-thirty-two-by';

    public function testSendsTheAssertionInTheBodyAndNeverTheSecretItself()
    {
        $options = $this->createClientAuthentication()->authenticate('test-client-id', 'https://provider.example.com/token', ['body' => [
            'grant_type' => 'refresh_token',
            'client_id' => 'test-client-id',
        ]]);

        $this->assertSame(AbstractClientAssertion::ASSERTION_TYPE, $options['body']['client_assertion_type']);
        $this->assertSame('refresh_token', $options['body']['grant_type']);
        $this->assertArrayNotHasKey('client_secret', $options['body']);
        $this->assertArrayNotHasKey('auth_basic', $options);
        $this->assertStringNotContainsString(self::CLIENT_SECRET, $options['body']['client_assertion']);
    }

    public function testNamesTheClientAsIssuerAndSubjectAndTheTokenEndpointAsAudience()
    {
        $options = $this->createClientAuthentication()->authenticate('test-client-id', 'https://provider.example.com/token', ['body' => []]);

        $claims = json_decode(self::decodeBase64Url(explode('.', $options['body']['client_assertion'])[1]), true, flags: \JSON_THROW_ON_ERROR);

        $this->assertSame('test-client-id', $claims['iss']);
        $this->assertSame('test-client-id', $claims['sub']);
        $this->assertSame('https://provider.example.com/token', $claims['aud']);
        $this->assertNotEmpty($claims['jti']);
        $this->assertSame(strtotime('2026-09-08 10:00:00 UTC'), $claims['iat']);
        $this->assertSame(strtotime('2026-09-08 10:01:00 UTC'), $claims['exp']);
    }

    /**
     * The HMAC key is the octets of the UTF-8 representation of the client secret.
     *
     * OIDC Core 1.0, Section 9 defines it, so the provider verifies the assertion with the
     * secret it already holds and nothing has to be registered for this method.
     */
    public function testKeysTheHmacWithTheOctetsOfTheSecret()
    {
        $options = $this->createClientAuthentication()->authenticate('test-client-id', 'https://provider.example.com/token', ['body' => []]);

        [$header, $payload, $signature] = explode('.', $options['body']['client_assertion']);
        $key = new JWK(['kty' => 'oct', 'k' => rtrim(strtr(base64_encode(self::CLIENT_SECRET), '+/', '-_'), '=')]);

        $this->assertTrue((new HS256())->verify($key, $header.'.'.$payload, self::decodeBase64Url($signature)));
        $this->assertSame(['alg' => 'HS256'], json_decode(self::decodeBase64Url($header), true, flags: \JSON_THROW_ON_ERROR));
    }

    /**
     * An asymmetric algorithm would make the client sign with a shared secret.
     *
     * No key of that kind is meant to be one the provider also holds.
     */
    public function testRejectsASignatureAlgorithm()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "RS256" algorithm cannot sign a "client_secret_jwt" client assertion. Use one of "HS256", "HS384", "HS512".');

        new ClientSecretJwt(self::CLIENT_SECRET, 'RS256');
    }

    /**
     * The key of an HMAC must be at least as long as the digest it produces.
     *
     * RFC 7518, Section 3.2 says so, or the secret and not the algorithm sets the strength of
     * the signature. The rule belongs to the algorithm, which is asked about the key when the
     * service is built so that the secret is refused there and not on the first token
     * request.
     */
    #[DataProvider('provideSecretsShorterThanTheDigest')]
    public function testRejectsASecretTheAlgorithmRefusesToBeKeyedWith(string $algorithm, int $length)
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('The OAuth2 client secret cannot key a "client_secret_jwt" assertion signed with "%s", which rejected it', $algorithm));

        new ClientSecretJwt(str_repeat('s', $length), $algorithm);
    }

    public static function provideSecretsShorterThanTheDigest(): iterable
    {
        yield 'empty' => ['HS256', 0];
        yield 'HS256' => ['HS256', 31];
        yield 'HS384' => ['HS384', 47];
        yield 'HS512' => ['HS512', 63];
    }

    #[DataProvider('provideSecretsAsLongAsTheDigest')]
    public function testAcceptsASecretAsLongAsTheDigest(string $algorithm, int $length)
    {
        $options = (new ClientSecretJwt(str_repeat('s', $length), $algorithm))->authenticate('test-client-id', 'https://provider.example.com/token', ['body' => []]);

        $this->assertSame(['alg' => $algorithm], json_decode(self::decodeBase64Url(explode('.', $options['body']['client_assertion'])[0]), true, flags: \JSON_THROW_ON_ERROR));
    }

    public static function provideSecretsAsLongAsTheDigest(): iterable
    {
        yield 'HS256' => ['HS256', 32];
        yield 'HS384' => ['HS384', 48];
        yield 'HS512' => ['HS512', 64];
    }

    public function testRejectsALifetimeThatIsNotPositive()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The lifetime of an OAuth2 client assertion must be a positive number of seconds, got -1.');

        new ClientSecretJwt(self::CLIENT_SECRET, 'HS256', -1);
    }

    public function testReportsItsMethod()
    {
        $this->assertSame('client_secret_jwt', $this->createClientAuthentication()->getMethod());
    }

    /**
     * FAPI 2.0 Security Profile, Section 5.2.2 takes the issuer and nothing else: an assertion
     * made for one endpoint of a provider authenticates the client at every other endpoint of
     * that same provider.
     */
    public function testNamesTheIssuerTheProviderAnnouncesAsAudienceWhenOneIsGiven()
    {
        // Given
        $clientAuthentication = $this->createClientAuthentication(self::createDiscovery('https://provider.example.com'));

        // When
        $options = $clientAuthentication->authenticate('test-client-id', 'https://provider.example.com/token', ['body' => []]);

        // Then
        $claims = json_decode(self::decodeBase64Url(explode('.', $options['body']['client_assertion'])[1]), true, flags: \JSON_THROW_ON_ERROR);
        $this->assertSame('https://provider.example.com', $claims['aud']);
    }

    /**
     * draft-ietf-oauth-rfc7523bis: "Client authentication JWTs SHOULD be explicitly typed by
     * using the typ header parameter value client-authentication+jwt".
     *
     * It keeps one kind of JWT from being taken for another (RFC 8725, Section 3.11), and it
     * is how a client signals "their compliance with the requirements herein".
     */
    public function testTypesTheAssertionExplicitlyWhenItNamesTheIssuer()
    {
        // Given
        $clientAuthentication = $this->createClientAuthentication(self::createDiscovery('https://provider.example.com'));

        // When
        $options = $clientAuthentication->authenticate('test-client-id', 'https://provider.example.com/token', ['body' => []]);

        // Then
        $this->assertSame('client-authentication+jwt', self::decodeHeader($options['body']['client_assertion'])['typ']);
    }

    /**
     * The type says the assertion was "produced in accordance" with the draft, and an
     * assertion naming the endpoint is not: the same draft writes that "the token endpoint URL
     * of the authorization server MUST NOT be used as an audience value". Typing it would
     * claim a compliance it does not have, and the draft asks servers not to reject an
     * untyped assertion anyway.
     */
    public function testTypesNothingWhenTheAssertionNamesTheEndpoint()
    {
        // Given
        $clientAuthentication = $this->createClientAuthentication();

        // When
        $options = $clientAuthentication->authenticate('test-client-id', 'https://provider.example.com/token', ['body' => []]);

        // Then
        $this->assertArrayNotHasKey('typ', self::decodeHeader($options['body']['client_assertion']));
    }

    private static function createDiscovery(string $announcedIssuer): OidcDiscovery
    {
        return new OidcDiscovery(
            new MockHttpClient(new JsonMockResponse(['issuer' => $announcedIssuer])),
            new ArrayAdapter(),
            'https://provider.example.com/.well-known/openid-configuration',
            'https://provider.example.com',
        );
    }

    private function createClientAuthentication(?OidcDiscovery $issuerAudience = null): ClientSecretJwt
    {
        return new ClientSecretJwt(self::CLIENT_SECRET, 'HS256', 60, new MockClock('2026-09-08 10:00:00'), $issuerAudience);
    }

    /**
     * @return array<string, mixed>
     */
    private static function decodeHeader(string $assertion): array
    {
        return json_decode(self::decodeBase64Url(explode('.', $assertion)[0]), true, flags: \JSON_THROW_ON_ERROR);
    }

    private static function decodeBase64Url(string $value): string
    {
        return base64_decode(strtr($value, '-_', '+/'));
    }
}
