<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\AccessToken\Dpop;

use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\Signature\Algorithm\ES256;
use Jose\Component\Signature\Algorithm\RS256;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\CompactSerializer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\AccessToken\Dpop\DpopSenderConstraint;
use Symfony\Component\Security\Http\AccessToken\Dpop\Exception\InvalidDpopProofException;

#[RequiresPhpExtension('openssl')]
class DpopSenderConstraintTest extends TestCase
{
    private const ACCESS_TOKEN = 'an.access.token';

    /**
     * tip: use https://mkjwk.org/ to generate a JWK.
     */
    private const PRIVATE_JWK = [
        'kty' => 'EC',
        'crv' => 'P-256',
        'x' => '0QEAsI1wGI-dmYatdUZoWSRWggLEpyzopuhwk-YUnA4',
        'y' => 'KYl-qyZ26HobuYwlQh-r0iHX61thfP82qqEku7i0woo',
        'd' => 'iA_TV2zvftni_9aFAQwFO_9aypfJFCSpcCyevDvz220',
    ];

    /**
     * Another key pair entirely: a proof signed with the key above is not a proof of possession of this one.
     */
    private const FOREIGN_PRIVATE_JWK = [
        'kty' => 'EC',
        'crv' => 'P-256',
        'x' => 'N1aUu8Pd2WdClkpCQ4QCPnGjYe_bTmDgEaSoxy5LhTw',
        'y' => 'Yr1v-tCNxE8QgAGlartrJAi343bI8VlAaNvgCOp8Azs',
        'd' => 'lLB9Zb0-ZBKkqfBiS0-XzRqTZ2Nl_SN7-Wd1oA5dLzM',
    ];

    private MockClock $clock;
    private ArrayAdapter $cache;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-09-22 10:00:00');
        // the pool is handed the same clock, so that a proof stops being remembered when it stops standing
        $this->cache = new ArrayAdapter(0, true, 0, 0, $this->clock);
    }

    public function testItAcceptsAProofMadeForTheRequestThatCarriesIt()
    {
        $request = $this->createRequest($this->createProof());

        $this->createConstraint()->check($request, self::ACCESS_TOKEN, $this->claimsBoundTo(self::PRIVATE_JWK));

        $this->expectNotToPerformAssertions();
    }

    public function testItRefusesARequestCarryingNoProof()
    {
        $this->expectException(InvalidDpopProofException::class);
        $this->expectExceptionMessage('A request presenting a DPoP-bound access token carries exactly one DPoP proof');

        $this->createConstraint()->check(Request::create('https://api.example.com/me'), self::ACCESS_TOKEN, $this->claimsBoundTo(self::PRIVATE_JWK));
    }

    public function testItRefusesARequestCarryingTwoProofs()
    {
        $request = $this->createRequest($this->createProof());
        $request->headers->set('DPoP', [$this->createProof(), $this->createProof(['jti' => 'another'])]);

        $this->expectException(InvalidDpopProofException::class);
        $this->expectExceptionMessage('carries exactly one DPoP proof');

        $this->createConstraint()->check($request, self::ACCESS_TOKEN, $this->claimsBoundTo(self::PRIVATE_JWK));
    }

    public function testItRefusesAProofThatIsNotACompactJws()
    {
        $this->expectException(InvalidDpopProofException::class);
        $this->expectExceptionMessage('not a compact JWS');

        $this->createConstraint()->check($this->createRequest('not-a-jws'), self::ACCESS_TOKEN, $this->claimsBoundTo(self::PRIVATE_JWK));
    }

    public function testItRefusesAJwtThatIsNotTypedAsAProof()
    {
        $proof = $this->createProof(header: ['typ' => 'JWT']);

        $this->expectException(InvalidDpopProofException::class);
        $this->expectExceptionMessage('is a JWT of type "dpop+jwt"');

        $this->createConstraint()->check($this->createRequest($proof), self::ACCESS_TOKEN, $this->claimsBoundTo(self::PRIVATE_JWK));
    }

    public function testItRefusesAProofSignedWithAnAlgorithmItIsNotWiredWith()
    {
        $constraint = new DpopSenderConstraint(new AlgorithmManager([new RS256()]), $this->cache, $this->clock);

        $this->expectException(InvalidDpopProofException::class);
        $this->expectExceptionMessage('algorithm this resource server does not accept');

        $constraint->check($this->createRequest($this->createProof()), self::ACCESS_TOKEN, $this->claimsBoundTo(self::PRIVATE_JWK));
    }

    public function testItRefusesAProofCarryingNoKey()
    {
        $proof = $this->createProof(header: ['jwk' => null]);

        $this->expectException(InvalidDpopProofException::class);
        $this->expectExceptionMessage('carries no public key in its header');

        $this->createConstraint()->check($this->createRequest($proof), self::ACCESS_TOKEN, $this->claimsBoundTo(self::PRIVATE_JWK));
    }

    public function testItRefusesAProofCarryingAPrivateKey()
    {
        $proof = $this->createProof(header: ['jwk' => self::PRIVATE_JWK]);

        $this->expectException(InvalidDpopProofException::class);
        $this->expectExceptionMessage('is not a public key');

        $this->createConstraint()->check($this->createRequest($proof), self::ACCESS_TOKEN, $this->claimsBoundTo(self::PRIVATE_JWK));
    }

    public function testItRefusesAProofCarryingAKeyThatIsNotOne()
    {
        // a key with no "kty" is not a key the library can make anything of, and it comes from the request
        $proof = $this->createProof(header: ['jwk' => ['use' => 'sig', 'alg' => 'ES256']]);

        $this->expectException(InvalidDpopProofException::class);
        $this->expectExceptionMessage('cannot be read');

        $this->createConstraint()->check($this->createRequest($proof), self::ACCESS_TOKEN, $this->claimsBoundTo(self::PRIVATE_JWK));
    }

    public function testItRefusesAProofSignedByAnotherKeyThanTheOneItCarries()
    {
        // the key of the header is the one the token is bound to, the signature comes from another pair
        $proof = $this->createProof(key: self::FOREIGN_PRIVATE_JWK, header: ['jwk' => $this->publicKey(self::PRIVATE_JWK)]);

        $this->expectException(InvalidDpopProofException::class);
        $this->expectExceptionMessage('not signed by the key it carries');

        $this->createConstraint()->check($this->createRequest($proof), self::ACCESS_TOKEN, $this->claimsBoundTo(self::PRIVATE_JWK));
    }

    public function testItRefusesAProofMadeForAnotherMethod()
    {
        $proof = $this->createProof(['htm' => 'POST']);

        $this->expectException(InvalidDpopProofException::class);
        $this->expectExceptionMessage('"htm" of the DPoP proof is not the method of this request');

        $this->createConstraint()->check($this->createRequest($proof), self::ACCESS_TOKEN, $this->claimsBoundTo(self::PRIVATE_JWK));
    }

    public function testItRefusesAProofMadeForAnotherUrl()
    {
        $proof = $this->createProof(['htu' => 'https://api.example.com/admin']);

        $this->expectException(InvalidDpopProofException::class);
        $this->expectExceptionMessage('"htu" of the DPoP proof is not the URL of this request');

        $this->createConstraint()->check($this->createRequest($proof), self::ACCESS_TOKEN, $this->claimsBoundTo(self::PRIVATE_JWK));
    }

    /**
     * RFC 9449, Section 4.3: the query and the fragment are no part of the URL a proof names, and the
     * port the scheme implies is no part of it either.
     */
    #[DataProvider('provideUrlsNamingTheSameEndpoint')]
    public function testItAcceptsAProofNamingTheEndpointAnotherWay(string $url)
    {
        $request = $this->createRequest($this->createProof(['htu' => $url]));

        $this->createConstraint()->check($request, self::ACCESS_TOKEN, $this->claimsBoundTo(self::PRIVATE_JWK));

        $this->expectNotToPerformAssertions();
    }

    public static function provideUrlsNamingTheSameEndpoint(): iterable
    {
        yield 'a query the client appended' => ['https://api.example.com/me?fields=sub'];
        yield 'a fragment' => ['https://api.example.com/me#here'];
        yield 'the port the scheme implies' => ['https://api.example.com:443/me'];
        yield 'an upper-case host' => ['https://API.example.com/me'];
    }

    public function testItRefusesAProofMadeLaterThanTheRequest()
    {
        $proof = $this->createProof(['iat' => $this->clock->now()->getTimestamp() + 30]);

        $this->expectException(InvalidDpopProofException::class);
        $this->expectExceptionMessage('made later than the request that carries it');

        $this->createConstraint()->check($this->createRequest($proof), self::ACCESS_TOKEN, $this->claimsBoundTo(self::PRIVATE_JWK));
    }

    public function testItRefusesAProofOlderThanItAccepts()
    {
        $proof = $this->createProof(['iat' => $this->clock->now()->getTimestamp() - 66]);

        $this->expectException(InvalidDpopProofException::class);
        $this->expectExceptionMessage('older than this resource server accepts');

        $this->createConstraint()->check($this->createRequest($proof), self::ACCESS_TOKEN, $this->claimsBoundTo(self::PRIVATE_JWK));
    }

    public function testItRefusesAProofCarryingNoIssuedAt()
    {
        $proof = $this->createProof(['iat' => '2026-09-22T10:00:00Z']);

        $this->expectException(InvalidDpopProofException::class);
        $this->expectExceptionMessage('carries no "iat"');

        $this->createConstraint()->check($this->createRequest($proof), self::ACCESS_TOKEN, $this->claimsBoundTo(self::PRIVATE_JWK));
    }

    public function testItRefusesAProofNamingNoAccessToken()
    {
        $proof = $this->createProof(['ath' => null]);

        $this->expectException(InvalidDpopProofException::class);
        $this->expectExceptionMessage('names it in its "ath"');

        $this->createConstraint()->check($this->createRequest($proof), self::ACCESS_TOKEN, $this->claimsBoundTo(self::PRIVATE_JWK));
    }

    public function testItRefusesAProofNamingAnotherAccessToken()
    {
        $proof = $this->createProof(['ath' => $this->hashOf('another.access.token')]);

        $this->expectException(InvalidDpopProofException::class);
        $this->expectExceptionMessage('"ath" of the DPoP proof is not the access token this request presents');

        $this->createConstraint()->check($this->createRequest($proof), self::ACCESS_TOKEN, $this->claimsBoundTo(self::PRIVATE_JWK));
    }

    public function testItRefusesAProofCarryingNoIdentifier()
    {
        $proof = $this->createProof(['jti' => null]);

        $this->expectException(InvalidDpopProofException::class);
        $this->expectExceptionMessage('carries no "jti"');

        $this->createConstraint()->check($this->createRequest($proof), self::ACCESS_TOKEN, $this->claimsBoundTo(self::PRIVATE_JWK));
    }

    public function testItRefusesAProofItAlreadySaw()
    {
        $proof = $this->createProof();
        $constraint = $this->createConstraint();
        $constraint->check($this->createRequest($proof), self::ACCESS_TOKEN, $this->claimsBoundTo(self::PRIVATE_JWK));

        $this->expectException(InvalidDpopProofException::class);
        $this->expectExceptionMessage('was presented before');

        $constraint->check($this->createRequest($proof), self::ACCESS_TOKEN, $this->claimsBoundTo(self::PRIVATE_JWK));
    }

    public function testItAcceptsTheSameIdentifierOnceTheProofStoppedStanding()
    {
        $proof = $this->createProof();
        $constraint = $this->createConstraint();
        $constraint->check($this->createRequest($proof), self::ACCESS_TOKEN, $this->claimsBoundTo(self::PRIVATE_JWK));

        $this->clock->sleep(120);
        $refreshed = $this->createProof();

        $constraint->check($this->createRequest($refreshed), self::ACCESS_TOKEN, $this->claimsBoundTo(self::PRIVATE_JWK));

        $this->expectNotToPerformAssertions();
    }

    public function testItRefusesATokenBoundToNothing()
    {
        $this->expectException(BadCredentialsException::class);
        $this->expectExceptionMessage('The access token is not bound to a key');

        $this->createConstraint()->check($this->createRequest($this->createProof()), self::ACCESS_TOKEN, ['sub' => 'user-42']);
    }

    public function testItRefusesATokenBoundToAnotherKey()
    {
        $this->expectException(BadCredentialsException::class);
        $this->expectExceptionMessage('bound to another key than the one the DPoP proof was signed with');

        $this->createConstraint()->check($this->createRequest($this->createProof()), self::ACCESS_TOKEN, $this->claimsBoundTo(self::FOREIGN_PRIVATE_JWK));
    }

    public function testTheChallengeNamesTheSchemeAndTheAlgorithmsAProofMayBeSignedWith()
    {
        $this->assertSame(['DPoP', ['algs' => 'ES256 RS256']], (new DpopSenderConstraint(new AlgorithmManager([new ES256(), new RS256()]), $this->cache, $this->clock))->getChallenge(null));
    }

    public function testTheChallengeTellsAnInvalidProofFromAnInvalidToken()
    {
        $constraint = $this->createConstraint();

        $this->assertSame('invalid_dpop_proof', $constraint->getChallenge(new InvalidDpopProofException())[1]['error']);
        $this->assertArrayNotHasKey('error', $constraint->getChallenge(new BadCredentialsException())[1]);
    }

    private function createConstraint(): DpopSenderConstraint
    {
        return new DpopSenderConstraint(new AlgorithmManager([new ES256()]), $this->cache, $this->clock);
    }

    private function createRequest(string $proof): Request
    {
        $request = Request::create('https://api.example.com/me');
        $request->headers->set('DPoP', $proof);

        return $request;
    }

    /**
     * @param array<string, mixed> $claims the claims replacing those of a proof made for the request above
     * @param array<string, mixed> $header the header members replacing those of that proof
     */
    private function createProof(array $claims = [], array $header = [], array $key = self::PRIVATE_JWK): string
    {
        $claims = array_filter([
            'jti' => bin2hex(random_bytes(16)),
            'htm' => 'GET',
            'htu' => 'https://api.example.com/me',
            'iat' => $this->clock->now()->getTimestamp(),
            'ath' => $this->hashOf(self::ACCESS_TOKEN),
            ...$claims,
        ], static fn ($value) => null !== $value);

        $header = array_filter([
            'typ' => 'dpop+jwt',
            'alg' => 'ES256',
            'jwk' => $this->publicKey($key),
            ...$header,
        ], static fn ($value) => null !== $value);

        return (new CompactSerializer())->serialize(
            (new JWSBuilder(new AlgorithmManager([new ES256()])))
                ->withPayload(json_encode($claims))
                ->addSignature(new JWK($key), $header)
                ->build()
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function publicKey(array $key): array
    {
        return (new JWK($key))->toPublic()->all();
    }

    /**
     * The "cnf" of an access token bound to the given key (RFC 7800, Section 3.1).
     *
     * @return array<string, mixed>
     */
    private function claimsBoundTo(array $key): array
    {
        return ['sub' => 'user-42', 'cnf' => ['jkt' => (new JWK($key))->toPublic()->thumbprint('sha256')]];
    }

    private function hashOf(string $accessToken): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $accessToken, true)), '+/', '-_'), '=');
    }
}
