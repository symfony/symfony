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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcIdToken;

#[RequiresPhpExtension('openssl')]
class OidcIdTokenTest extends TestCase
{
    public function testDecode()
    {
        $jwt = $this->buildJwt(['sub' => 'user-42', 'email' => 'test@example.com']);

        $claims = $this->createIdToken()->decode($jwt);

        $this->assertSame('user-42', $claims['sub']);
        $this->assertSame('test@example.com', $claims['email']);
    }

    public function testDecodeInvalidFormat()
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid ID token format');

        $this->createIdToken()->decode('not-a-jwt');
    }

    public function testDecodeInvalidBase64()
    {
        $this->expectException(AuthenticationException::class);

        $this->createIdToken()->decode('header.!!!invalid!!!.signature');
    }

    public function testDecodeInvalidJson()
    {
        $header = base64_encode('{"alg":"RS256"}');
        $payload = rtrim(strtr(base64_encode('not-json'), '+/', '-_'), '=');
        $signature = base64_encode('sig');

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid ID token payload');

        $this->createIdToken()->decode($header.'.'.$payload.'.'.$signature);
    }

    public function testValidateClaims()
    {
        $claims = [
            'iss' => 'https://provider.example.com',
            'aud' => 'my-client-id',
            'exp' => time() + 3600,
            'iat' => time(),
            'nonce' => 'expected-nonce',
        ];

        $this->createIdToken()->validateClaims($claims, 'https://provider.example.com', 'my-client-id', 'expected-nonce');

        $this->addToAssertionCount(1);
    }

    public function testValidateClaimsWithArrayAudience()
    {
        $claims = [
            'iss' => 'https://provider.example.com',
            'aud' => ['other-client', 'my-client-id'],
            'azp' => 'my-client-id',
            'exp' => time() + 3600,
            'iat' => time(),
        ];

        $this->createIdToken()->validateClaims($claims, 'https://provider.example.com', 'my-client-id');

        $this->addToAssertionCount(1);
    }

    public function testValidateClaimsAllowsMultipleAudiencesWithoutAzp()
    {
        // OIDC Core 1.0, Section 3.1.3.7 item 3 is a SHOULD, and the audience is already
        // checked, so a missing "azp" claim must not reject the token
        $claims = [
            'iss' => 'https://provider.example.com',
            'aud' => ['other-client', 'my-client-id'],
            'exp' => time() + 3600,
            'iat' => time(),
        ];

        $this->createIdToken()->validateClaims($claims, 'https://provider.example.com', 'my-client-id');

        $this->addToAssertionCount(1);
    }

    public function testValidateClaimsWrongAzp()
    {
        $claims = [
            'iss' => 'https://provider.example.com',
            'aud' => 'my-client-id',
            'azp' => 'another-client',
            'exp' => time() + 3600,
            'iat' => time(),
        ];

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('azp');

        $this->createIdToken()->validateClaims($claims, 'https://provider.example.com', 'my-client-id');
    }

    public function testValidateClaimsWithoutNonce()
    {
        $claims = [
            'iss' => 'https://provider.example.com',
            'aud' => 'my-client-id',
            'exp' => time() + 3600,
            'iat' => time(),
        ];

        $this->createIdToken()->validateClaims($claims, 'https://provider.example.com', 'my-client-id');

        $this->addToAssertionCount(1);
    }

    public function testValidateClaimsWrongIssuer()
    {
        $claims = [
            'iss' => 'https://evil.example.com',
            'aud' => 'my-client-id',
            'exp' => time() + 3600,
            'iat' => time(),
        ];

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid ID token');

        $this->createIdToken()->validateClaims($claims, 'https://provider.example.com', 'my-client-id');
    }

    public function testValidateClaimsMissingIssuer()
    {
        $claims = [
            'aud' => 'my-client-id',
            'exp' => time() + 3600,
            'iat' => time(),
        ];

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid ID token');

        $this->createIdToken()->validateClaims($claims, 'https://provider.example.com', 'my-client-id');
    }

    public function testValidateClaimsWrongAudience()
    {
        $claims = [
            'iss' => 'https://provider.example.com',
            'aud' => 'wrong-client-id',
            'exp' => time() + 3600,
            'iat' => time(),
        ];

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid ID token');

        $this->createIdToken()->validateClaims($claims, 'https://provider.example.com', 'my-client-id');
    }

    public function testValidateClaimsExpired()
    {
        $claims = [
            'iss' => 'https://provider.example.com',
            'aud' => 'my-client-id',
            'exp' => time() - 3600,
            'iat' => time(),
        ];

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid ID token');

        $this->createIdToken()->validateClaims($claims, 'https://provider.example.com', 'my-client-id');
    }

    public function testValidateClaimsMissingExp()
    {
        $claims = [
            'iss' => 'https://provider.example.com',
            'aud' => 'my-client-id',
            'iat' => time(),
        ];

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid ID token');

        $this->createIdToken()->validateClaims($claims, 'https://provider.example.com', 'my-client-id');
    }

    public function testValidateClaimsMissingIssuedAt()
    {
        $claims = [
            'iss' => 'https://provider.example.com',
            'aud' => 'my-client-id',
            'exp' => time() + 3600,
        ];

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid ID token');

        $this->createIdToken()->validateClaims($claims, 'https://provider.example.com', 'my-client-id');
    }

    #[DataProvider('provideFutureTimeClaims')]
    public function testValidateClaimsRejectsFutureTimeClaims(string $claim)
    {
        $claims = [
            'iss' => 'https://provider.example.com',
            'aud' => 'my-client-id',
            'exp' => 1750003600,
            'iat' => 1749999999,
        ];
        $claims[$claim] = 1750000001;

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid ID token');

        (new OidcIdToken(new MockClock('@1750000000')))->validateClaims($claims, 'https://provider.example.com', 'my-client-id');
    }

    public static function provideFutureTimeClaims(): iterable
    {
        yield 'issued at' => ['iat'];
        yield 'not before' => ['nbf'];
    }

    public function testValidateClaimsAllowsTimeDrift()
    {
        $claims = [
            'iss' => 'https://provider.example.com',
            'aud' => 'my-client-id',
            'exp' => 1750003600,
            'iat' => 1750000001,
            'nbf' => 1750000001,
        ];

        (new OidcIdToken(new MockClock('@1750000000'), 2))->validateClaims($claims, 'https://provider.example.com', 'my-client-id');

        $this->addToAssertionCount(1);
    }

    public function testValidateClaimsWrongNonce()
    {
        $claims = [
            'iss' => 'https://provider.example.com',
            'aud' => 'my-client-id',
            'exp' => time() + 3600,
            'iat' => time(),
            'nonce' => 'wrong-nonce',
        ];

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('nonce');

        $this->createIdToken()->validateClaims($claims, 'https://provider.example.com', 'my-client-id', 'expected-nonce');
    }

    public function testValidateClaimsMissingNonceWhenExpected()
    {
        $claims = [
            'iss' => 'https://provider.example.com',
            'aud' => 'my-client-id',
            'exp' => time() + 3600,
            'iat' => time(),
        ];

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('nonce');

        $this->createIdToken()->validateClaims($claims, 'https://provider.example.com', 'my-client-id', 'expected-nonce');
    }

    public function testValidateClaimsUsesTheInjectedClock()
    {
        $claims = [
            'iss' => 'https://provider.example.com',
            'aud' => 'my-client-id',
            'exp' => 1750000000,
            'iat' => 1749990000,
        ];

        // the token is still valid one second before it expires...
        (new OidcIdToken(new MockClock('@1749999999')))->validateClaims($claims, 'https://provider.example.com', 'my-client-id');

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('The token expired');

        // ...and expired one second after
        (new OidcIdToken(new MockClock('@1750000001')))->validateClaims($claims, 'https://provider.example.com', 'my-client-id');
    }

    private function createIdToken(): OidcIdToken
    {
        // any PSR-20 clock does: decode() never reads it
        return new OidcIdToken(new MockClock());
    }

    public function testValidateClaimsChecksMaxAgeWithTheInjectedClock()
    {
        $claims = [
            'iss' => 'https://provider.example.com',
            'aud' => 'my-client-id',
            'exp' => 1750003600,
            'iat' => 1750000000,
            'auth_time' => 1749999700,
        ];

        // the user authenticated exactly max_age seconds before the injected clock's now...
        (new OidcIdToken(new MockClock('@1750000000')))->validateClaims($claims, 'https://provider.example.com', 'my-client-id', null, 300);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('max_age');

        // ...and one second too early for a clock one second later
        (new OidcIdToken(new MockClock('@1750000001')))->validateClaims($claims, 'https://provider.example.com', 'my-client-id', null, 300);
    }

    public function testValidateClaimsMaxAgeAllowsTimeDrift()
    {
        $claims = [
            'iss' => 'https://provider.example.com',
            'aud' => 'my-client-id',
            'exp' => 1750003600,
            'iat' => 1750000000,
            'auth_time' => 1749999698,
        ];

        // 302 seconds elapsed, accepted with max_age 300 and a 2-second drift allowance
        (new OidcIdToken(new MockClock('@1750000000'), 2))->validateClaims($claims, 'https://provider.example.com', 'my-client-id', null, 300);

        $this->addToAssertionCount(1);
    }

    public function testValidateClaimsMissingAuthTimeWhenMaxAgeRequested()
    {
        $claims = [
            'iss' => 'https://provider.example.com',
            'aud' => 'my-client-id',
            'exp' => 1750003600,
            'iat' => 1750000000,
        ];

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('auth_time');

        (new OidcIdToken(new MockClock('@1750000000')))->validateClaims($claims, 'https://provider.example.com', 'my-client-id', null, 300);
    }

    public function testValidateClaimsRejectsANonNumericAuthTime()
    {
        $claims = [
            'iss' => 'https://provider.example.com',
            'aud' => 'my-client-id',
            'exp' => 1750003600,
            'iat' => 1750000000,
            'auth_time' => 'yesterday',
        ];

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('auth_time');

        (new OidcIdToken(new MockClock('@1750000000')))->validateClaims($claims, 'https://provider.example.com', 'my-client-id', null, 300);
    }

    private function buildJwt(array $claims = []): string
    {
        return (new CompactSerializer())->serialize(
            (new JWSBuilder(new AlgorithmManager([new ES256()])))
                ->withPayload(json_encode($claims))
                ->addSignature(self::getJWK(), ['alg' => 'ES256'])
                ->build()
        );
    }

    private static function getJWK(): JWK
    {
        // tip: use https://mkjwk.org/ to generate a JWK
        return new JWK([
            'kty' => 'EC',
            'crv' => 'P-256',
            'x' => '0QEAsI1wGI-dmYatdUZoWSRWggLEpyzopuhwk-YUnA4',
            'y' => 'KYl-qyZ26HobuYwlQh-r0iHX61thfP82qqEku7i0woo',
            'd' => 'iA_TV2zvftni_9aFAQwFO_9aypfJFCSpcCyevDvz220',
        ]);
    }
}
