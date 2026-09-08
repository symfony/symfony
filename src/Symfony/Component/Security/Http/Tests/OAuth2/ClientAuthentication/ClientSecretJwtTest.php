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
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Security\Http\OAuth2\ClientAuthentication\AbstractClientAssertion;
use Symfony\Component\Security\Http\OAuth2\ClientAuthentication\ClientSecretJwt;

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
     * OIDC Core 1.0, Section 9: the HMAC key is the octets of the UTF-8 representation of the
     * client secret, so the provider verifies the assertion with the secret it already holds
     * and nothing has to be registered for this method.
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
     * An asymmetric algorithm would make the client sign with a secret the provider also
     * holds, which no key of that kind is meant to be.
     */
    public function testRejectsASignatureAlgorithm()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "RS256" algorithm cannot sign a "client_secret_jwt" client assertion. Use one of "HS256", "HS384", "HS512".');

        new ClientSecretJwt(self::CLIENT_SECRET, 'RS256');
    }

    /**
     * RFC 7518, Section 3.2: the key of an HMAC must be at least as long as the digest it
     * produces, or the secret and not the algorithm sets the strength of the signature. The
     * rule belongs to the algorithm, which is asked about the key when the service is built
     * so that the secret is refused there and not on the first token request.
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

    private function createClientAuthentication(): ClientSecretJwt
    {
        return new ClientSecretJwt(self::CLIENT_SECRET, 'HS256', 60, new MockClock('2026-09-08 10:00:00'));
    }

    private static function decodeBase64Url(string $value): string
    {
        return base64_decode(strtr($value, '-_', '+/'));
    }
}
