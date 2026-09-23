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
        // Given
        $factory = $this->createFactory();

        // When
        $proof = $factory->createProof('post', 'https://provider.example.com/token');

        // Then
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
        // Given
        $factory = $this->createFactory();

        // When
        $proof = $factory->createProof('GET', $url);

        // Then
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
        // Given
        $factory = $this->createFactory();

        // When
        $proof = $factory->createProof('POST', 'https://provider.example.com/token');

        // Then
        $header = self::decodeHeader($proof);
        $this->assertSame('dpop+jwt', $header['typ']);
        $this->assertSame('ES256', $header['alg']);
        $this->assertArrayNotHasKey('d', $header['jwk']);
        $this->assertSame(self::PRIVATE_JWK['x'], $header['jwk']['x']);
        $this->assertStringNotContainsString(self::PRIVATE_JWK['d'], $proof);
    }

    public function testSignsTheProofWithThePrivateKey()
    {
        // Given
        $factory = $this->createFactory();

        // When
        $proof = $factory->createProof('POST', 'https://provider.example.com/token');

        // Then
        [$header, $payload, $signature] = explode('.', $proof);
        $this->assertTrue((new ES256())->verify(new JWK(self::PRIVATE_JWK), $header.'.'.$payload, self::decodeBase64Url($signature)));
    }

    /**
     * Section 4.2: "ath" ties the proof to the very token the request presents.
     */
    public function testNamesTheDigestOfTheAccessTokenWhenOneIsPresented()
    {
        // Given
        $factory = $this->createFactory();

        // When
        $proof = $factory->createProof('GET', 'https://provider.example.com/userinfo', 'an-access-token');

        // Then
        $expected = rtrim(strtr(base64_encode(hash('sha256', 'an-access-token', true)), '+/', '-_'), '=');
        $this->assertSame($expected, self::decodePayload($proof)['ath']);
        $this->assertStringNotContainsString('an-access-token', $proof);
    }

    public function testCarriesNoDigestWhenNoAccessTokenIsPresented()
    {
        // Given
        $factory = $this->createFactory();

        // When
        $proof = $factory->createProof('POST', 'https://provider.example.com/token');

        // Then
        $this->assertArrayNotHasKey('ath', self::decodePayload($proof));
    }

    public function testCarriesTheNonceTheProviderNamed()
    {
        // Given
        $factory = $this->createFactory();

        // When
        $proof = $factory->createProof('POST', 'https://provider.example.com/token', null, 'a-nonce-of-the-provider');

        // Then
        $this->assertSame('a-nonce-of-the-provider', self::decodePayload($proof)['nonce']);
    }

    public function testEachProofIsSingleUse()
    {
        // Given
        $factory = $this->createFactory();

        // When
        $first = self::decodePayload($factory->createProof('POST', 'https://provider.example.com/token'));
        $second = self::decodePayload($factory->createProof('POST', 'https://provider.example.com/token'));

        // Then
        $this->assertNotSame($first['jti'], $second['jti']);
    }

    /**
     * RFC 7638, Section 3: the thumbprint names the key without carrying it.
     */
    public function testReportsTheThumbprintOfItsKey()
    {
        // Given
        $factory = $this->createFactory();

        // When
        $thumbprint = $factory->getKeyThumbprint();

        // Then
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
        // Then
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "HS256" algorithm cannot sign a DPoP proof. Use one of "RS256", "RS384", "RS512", "ES256", "ES384", "ES512", "PS256", "PS384", "PS512".');

        // When
        new DpopProofFactory(new JWK(self::PRIVATE_JWK), 'HS256');
    }

    public function testRejectsAPublicKey()
    {
        // Given
        $publicKey = new JWK(array_diff_key(self::PRIVATE_JWK, ['d' => null]));

        // Then
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A DPoP proof must be signed with the private key of the client, and the given JWK has no "d" parameter: it is the public key.');

        // When
        new DpopProofFactory($publicKey);
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
