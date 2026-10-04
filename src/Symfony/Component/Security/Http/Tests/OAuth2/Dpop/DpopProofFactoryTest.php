<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\OAuth2\Dpop;

use Jose\Component\Core\JWK;
use Jose\Component\Signature\Algorithm\ES256;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Security\Http\OAuth2\Dpop\DpopProofFactory;

#[RequiresPhpExtension('openssl')]
class DpopProofFactoryTest extends TestCase
{
    private const PRIVATE_JWK = [
        'kty' => 'EC',
        'crv' => 'P-256',
        'x' => '0QEAsI1wGI-dmYatdUZoWSRWggLEpyzopuhwk-YUnA4',
        'y' => 'KYl-qyZ26HobuYwlQh-r0iHX61thfP82qqEku7i0woo',
        'd' => 'iA_TV2zvftni_9aFAQwFO_9aypfJFCSpcCyevDvz220',
    ];

    /**
     * RFC 9449, Section 4.2: a proof says which request it was made for.
     */
    public function testNamesTheMethodAndTheUrlOfTheRequest()
    {
        $factory = $this->createFactory();

        $proof = $factory->createProof('post', 'https://provider.example.com/token');

        $claims = self::decodePayload($proof);
        $this->assertSame('POST', $claims['htm']);
        $this->assertSame('https://provider.example.com/token', $claims['htu']);
        $this->assertSame(strtotime('2026-09-23 10:00:00 UTC'), $claims['iat']);
        $this->assertNotEmpty($claims['jti']);
    }

    /**
     * The "htu" is the endpoint, not the parameters sent to it.
     */
    #[DataProvider('provideUrlsWithSomethingAfterThePath')]
    public function testLeavesTheQueryAndTheFragmentOutOfTheUrl(string $url)
    {
        $factory = $this->createFactory();

        $proof = $factory->createProof('GET', $url);

        $this->assertSame('https://provider.example.com/userinfo', self::decodePayload($proof)['htu']);
    }

    public static function provideUrlsWithSomethingAfterThePath(): iterable
    {
        yield 'query' => ['https://provider.example.com/userinfo?schema=openid'];
        yield 'fragment' => ['https://provider.example.com/userinfo#section'];
        yield 'both' => ['https://provider.example.com/userinfo?schema=openid#section'];
        yield 'neither' => ['https://provider.example.com/userinfo'];
    }

    /**
     * The header carries the public key, which is how the provider learns it.
     *
     * Nothing is registered for a DPoP key, so a private parameter leaking into the header
     * would hand the key to whoever reads the proof.
     */
    public function testCarriesThePublicKeyAndNeverThePrivateOne()
    {
        $factory = $this->createFactory();

        $proof = $factory->createProof('POST', 'https://provider.example.com/token');

        $header = self::decodeHeader($proof);
        $this->assertSame('dpop+jwt', $header['typ']);
        $this->assertSame('ES256', $header['alg']);
        $this->assertArrayNotHasKey('d', $header['jwk']);
        $this->assertSame(self::PRIVATE_JWK['x'], $header['jwk']['x']);
        $this->assertStringNotContainsString(self::PRIVATE_JWK['d'], $proof);
    }

    public function testCarriesNoOtherMemberOfTheKey()
    {
        // "oth" holds the other primes of a multi-prime RSA key (RFC 7518, Section 6.3.2.7) and "toPublic()" keeps it, like any member it does not know
        $key = new JWK(self::PRIVATE_JWK + ['kid' => 'key-1', 'oth' => [['r' => 'AQAB', 'd' => 'AQAB', 't' => 'AQAB']], 'x-secret' => 'hidden']);
        $proof = (new DpopProofFactory($key, 'ES256', new MockClock('2026-09-23 10:00:00')))->createProof('POST', 'https://provider.example.com/token');

        $this->assertEquals(['kty' => 'EC', 'crv' => 'P-256', 'x' => self::PRIVATE_JWK['x'], 'y' => self::PRIVATE_JWK['y']], self::decodeHeader($proof)['jwk']);
    }

    public function testSignsTheProofWithThePrivateKey()
    {
        $factory = $this->createFactory();

        $proof = $factory->createProof('POST', 'https://provider.example.com/token');

        [$header, $payload, $signature] = explode('.', $proof);
        $this->assertTrue((new ES256())->verify(new JWK(self::PRIVATE_JWK), $header.'.'.$payload, self::decodeBase64Url($signature)));
    }

    /**
     * Section 4.2: "ath" ties the proof to the very token the request presents.
     */
    public function testNamesTheDigestOfTheAccessTokenWhenOneIsPresented()
    {
        $factory = $this->createFactory();

        $proof = $factory->createProof('GET', 'https://provider.example.com/userinfo', 'an-access-token');

        $expected = rtrim(strtr(base64_encode(hash('sha256', 'an-access-token', true)), '+/', '-_'), '=');
        $this->assertSame($expected, self::decodePayload($proof)['ath']);
        $this->assertStringNotContainsString('an-access-token', $proof);
    }

    public function testCarriesNoDigestWhenNoAccessTokenIsPresented()
    {
        $factory = $this->createFactory();

        $proof = $factory->createProof('POST', 'https://provider.example.com/token');

        $this->assertArrayNotHasKey('ath', self::decodePayload($proof));
    }

    public function testCarriesTheNonceTheProviderNamed()
    {
        $factory = $this->createFactory();

        $proof = $factory->createProof('POST', 'https://provider.example.com/token', null, 'a-nonce-of-the-provider');

        $this->assertSame('a-nonce-of-the-provider', self::decodePayload($proof)['nonce']);
    }

    public function testEachProofIsSingleUse()
    {
        $factory = $this->createFactory();

        $first = self::decodePayload($factory->createProof('POST', 'https://provider.example.com/token'));
        $second = self::decodePayload($factory->createProof('POST', 'https://provider.example.com/token'));

        $this->assertNotSame($first['jti'], $second['jti']);
    }

    /**
     * RFC 7638, Section 3: the thumbprint names the key without carrying it.
     */
    public function testReportsTheThumbprintOfItsKey()
    {
        $factory = $this->createFactory();

        $thumbprint = $factory->getKeyThumbprint();

        $this->assertSame((new JWK(self::PRIVATE_JWK))->thumbprint('sha256'), $thumbprint);
        $this->assertNotSame('', $thumbprint);
    }

    /**
     * RFC 9449, Section 4.2 excludes symmetric algorithms.
     *
     * A shared secret proves possession to whoever shares it, so the provider would learn
     * nothing from a proof signed with one.
     */
    public function testRejectsAMacAlgorithm()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "HS256" algorithm cannot sign a DPoP proof. Use one of "RS256", "RS384", "RS512", "ES256", "ES384", "ES512", "PS256", "PS384", "PS512".');

        new DpopProofFactory(new JWK(self::PRIVATE_JWK), 'HS256');
    }

    public function testRejectsAPublicKey()
    {
        $publicKey = new JWK(array_diff_key(self::PRIVATE_JWK, ['d' => null]));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A DPoP proof must be signed with the private key of the client, and the given JWK has no "d" parameter: it is the public key.');

        new DpopProofFactory($publicKey);
    }

    /**
     * The key is read against the algorithm before anything is signed.
     *
     * The first proof is signed on the callback of a user who has already logged in at the
     * provider, so a key the algorithm cannot use is refused on the first request the
     * firewall handles rather than on that callback.
     */
    public function testRejectsAKeyOfTheWrongType()
    {
        $rsaKey = new JWK(['kty' => 'RSA', 'n' => 'xGkQ', 'e' => 'AQAB', 'd' => 'Vh6-Q']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "ES256" algorithm signs with a key of the "EC" type, and the given JWK is of the "RSA" type.');

        new DpopProofFactory($rsaKey, 'ES256');
    }

    public function testRejectsASymmetricKeyForWhatItIs()
    {
        $secret = new JWK(['kty' => 'oct', 'k' => 'c2VjcmV0']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "ES256" algorithm signs with a key of the "EC" type, and the given JWK is of the "oct" type.');

        new DpopProofFactory($secret, 'ES256');
    }

    /**
     * RFC 7518, Section 3.4 names a curve per algorithm, and "web-token/jwt-library" only
     * checks that an EC key carries one: a P-384 key would otherwise sign something that is
     * not a valid ES256 signature.
     */
    public function testRejectsAKeyOnTheWrongCurve()
    {
        $onP384 = new JWK(['kty' => 'EC', 'crv' => 'P-384'] + array_diff_key(self::PRIVATE_JWK, ['kty' => null, 'crv' => null]));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "ES256" algorithm signs with a key on the "P-256" curve (RFC 7518, Section 3.4), and the given JWK is on the "P-384" curve.');

        new DpopProofFactory($onP384, 'ES256');
    }

    /**
     * An RSA key signs under any of the RSA algorithms, which name no curve.
     */
    public function testAcceptsAnRsaKeyUnderAnRsaAlgorithm()
    {
        $rsaKey = new JWK(['kty' => 'RSA', 'n' => 'xGkQ', 'e' => 'AQAB', 'd' => 'Vh6-Q']);

        $factory = new DpopProofFactory($rsaKey, 'PS256');

        $this->assertSame($rsaKey->thumbprint('sha256'), $factory->getKeyThumbprint());
    }

    private function createFactory(): DpopProofFactory
    {
        return new DpopProofFactory(new JWK(self::PRIVATE_JWK), 'ES256', new MockClock('2026-09-23 10:00:00'));
    }

    private static function decodeHeader(string $proof): array
    {
        return json_decode(self::decodeBase64Url(explode('.', $proof)[0]), true, flags: \JSON_THROW_ON_ERROR);
    }

    private static function decodePayload(string $proof): array
    {
        return json_decode(self::decodeBase64Url(explode('.', $proof)[1]), true, flags: \JSON_THROW_ON_ERROR);
    }

    private static function decodeBase64Url(string $value): string
    {
        return base64_decode(strtr($value, '-_', '+/').str_repeat('=', 3 - (3 + \strlen($value)) % 4));
    }
}
