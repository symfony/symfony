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
use Jose\Component\Signature\Algorithm\ES256;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Security\Http\OAuth2\ClientAuthentication\AbstractClientAssertion;
use Symfony\Component\Security\Http\OAuth2\ClientAuthentication\PrivateKeyJwt;

#[RequiresPhpExtension('openssl')]
class PrivateKeyJwtTest extends TestCase
{
    private const PRIVATE_JWK = [
        'kty' => 'EC',
        'crv' => 'P-256',
        'x' => '0QEAsI1wGI-dmYatdUZoWSRWggLEpyzopuhwk-YUnA4',
        'y' => 'KYl-qyZ26HobuYwlQh-r0iHX61thfP82qqEku7i0woo',
        'd' => 'iA_TV2zvftni_9aFAQwFO_9aypfJFCSpcCyevDvz220',
    ];

    /**
     * Another key pair entirely: an assertion signed with the key above does not verify against it.
     */
    private const FOREIGN_PUBLIC_JWK = [
        'kty' => 'EC',
        'crv' => 'P-256',
        'x' => 'N1aUu8Pd2WdClkpCQ4QCPnGjYe_bTmDgEaSoxy5LhTw',
        'y' => 'Yr1v-tCNxE8QgAGlartrJAi343bI8VlAaNvgCOp8Azs',
    ];

    public function testSendsTheAssertionInTheBodyAndLeavesTheRestOfItAlone()
    {
        $options = $this->createClientAuthentication()->authenticate('test-client-id', 'https://provider.example.com/token', ['body' => [
            'grant_type' => 'authorization_code',
            'client_id' => 'test-client-id',
        ]]);

        $this->assertSame('urn:ietf:params:oauth:client-assertion-type:jwt-bearer', $options['body']['client_assertion_type']);
        $this->assertSame(AbstractClientAssertion::ASSERTION_TYPE, $options['body']['client_assertion_type']);
        $this->assertSame('authorization_code', $options['body']['grant_type']);
        $this->assertSame('test-client-id', $options['body']['client_id']);
        $this->assertArrayNotHasKey('auth_basic', $options);
    }

    /**
     * RFC 7523, Section 3: the client is both the issuer and the subject of the assertion it
     * authenticates itself with, and the token endpoint the request goes to is the audience,
     * so that the provider it is sent to is the only one that can use it.
     */
    public function testNamesTheClientAsIssuerAndSubjectAndTheTokenEndpointAsAudience()
    {
        $options = $this->createClientAuthentication()->authenticate('test-client-id', 'https://provider.example.com/token', ['body' => []]);

        $claims = self::decodePayload($options['body']['client_assertion']);

        $this->assertSame('test-client-id', $claims['iss']);
        $this->assertSame('test-client-id', $claims['sub']);
        $this->assertSame('https://provider.example.com/token', $claims['aud']);
    }

    public function testSignsTheAssertionWithThePrivateKeyOfTheClient()
    {
        $options = $this->createClientAuthentication()->authenticate('test-client-id', 'https://provider.example.com/token', ['body' => []]);

        [$header, $payload, $signature] = explode('.', $options['body']['client_assertion']);

        $this->assertTrue((new ES256())->verify(new JWK(self::PRIVATE_JWK), $header.'.'.$payload, self::decodeBase64Url($signature)));
        $this->assertFalse((new ES256())->verify(new JWK(self::FOREIGN_PUBLIC_JWK), $header.'.'.$payload, self::decodeBase64Url($signature)));
        $this->assertSame(['alg' => 'ES256'], self::decodeHeader($options['body']['client_assertion']));
    }

    /**
     * OIDC Core 1.0, Section 10.1: a client that publishes several keys makes the provider
     * pick the right one by naming it, and one that publishes a single unnamed key sends
     * no "kid" at all rather than an empty one.
     */
    public function testSetsTheKeyIdentifierHeaderOnlyWhenTheKeyCarriesOne()
    {
        $named = new PrivateKeyJwt(new JWK(self::PRIVATE_JWK + ['kid' => 'client-signing-key']), 'ES256');
        $options = $named->authenticate('test-client-id', 'https://provider.example.com/token', ['body' => []]);

        $this->assertSame('client-signing-key', self::decodeHeader($options['body']['client_assertion'])['kid']);

        $options = $this->createClientAuthentication()->authenticate('test-client-id', 'https://provider.example.com/token', ['body' => []]);

        $this->assertArrayNotHasKey('kid', self::decodeHeader($options['body']['client_assertion']));
    }

    public function testDatesTheAssertionWithTheClockAndExpiresItAfterTheGivenLifetime()
    {
        $clientAuthentication = new PrivateKeyJwt(new JWK(self::PRIVATE_JWK), 'ES256', 30, new MockClock('2026-09-08 10:00:00'));

        $claims = self::decodePayload($clientAuthentication->authenticate('test-client-id', 'https://provider.example.com/token', ['body' => []])['body']['client_assertion']);

        $this->assertSame(strtotime('2026-09-08 10:00:00 UTC'), $claims['iat']);
        $this->assertSame(strtotime('2026-09-08 10:00:30 UTC'), $claims['exp']);
    }

    /**
     * RFC 7523, Section 3, item 7: the "jti" is what a provider rejects a replayed assertion
     * on, so two assertions must never share one, not even when they are built in the same
     * second for the same request.
     */
    public function testGivesEveryAssertionAnIdentifierOfItsOwn()
    {
        $clientAuthentication = new PrivateKeyJwt(new JWK(self::PRIVATE_JWK), 'ES256', 60, new MockClock('2026-09-08 10:00:00'));

        $first = self::decodePayload($clientAuthentication->authenticate('test-client-id', 'https://provider.example.com/token', ['body' => []])['body']['client_assertion']);
        $second = self::decodePayload($clientAuthentication->authenticate('test-client-id', 'https://provider.example.com/token', ['body' => []])['body']['client_assertion']);

        $this->assertNotEmpty($first['jti']);
        $this->assertNotSame($first['jti'], $second['jti']);
    }

    /**
     * A MAC algorithm would turn the shared secret of the provider into a signature only the
     * client is supposed to be able to make, which is the whole point of this method.
     */
    public function testRejectsAMacAlgorithm()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "HS256" algorithm cannot sign a "private_key_jwt" client assertion. Use one of "RS256", "RS384", "RS512", "ES256", "ES384", "ES512", "PS256", "PS384", "PS512".');

        new PrivateKeyJwt(new JWK(self::PRIVATE_JWK), 'HS256');
    }

    public function testRejectsAnUnknownAlgorithm()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "none" algorithm cannot sign a "private_key_jwt" client assertion.');

        new PrivateKeyJwt(new JWK(self::PRIVATE_JWK), 'none');
    }

    public function testRejectsAPublicKey()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('the given JWK has no "d" parameter: it is the public key');

        new PrivateKeyJwt(new JWK(self::FOREIGN_PUBLIC_JWK), 'ES256');
    }

    public function testRejectsALifetimeThatIsNotPositive()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The lifetime of an OAuth2 client assertion must be a positive number of seconds, got 0.');

        new PrivateKeyJwt(new JWK(self::PRIVATE_JWK), 'ES256', 0);
    }

    public function testReportsItsMethod()
    {
        $this->assertSame('private_key_jwt', $this->createClientAuthentication()->getMethod());
    }

    private function createClientAuthentication(): PrivateKeyJwt
    {
        return new PrivateKeyJwt(new JWK(self::PRIVATE_JWK), 'ES256');
    }

    private static function decodeHeader(string $assertion): array
    {
        return json_decode(self::decodeBase64Url(explode('.', $assertion)[0]), true, flags: \JSON_THROW_ON_ERROR);
    }

    private static function decodePayload(string $assertion): array
    {
        return json_decode(self::decodeBase64Url(explode('.', $assertion)[1]), true, flags: \JSON_THROW_ON_ERROR);
    }

    private static function decodeBase64Url(string $value): string
    {
        return base64_decode(strtr($value, '-_', '+/'));
    }
}
