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
use Jose\Component\Signature\Algorithm\PS256;
use Jose\Component\Signature\Algorithm\RS256;
use Jose\Component\Signature\Algorithm\SignatureAlgorithm;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\CompactSerializer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\CacheItem;
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

    private const RSA_PRIVATE_JWK = [
        'kty' => 'RSA',
        'n' => 'urwfndKKeSPAVRghWFQXfjtHdNm9CkCe7i8-52ct0VT8qqfIenKnblV_wUfUO2YA2PoBh6NUgCnd5GXJA0FY28F6HAH-T6OTJwdlo8D3aA4BCFa9B1Bl0Me4hGMAXTMYA3VPygPriTL8QHg6A8YwN2vtod-LwHKAOP9MVVLDTmbYKmeztZ7UKv0U5l1ZTFe4F0Z5D5copLylwA9HIj7qyUvSh0cEnw8MrkX5oMnYBgJ2i4l88wcQm8lyTWi7_Qtc51gX9jVuHpJPSJleuNPbU2B62HKnfjBjuXrHMujrqrjXe8izthojO3K0Q5-zQNdLtThmPDUZKZcby77VcBibpQ',
        'e' => 'AQAB',
        'd' => 'DZzQKzeha5JNH4bqS1-Z9NRKcby7uKccTt231rOhVey9nI0toquABB2MeiEL2ial8itLeEFSVCN5LwCyhh-55A83IRqOGO0iOYqjAKx8_KNktOBp2EImofElRNVYvfKPEhV3sxDVF0PVurdo90R32FpwUMgUjntqWH1X6bJmVW0u7twgiSBQcUxIYxf_W58S_l413cW7OFpEhJArDW3lneI-rmerjKw2SpUWdzBKWPW5l7DRv-YtxJI7lL8TIp5t_Q8M2szQioaGExriTvho8ZQ3kCtwSGKkaoGw-R08_hfQv5hiIv8j4Cq1vLIjH4TrAMK_44fzBwIqarjrsNRUUw',
        'p' => '6ApcDo3YS09omIIHSRUZtDVC7tMshguQHv8mIKpnRzoYr5zogBbTDeuBnhudOdeBHDX3pgZ-HI0WeljxuR6GZKHiP3L0XIc6kqAEATXgfCuqcyMI-yZ_dhy1_E6--fFxogBoRTtYjXw5l12AfnjSEoyLH_LnExiH6a5blCx6DBs',
        'q' => 'zgQtpm2GGHVNSfm8aY_EerWpZfXQT-m2TJHhX9M7wlQhV8TCxtn2qKJ9JCA1FRKA5QP3FzazJGdvXRHtksKSVVkmi5mkt7Y0NH5Ot_8stjQpLPS5CM_inJWpu8OZlPOCDZ6ui5vv3K0pGKTc2flXvgMZv5YuMP12wHfR_fLW8z8',
        'dp' => 'WE9JBcj6Hn9UQWaLO0cqhykWFiqMcV-JNCLK8feoYrU7m-pNmiIVpOCSbgf4aIEaBBCN9cPWzyrq-RlMtbVmVHo_zwIC-gwFQLRhkcxdaSR9NGCLFHpsXBlFMYcOq7U2F_-rr12MsBwtFuxg8MEuwhx2_mvFgcD-SPMwq03aDS0',
        'dq' => 'bdWUcPJbV5bSSeo3JSBc4_8N_Sn8kV5Mrq3ntirr5ULtM9nW7iUud-GJTkVNh5qB1ZczmNC02We726a_Pks1sQMCpdKYGsmtM39TgI0uzUUp3qnqLGSIsGqqP0ydCOh6IcDD1fKhAsne6yCaDiv9N3R3ucjbf4riUOKQtg9Nwgk',
        'qi' => 'IyXxQu7ddl4KYpCgZPNLJ6Vqde9JfwJELomIBD36stNUdPdGCn2vWZ_oHHPsk1cqHrPuMsMhg-PoMl15KvyYmUiSGWRejwQrZfVEV0qtC_wYi6ugctnUHNum5PuD2WBx__i1Efc3WZIcOXaDPlzt3MSUFCr184JjZy_5_FYgo9c',
    ];

    private MockClock $clock;
    private ArrayAdapter $cache;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-09-22 10:00:00');
        // the pool reads the real time and not the clock, as the pools of the framework do
        $this->cache = new ArrayAdapter();
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

    #[DataProvider('provideKeysCarryingPrivateMaterial')]
    public function testItRefusesAProofCarryingAPrivateKey(array $key)
    {
        $proof = $this->createProof(header: ['jwk' => $key]);

        $this->expectException(InvalidDpopProofException::class);
        $this->expectExceptionMessage('is not a public key');

        $this->createConstraint()->check($this->createRequest($proof), self::ACCESS_TOKEN, $this->claimsBoundTo(self::PRIVATE_JWK));
    }

    public static function provideKeysCarryingPrivateMaterial(): iterable
    {
        yield 'the "d" of an EC key' => [self::PRIVATE_JWK];
        yield 'the "oth" of a multi-prime RSA key' => [['kty' => 'RSA', 'n' => 'AQAB', 'e' => 'AQAB', 'oth' => [['r' => 'AQAB', 'd' => 'AQAB', 't' => 'AQAB']]]];
        yield 'the "priv" of an AKP key' => [['kty' => 'AKP', 'alg' => 'ML-DSA-44', 'pub' => 'AQAB', 'priv' => 'AQAB']];
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

    #[Group('time-sensitive')]
    public function testItReadsTheTimeWithoutAClock()
    {
        ClockMock::withClockMock($this->clock->now()->getTimestamp());

        $constraint = new DpopSenderConstraint(new AlgorithmManager([new ES256()]), $this->cache);
        $constraint->check($this->createRequest($this->createProof()), self::ACCESS_TOKEN, $this->claimsBoundTo(self::PRIVATE_JWK));

        $this->expectNotToPerformAssertions();
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

    #[DataProvider('provideIssuedAtNoTimestampHolds')]
    public function testItRefusesAProofIssuedAtAMomentNoTimestampHolds(float $issuedAt, string $message)
    {
        $proof = $this->createProof(['iat' => $issuedAt]);

        $this->expectException(InvalidDpopProofException::class);
        $this->expectExceptionMessage($message);

        $this->createConstraint()->check($this->createRequest($proof), self::ACCESS_TOKEN, $this->claimsBoundTo(self::PRIVATE_JWK));
    }

    public static function provideIssuedAtNoTimestampHolds(): iterable
    {
        yield 'far in the future' => [1e30, 'made later than the request that carries it'];
        yield 'far in the past' => [-1e30, 'older than this resource server accepts'];
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

    /**
     * A pool that cannot write is a pool that no longer tells a replay from a first use.
     */
    public function testItRefusesAProofThatCouldNotBeWrittenDown()
    {
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn(new CacheItem());
        $cache->expects($this->once())->method('save')->willReturn(false);

        $constraint = new DpopSenderConstraint(new AlgorithmManager([new ES256()]), $cache, $this->clock);

        $this->expectException(InvalidDpopProofException::class);
        $this->expectExceptionMessage('could not be remembered');

        $constraint->check($this->createRequest($this->createProof()), self::ACCESS_TOKEN, $this->claimsBoundTo(self::PRIVATE_JWK));
    }

    public function testItRemembersAProofForAsLongAsItStands()
    {
        $item = $this->createMock(CacheItemInterface::class);
        $item->method('set')->willReturnSelf();
        $item->expects($this->once())->method('expiresAfter')->with(35)->willReturnSelf();
        $cache = $this->createStub(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn($item);
        $cache->method('save')->willReturn(true);

        $constraint = new DpopSenderConstraint(new AlgorithmManager([new ES256()]), $cache, $this->clock);
        $proof = $this->createProof(['iat' => $this->clock->now()->getTimestamp() - 30]);

        $constraint->check($this->createRequest($proof), self::ACCESS_TOKEN, $this->claimsBoundTo(self::PRIVATE_JWK));
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

    #[DataProvider('provideTokensNotBoundToTheKeyOfTheProof')]
    public function testItVerifiesNoSignatureWithAKeyTheTokenIsNotBoundTo(array $claims, string $message)
    {
        $algorithm = new class implements SignatureAlgorithm {
            public int $verifications = 0;

            public function name(): string
            {
                return 'ES256';
            }

            public function allowedKeyTypes(): array
            {
                return ['EC'];
            }

            public function sign(JWK $key, string $input): string
            {
                return (new ES256())->sign($key, $input);
            }

            public function verify(JWK $key, string $input, string $signature): bool
            {
                ++$this->verifications;

                return (new ES256())->verify($key, $input, $signature);
            }
        };
        $constraint = new DpopSenderConstraint(new AlgorithmManager([$algorithm]), $this->cache, $this->clock);

        try {
            $constraint->check($this->createRequest($this->createProof()), self::ACCESS_TOKEN, $claims);
            $this->fail('The proof should have been refused.');
        } catch (BadCredentialsException $e) {
            $this->assertStringContainsString($message, $e->getMessage());
        }

        $this->assertSame(0, $algorithm->verifications);
    }

    public static function provideTokensNotBoundToTheKeyOfTheProof(): iterable
    {
        yield 'a token bound to nothing' => [['sub' => 'user-42'], 'not bound to a key'];
        yield 'a token bound to another key' => [self::claimsBoundTo(self::FOREIGN_PRIVATE_JWK), 'bound to another key'];
    }

    public function testItAcceptsAProofSignedWithAnRsaKeyOfTheSizeClientsGenerate()
    {
        $this->createRsaConstraint()->check($this->createRequest($this->createProof(header: ['alg' => 'RS256'], key: self::RSA_PRIVATE_JWK)), self::ACCESS_TOKEN, self::claimsBoundTo(self::RSA_PRIVATE_JWK));

        $this->expectNotToPerformAssertions();
    }

    /**
     * The proof carries no valid signature: an RSA key of an accepted size gets as far as having it checked.
     */
    #[DataProvider('provideRsaKeysOfAnAcceptedSize')]
    public function testItVerifiesTheSignatureWithAnRsaKeyOfAnAcceptedSize(string $modulus, string $exponent)
    {
        $key = ['kty' => 'RSA', 'n' => self::base64UrlEncode($modulus), 'e' => self::base64UrlEncode($exponent)];

        $this->expectException(InvalidDpopProofException::class);
        $this->expectExceptionMessage('not signed by the key it carries');

        $this->createRsaConstraint()->check($this->createRequest($this->createUnsignedProof($key)), self::ACCESS_TOKEN, self::claimsBoundTo($key));
    }

    public static function provideRsaKeysOfAnAcceptedSize(): iterable
    {
        yield '2048 bits, the smallest modulus' => ["\x80".str_repeat("\xa5", 254)."\x01", "\x01\x00\x01"];
        yield '4096 bits, the largest modulus' => ["\xff".str_repeat("\xa5", 510)."\x01", "\x01\x00\x01"];
        yield 'a leading zero byte' => ["\x00\x80".str_repeat("\xa5", 254)."\x01", "\x00\x01\x00\x01"];
        yield 'an exponent of 3' => ["\x80".str_repeat("\xa5", 254)."\x01", "\x03"];
        yield 'an exponent of 65535' => ["\x80".str_repeat("\xa5", 254)."\x01", "\xff\xff"];
        yield 'an exponent of 33 bits, the largest' => ["\x80".str_repeat("\xa5", 254)."\x01", "\x01\xff\xff\xff\xff"];
    }

    /**
     * The key is checked whatever the algorithm, RSASSA-PSS verifying in PHP at a cost the size of the key sets.
     */
    #[DataProvider('provideRsaKeysClientsDoNotGenerate')]
    public function testItRefusesAProofCarryingAnRsaKeyClientsDoNotGenerate(array $key, string $message)
    {
        $this->expectException(InvalidDpopProofException::class);
        $this->expectExceptionMessage($message);

        $this->createRsaConstraint()->check($this->createRequest($this->createUnsignedProof($key)), self::ACCESS_TOKEN, self::claimsBoundTo($key));
    }

    public static function provideRsaKeysClientsDoNotGenerate(): iterable
    {
        $modulus = self::base64UrlEncode("\x80".str_repeat("\xa5", 254)."\x01");

        yield 'a modulus of 2047 bits' => [['kty' => 'RSA', 'n' => self::base64UrlEncode("\x7f".str_repeat("\xa5", 254)."\x01"), 'e' => 'AQAB'], 'has a modulus of 2047 bits'];
        yield 'a modulus of 4097 bits' => [['kty' => 'RSA', 'n' => self::base64UrlEncode("\x01".str_repeat("\xa5", 511)."\x01"), 'e' => 'AQAB'], 'has a modulus of 4097 bits'];
        yield 'a modulus of 16384 bits' => [['kty' => 'RSA', 'n' => self::base64UrlEncode("\xff".str_repeat("\xa5", 2046)."\x01"), 'e' => 'AQAB'], 'has a modulus of 16384 bits'];
        yield 'an exponent of 34 bits' => [['kty' => 'RSA', 'n' => $modulus, 'e' => self::base64UrlEncode("\x02\x00\x00\x00\x01")], 'must be odd, greater than 1 and at most 33 bits long'];
        yield 'an exponent of 64 bits' => [['kty' => 'RSA', 'n' => $modulus, 'e' => self::base64UrlEncode(str_repeat("\xff", 8))], 'must be odd, greater than 1 and at most 33 bits long'];
        yield 'an even exponent' => [['kty' => 'RSA', 'n' => $modulus, 'e' => self::base64UrlEncode("\x01\x00\x00")], 'must be odd, greater than 1 and at most 33 bits long'];
        yield 'an exponent of 1' => [['kty' => 'RSA', 'n' => $modulus, 'e' => self::base64UrlEncode("\x01")], 'must be odd, greater than 1 and at most 33 bits long'];
        yield 'no modulus' => [['kty' => 'RSA', 'e' => 'AQAB'], 'cannot be read'];
        yield 'a modulus that is not base64url' => [['kty' => 'RSA', 'n' => '!'.$modulus, 'e' => 'AQAB'], 'cannot be read'];
        yield 'a modulus of zeros' => [['kty' => 'RSA', 'n' => 'AAAA', 'e' => 'AQAB'], 'cannot be read'];
        yield 'no exponent' => [['kty' => 'RSA', 'n' => $modulus], 'cannot be read'];
    }

    public function testItAcceptsRsassaPssWithGmpOrBcMath()
    {
        if (!\extension_loaded('gmp') && !\extension_loaded('bcmath')) {
            $this->markTestSkipped('Neither the GMP nor the BCMath extension is loaded.');
        }

        $constraint = new DpopSenderConstraint(new AlgorithmManager([new ES256(), new PS256()]), $this->cache, $this->clock);

        $this->assertSame(['DPoP', ['algs' => 'ES256 PS256']], $constraint->getChallenge(null));
    }

    public function testItRefusesRsassaPssWithoutGmpOrBcMath()
    {
        if (\extension_loaded('gmp') || \extension_loaded('bcmath')) {
            $this->markTestSkipped('The GMP or the BCMath extension is loaded.');
        }

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The "PS256" algorithm verifies a DPoP proof in PHP, which takes seconds per request without the GMP or BCMath extension.');

        new DpopSenderConstraint(new AlgorithmManager([new ES256(), new PS256()]), $this->cache, $this->clock);
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

    private function createRsaConstraint(): DpopSenderConstraint
    {
        return new DpopSenderConstraint(new AlgorithmManager([new RS256()]), $this->cache, $this->clock);
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
        $claims = $this->createClaims($claims);

        $header = array_filter([
            'typ' => 'dpop+jwt',
            'alg' => 'ES256',
            'jwk' => $this->publicKey($key),
            ...$header,
        ], static fn ($value) => null !== $value);

        return (new CompactSerializer())->serialize(
            (new JWSBuilder(new AlgorithmManager([new ES256(), new RS256()])))
                ->withPayload(json_encode($claims))
                ->addSignature(new JWK($key), $header)
                ->build()
        );
    }

    /**
     * A proof carrying the given public key, under an RS256 signature no private key made.
     *
     * @param array<string, mixed> $key
     */
    private function createUnsignedProof(array $key): string
    {
        $header = ['typ' => 'dpop+jwt', 'alg' => 'RS256', 'jwk' => $key];

        return self::base64UrlEncode(json_encode($header)).'.'.self::base64UrlEncode(json_encode($this->createClaims())).'.'.self::base64UrlEncode(random_bytes(256));
    }

    /**
     * @param array<string, mixed> $claims the claims replacing those of a proof made for the request above
     *
     * @return array<string, mixed>
     */
    private function createClaims(array $claims = []): array
    {
        return array_filter([
            'jti' => bin2hex(random_bytes(16)),
            'htm' => 'GET',
            'htu' => 'https://api.example.com/me',
            'iat' => $this->clock->now()->getTimestamp(),
            'ath' => $this->hashOf(self::ACCESS_TOKEN),
            ...$claims,
        ], static fn ($value) => null !== $value);
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
    private static function claimsBoundTo(array $key): array
    {
        return ['sub' => 'user-42', 'cnf' => ['jkt' => (new JWK($key))->toPublic()->thumbprint('sha256')]];
    }

    private function hashOf(string $accessToken): string
    {
        return self::base64UrlEncode(hash('sha256', $accessToken, true));
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
