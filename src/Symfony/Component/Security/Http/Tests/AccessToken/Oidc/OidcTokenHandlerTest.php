<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\AccessToken\Oidc;

use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\Core\JWKSet;
use Jose\Component\Encryption\Algorithm\ContentEncryption\A128CBCHS256;
use Jose\Component\Encryption\Algorithm\KeyEncryption\Dir;
use Jose\Component\Encryption\JWEBuilder;
use Jose\Component\Encryption\Serializer\CompactSerializer as JweCompactSerializer;
use Jose\Component\Signature\Algorithm\ES256;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\CompactSerializer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\Clock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\OidcUser;
use Symfony\Component\Security\Http\AccessToken\Oidc\OidcTokenHandler;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Contracts\Cache\ItemInterface;

#[RequiresPhpExtension('openssl')]
class OidcTokenHandlerTest extends TestCase
{
    private const AUDIENCE = 'Symfony OIDC';

    #[DataProvider('getClaims')]
    public function testGetsUserIdentifierFromSignedToken(string $claim, string $expected)
    {
        $time = time();
        $claims = [
            'iat' => $time,
            'nbf' => $time,
            'exp' => $time + 3600,
            'iss' => 'https://www.example.com',
            'aud' => self::AUDIENCE,
            'sub' => 'e21bf182-1538-406e-8ccb-e25a17aba39f',
            'email' => 'foo@example.com',
        ];
        $token = self::buildJWS(json_encode($claims));
        $expectedUser = new OidcUser(...$claims, userIdentifier: $claims[$claim]);

        $loggerMock = $this->createMock(LoggerInterface::class);
        $loggerMock->expects($this->never())->method('error');

        $userBadge = (new OidcTokenHandler(
            new AlgorithmManager([new ES256()]),
            self::getJWKSet(),
            self::AUDIENCE,
            ['https://www.example.com'],
            $claim,
            $loggerMock,
            new Clock(),
            0,
            true,
        ))->getUserBadgeFrom($token);
        $actualUser = $userBadge->getUserLoader()();

        $this->assertInstanceOf(UserBadge::class, $userBadge);
        $this->assertSame($expected, $userBadge->getUserIdentifier());
        $this->assertSame($claims, $userBadge->getAttributes());
        $this->assertInstanceOf(OidcUser::class, $actualUser);
        $this->assertEquals($expectedUser, $actualUser);
        $this->assertEquals($claims, $userBadge->getAttributes());
        $this->assertEquals($claims[$claim], $actualUser->getUserIdentifier());
    }

    public static function getClaims(): iterable
    {
        yield ['sub', 'e21bf182-1538-406e-8ccb-e25a17aba39f'];
        yield ['email', 'foo@example.com'];
    }

    #[DataProvider('getInvalidTokens')]
    public function testThrowsAnErrorIfTokenIsInvalid(string $token)
    {
        $loggerMock = $this->createMock(LoggerInterface::class);
        $loggerMock->expects($this->once())->method('error');

        $this->expectException(BadCredentialsException::class);
        $this->expectExceptionMessage('Invalid credentials.');

        (new OidcTokenHandler(
            new AlgorithmManager([new ES256()]),
            self::getJWKSet(),
            self::AUDIENCE,
            ['https://www.example.com'],
            'sub',
            $loggerMock,
            new Clock(),
            0,
            true,
        ))->getUserBadgeFrom($token);
    }

    public static function getInvalidTokens(): iterable
    {
        yield 'Invalid token' => ['invalid'];
        yield 'Token is expired' => [
            self::buildJWS(json_encode([
                'iat' => time() - 3600,
                'nbf' => time() - 3600,
                'exp' => time() - 3590,
                'iss' => 'https://www.example.com',
                'aud' => self::AUDIENCE,
                'sub' => 'e21bf182-1538-406e-8ccb-e25a17aba39f',
                'email' => 'foo@example.com',
            ])),
        ];
        yield 'Invalid audience' => [
            self::buildJWS(json_encode([
                'iat' => time(),
                'nbf' => time(),
                'exp' => time() + 3590,
                'iss' => 'https://www.example.com',
                'aud' => 'invalid',
                'sub' => 'e21bf182-1538-406e-8ccb-e25a17aba39f',
                'email' => 'foo@example.com',
            ])),
        ];
        yield 'Missing "aud" claim' => [
            self::buildJWS(json_encode([
                'iat' => time(),
                'nbf' => time(),
                'exp' => time() + 3600,
                'iss' => 'https://www.example.com',
                'sub' => 'e21bf182-1538-406e-8ccb-e25a17aba39f',
            ])),
        ];
        yield 'Missing "iss" claim' => [
            self::buildJWS(json_encode([
                'iat' => time(),
                'nbf' => time(),
                'exp' => time() + 3600,
                'aud' => self::AUDIENCE,
                'sub' => 'e21bf182-1538-406e-8ccb-e25a17aba39f',
            ])),
        ];
        yield 'Missing "exp" claim' => [
            self::buildJWS(json_encode([
                'iat' => time(),
                'nbf' => time(),
                'iss' => 'https://www.example.com',
                'aud' => self::AUDIENCE,
                'sub' => 'e21bf182-1538-406e-8ccb-e25a17aba39f',
            ])),
        ];
        yield 'Missing "iat" claim' => [
            self::buildJWS(json_encode([
                'nbf' => time(),
                'exp' => time() + 3600,
                'iss' => 'https://www.example.com',
                'aud' => self::AUDIENCE,
                'sub' => 'e21bf182-1538-406e-8ccb-e25a17aba39f',
            ])),
        ];
    }

    public function testThrowsAnErrorIfUserPropertyIsMissing()
    {
        $loggerMock = $this->createMock(LoggerInterface::class);
        $loggerMock->expects($this->once())->method('error');

        $time = time();
        $claims = [
            'iat' => $time,
            'nbf' => $time,
            'exp' => $time + 3600,
            'iss' => 'https://www.example.com',
            'aud' => self::AUDIENCE,
            'sub' => 'e21bf182-1538-406e-8ccb-e25a17aba39f',
        ];
        $token = $this->buildJWS(json_encode($claims));

        $this->expectException(BadCredentialsException::class);
        $this->expectExceptionMessage('Invalid credentials.');

        (new OidcTokenHandler(
            new AlgorithmManager([new ES256()]),
            self::getJWKSet(),
            self::AUDIENCE,
            ['https://www.example.com'],
            'email',
            $loggerMock,
            new Clock(),
            0,
            true,
        ))->getUserBadgeFrom($token);
    }

    #[DataProvider('getAtJwtTypes')]
    public function testAcceptsTheTokenTypesOfTheAccessTokenProfile(string $type)
    {
        $token = self::buildJWS(json_encode(self::getValidClaims()), ['typ' => $type]);

        $loggerMock = $this->createMock(LoggerInterface::class);
        $loggerMock->expects($this->never())->method('error');

        $userBadge = (new OidcTokenHandler(
            new AlgorithmManager([new ES256()]),
            self::getJWKSet(),
            self::AUDIENCE,
            ['https://www.example.com'],
            'sub',
            $loggerMock,
            new Clock(),
            0,
            true,
        ))->getUserBadgeFrom($token);

        $this->assertSame('e21bf182-1538-406e-8ccb-e25a17aba39f', $userBadge->getUserIdentifier());
    }

    public static function getAtJwtTypes(): iterable
    {
        yield 'short form' => ['at+jwt'];
        yield 'media type form' => ['application/at+jwt'];
        yield 'media types are case-insensitive' => ['AT+JWT'];
    }

    /**
     * An ID token carries the client identifier in its "aud" claim, so an application whose
     * configured audience is that same client identifier accepts it as an access token unless
     * the token type is enforced, which is what RFC 9068 §4 asks the resource server to do.
     */
    #[DataProvider('getTokensRejectedByTheAtJwtTypeCheck')]
    public function testRejectsTheTokensThatDoNotCarryTheAccessTokenType(array $header)
    {
        $token = self::buildJWS(json_encode(self::getValidClaims()), $header);

        $loggerMock = $this->createMock(LoggerInterface::class);
        $loggerMock->expects($this->once())->method('error');

        $this->expectException(BadCredentialsException::class);
        $this->expectExceptionMessage('Invalid credentials.');

        (new OidcTokenHandler(
            new AlgorithmManager([new ES256()]),
            self::getJWKSet(),
            self::AUDIENCE,
            ['https://www.example.com'],
            'sub',
            $loggerMock,
            new Clock(),
            0,
            true,
        ))->getUserBadgeFrom($token);
    }

    public static function getTokensRejectedByTheAtJwtTypeCheck(): iterable
    {
        yield 'an ID token' => [['typ' => 'JWT']];
        yield 'no type at all' => [[]];
        yield 'another profile' => [['typ' => 'logout+jwt']];
        yield 'a non-string type' => [['typ' => 42]];
    }

    #[DataProvider('getTokensRejectedByTheAtJwtTypeCheck')]
    public function testAcceptsAnyTokenTypeWhenTheCheckIsDisabled(array $header)
    {
        $token = self::buildJWS(json_encode(self::getValidClaims()), $header);

        $loggerMock = $this->createMock(LoggerInterface::class);
        $loggerMock->expects($this->never())->method('error');

        $userBadge = (new OidcTokenHandler(
            new AlgorithmManager([new ES256()]),
            self::getJWKSet(),
            self::AUDIENCE,
            ['https://www.example.com'],
            'sub',
            $loggerMock,
            new Clock(),
            0,
            false,
        ))->getUserBadgeFrom($token);

        $this->assertSame('e21bf182-1538-406e-8ccb-e25a17aba39f', $userBadge->getUserIdentifier());
    }

    /**
     * The check is off unless it is asked for, so that upgrading to 8.2 does not reject the
     * tokens an application already accepts. It is enforced by default as of 9.0.
     */
    #[Group('legacy')]
    #[IgnoreDeprecations]
    #[DataProvider('getTokensRejectedByTheAtJwtTypeCheck')]
    public function testAcceptsAnyTokenTypeWhenTheArgumentIsOmitted(array $header)
    {
        $this->expectUserDeprecationMessage('Since symfony/security-http 8.2: Not passing a value for the "$enforceAtJwtType" argument of "Symfony\\Component\\Security\\Http\\AccessToken\\Oidc\\OidcTokenHandler::__construct()" is deprecated, pass it explicitly; it will default to true in 9.0.');

        $token = self::buildJWS(json_encode(self::getValidClaims()), $header);

        $userBadge = (new OidcTokenHandler(
            new AlgorithmManager([new ES256()]),
            self::getJWKSet(),
            self::AUDIENCE,
            ['https://www.example.com'],
            'sub',
        ))->getUserBadgeFrom($token);

        $this->assertSame('e21bf182-1538-406e-8ccb-e25a17aba39f', $userBadge->getUserIdentifier());
    }

    /**
     * The type sits in the signed token, so the check must run after decryption.
     * Reading it from the outer JWE header rejects every encrypted token instead.
     */
    #[DataProvider('getEncryptedTokenTypes')]
    public function testEnforcesTheAccessTokenTypeInsideAnEncryptedToken(string $type, bool $accepted)
    {
        $encryptionKeyset = new JWKSet([new JWK(['kty' => 'oct', 'k' => 'V0hBVCBBIExPVkVMWSBLRVkgT0YgMzIgQllURVMh'])]);
        $encryptionAlgorithms = new AlgorithmManager([new Dir(), new A128CBCHS256()]);

        $jwe = (new JWEBuilder($encryptionAlgorithms))
            ->withPayload(self::buildJWS(json_encode(self::getValidClaims()), ['typ' => $type]))
            ->withSharedProtectedHeader(['alg' => 'dir', 'enc' => 'A128CBC-HS256', 'cty' => 'JWT'])
            ->addRecipient($encryptionKeyset->get(0))
            ->build();

        $handler = new OidcTokenHandler(
            new AlgorithmManager([new ES256()]),
            self::getJWKSet(),
            self::AUDIENCE,
            ['https://www.example.com'],
            'sub',
            null,
            new Clock(),
            0,
            true,
        );
        $handler->enableJweSupport($encryptionKeyset, $encryptionAlgorithms, true);

        if (!$accepted) {
            $this->expectException(BadCredentialsException::class);
        }

        $userBadge = $handler->getUserBadgeFrom((new JweCompactSerializer())->serialize($jwe, 0));

        $this->assertSame('e21bf182-1538-406e-8ccb-e25a17aba39f', $userBadge->getUserIdentifier());
    }

    public static function getEncryptedTokenTypes(): iterable
    {
        yield 'an access token' => ['at+jwt', true];
        yield 'an ID token' => ['JWT', false];
    }

    private static function getValidClaims(): array
    {
        $time = time();

        return [
            'iat' => $time,
            'nbf' => $time,
            'exp' => $time + 3600,
            'iss' => 'https://www.example.com',
            'aud' => self::AUDIENCE,
            'sub' => 'e21bf182-1538-406e-8ccb-e25a17aba39f',
        ];
    }

    private static function buildJWS(string $payload, array $header = ['typ' => 'at+jwt']): string
    {
        return (new CompactSerializer())->serialize((new JWSBuilder(new AlgorithmManager([
            new ES256(),
        ])))
            ->withPayload($payload)
            ->addSignature(self::getJWK(), $header + ['alg' => 'ES256'])
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

    private static function getSecondJWK(): JWK
    {
        return new JWK([
            'kty' => 'EC',
            'd' => '0LCBSOYvrksazPnC0pzwY0P5MWEESUhEzbc2zJEnOsc',
            'crv' => 'P-256',
            'x' => 'N1aUu8Pd2WdClkpCQ4QCPnGjYe_bTmDgEaSoxy5LhTw',
            'y' => 'Yr1v-tCNxE8QgAGlartrJAi343bI8VlAaNvgCOp8Azs',
        ]);
    }

    private static function getJWKSet(): JWKSet
    {
        return new JWKSet([
            new JWK([
                'kty' => 'EC',
                'crv' => 'P-256',
                'x' => 'FtgMtrsKDboRO-Zo0XC7tDJTATHVmwuf9GK409kkars',
                'y' => 'rWDE0ERU2SfwGYCo1DWWdgFEbZ0MiAXLRBBOzBgs_jY',
                'd' => '4G7bRIiKih0qrFxc0dtvkHUll19tTyctoCR3eIbOrO0',
            ]),
            self::getJWK(),
        ]);
    }

    public function testGetsUserIdentifierWithSingleDiscoveryEndpoint()
    {
        $time = time();
        $claims = [
            'iat' => $time,
            'nbf' => $time,
            'exp' => $time + 3600,
            'iss' => 'https://www.example.com',
            'aud' => self::AUDIENCE,
            'sub' => 'e21bf182-1538-406e-8ccb-e25a17aba39f',
            'email' => 'foo@example.com',
        ];
        $token = $this->buildJWS(json_encode($claims));

        $httpClient = new MockHttpClient([
            new JsonMockResponse(['jwks_uri' => 'https://www.example.com/.well-known/jwks.json']),
            new JsonMockResponse(['keys' => [array_merge(self::getJWK()->all(), ['use' => 'sig'])]]),
        ]);

        $cache = new ArrayAdapter();
        $handler = new OidcTokenHandler(
            new AlgorithmManager([new ES256()]),
            null,
            self::AUDIENCE,
            ['https://www.example.com'],
            'sub',
            null,
            new Clock(),
            0,
            true,
        );
        $handler->enableDiscovery($cache, $httpClient, 'oidc_config');

        $userBadge = $handler->getUserBadgeFrom($token);

        $this->assertInstanceOf(UserBadge::class, $userBadge);
        $this->assertSame('e21bf182-1538-406e-8ccb-e25a17aba39f', $userBadge->getUserIdentifier());
    }

    public function testDiscoveryRejectsAnOversizedJwks()
    {
        $token = $this->buildJWS(json_encode(['sub' => 'e21bf182-1538-406e-8ccb-e25a17aba39f']));

        $oversized = json_encode(['keys' => [['kty' => 'oct', 'use' => 'sig', 'k' => str_repeat('A', 1024 * 1024)]]]);
        $httpClient = new MockHttpClient([
            new JsonMockResponse(['jwks_uri' => 'https://www.example.com/.well-known/jwks.json']),
            new MockResponse($oversized, ['response_headers' => ['content-type' => 'application/json']]),
        ]);

        $handler = new OidcTokenHandler(
            new AlgorithmManager([new ES256()]),
            null,
            self::AUDIENCE,
            ['https://www.example.com'],
            'sub',
            null,
            new Clock(),
            0,
            true,
        );
        $handler->enableDiscovery(new ArrayAdapter(), $httpClient, 'oidc_config');

        $this->expectException(BadCredentialsException::class);
        $this->expectExceptionMessage('Invalid credentials.');

        $handler->getUserBadgeFrom($token);
    }

    public function testDiscoveryRejectsAJwksWithoutKeys()
    {
        $token = $this->buildJWS(json_encode(['sub' => 'e21bf182-1538-406e-8ccb-e25a17aba39f']));

        $httpClient = new MockHttpClient([
            new JsonMockResponse(['jwks_uri' => 'https://www.example.com/.well-known/jwks.json']),
            new JsonMockResponse(['error' => 'temporarily_unavailable']),
        ]);

        $handler = new OidcTokenHandler(
            new AlgorithmManager([new ES256()]),
            null,
            self::AUDIENCE,
            ['https://www.example.com'],
            'sub',
            null,
            new Clock(),
            0,
            true,
        );
        $handler->enableDiscovery(new ArrayAdapter(), $httpClient, 'oidc_config');

        $this->expectException(BadCredentialsException::class);
        $this->expectExceptionMessage('Invalid credentials.');

        $handler->getUserBadgeFrom($token);
    }

    public function testGetsUserIdentifierWithMultipleDiscoveryEndpoints()
    {
        $time = time();

        $httpClient1 = new MockHttpClient(static function ($method, $url) {
            if (str_contains($url, 'openid-configuration')) {
                return new JsonMockResponse(['jwks_uri' => 'https://provider1.example.com/.well-known/jwks.json']);
            }

            return new JsonMockResponse(['keys' => [array_merge(self::getJWK()->all(), ['use' => 'sig'])]]);
        });

        $httpClient2 = new MockHttpClient(static function ($method, $url) {
            if (str_contains($url, 'openid-configuration')) {
                return new JsonMockResponse(['jwks_uri' => 'https://provider2.example.com/.well-known/jwks.json']);
            }

            return new JsonMockResponse(['keys' => [array_merge(self::getSecondJWK()->all(), ['use' => 'sig'])]]);
        });

        $cache = new ArrayAdapter();

        $handler = new OidcTokenHandler(
            new AlgorithmManager([new ES256()]),
            null,
            self::AUDIENCE,
            ['https://www.example.com'],
            'sub',
            null,
            new Clock(),
            0,
            true,
        );
        $handler->enableDiscovery($cache, [$httpClient1, $httpClient2], 'oidc_config');

        $claims1 = [
            'iat' => $time,
            'nbf' => $time,
            'exp' => $time + 3600,
            'iss' => 'https://www.example.com',
            'aud' => self::AUDIENCE,
            'sub' => 'user-from-provider1',
            'email' => 'user1@example.com',
        ];
        $token1 = self::buildJWSWithKey(json_encode($claims1), self::getJWK());
        $userBadge1 = $handler->getUserBadgeFrom($token1);

        $this->assertInstanceOf(UserBadge::class, $userBadge1);
        $this->assertSame('user-from-provider1', $userBadge1->getUserIdentifier());

        $claims2 = [
            'iat' => $time,
            'nbf' => $time,
            'exp' => $time + 3600,
            'iss' => 'https://www.example.com',
            'aud' => self::AUDIENCE,
            'sub' => 'user-from-provider2',
            'email' => 'user2@example.com',
        ];
        $token2 = self::buildJWSWithKey(json_encode($claims2), self::getSecondJWK());
        $userBadge2 = $handler->getUserBadgeFrom($token2);

        $this->assertInstanceOf(UserBadge::class, $userBadge2);
        $this->assertSame('user-from-provider2', $userBadge2->getUserIdentifier());

        $this->assertTrue($cache->hasItem('oidc_config'));
    }

    public function testDiscoveryDocumentsOfEveryClientAreRequestedBeforeTheirJwks()
    {
        // the responses are lazy, so requesting every ".well-known" document before consuming
        // any of them is what lets those round trips happen concurrently
        $requestedUrls = [];
        $httpClients = [];
        foreach ([1, 2] as $provider) {
            $httpClients[] = new MockHttpClient(static function ($method, $url) use (&$requestedUrls, $provider): JsonMockResponse {
                $requestedUrls[] = $url;

                if (str_contains($url, 'openid-configuration')) {
                    return new JsonMockResponse(['jwks_uri' => \sprintf('https://provider%d.example.com/jwks.json', $provider)]);
                }

                return new JsonMockResponse(['keys' => [array_merge((1 === $provider ? self::getJWK() : self::getSecondJWK())->all(), ['use' => 'sig'])]]);
            }, \sprintf('https://provider%d.example.com/', $provider));
        }

        $handler = new OidcTokenHandler(new AlgorithmManager([new ES256()]), null, self::AUDIENCE, ['https://www.example.com'], 'sub', null, new Clock(), 0, true);
        $handler->enableDiscovery(new ArrayAdapter(), $httpClients, 'oidc_config');

        $time = time();
        $handler->getUserBadgeFrom(self::buildJWSWithKey(json_encode([
            'iat' => $time,
            'nbf' => $time,
            'exp' => $time + 3600,
            'iss' => 'https://www.example.com',
            'aud' => self::AUDIENCE,
            'sub' => 'user-from-provider1',
        ]), self::getJWK()));

        $this->assertSame([
            'https://provider1.example.com/.well-known/openid-configuration',
            'https://provider2.example.com/.well-known/openid-configuration',
            'https://provider1.example.com/jwks.json',
            'https://provider2.example.com/jwks.json',
        ], $requestedUrls);
    }

    private static function buildJWSWithKey(string $payload, JWK $jwk): string
    {
        return (new CompactSerializer())->serialize((new JWSBuilder(new AlgorithmManager([
            new ES256(),
        ])))
            ->withPayload($payload)
            ->addSignature($jwk, ['alg' => 'ES256', 'typ' => 'at+jwt'])
            ->build()
        );
    }

    public function testDiscoveryCachesJwksAccordingToCacheControl()
    {
        $time = time();
        $claims = [
            'iat' => $time,
            'nbf' => $time,
            'exp' => $time + 3600,
            'iss' => 'https://www.example.com',
            'aud' => self::AUDIENCE,
            'sub' => 'user-cache-control',
        ];
        $token = self::buildJWS(json_encode($claims));

        $requestCount = 0;
        $httpClient = new MockHttpClient(static function ($method, $url) use (&$requestCount) {
            ++$requestCount;
            if (str_contains($url, 'openid-configuration')) {
                return new JsonMockResponse(['jwks_uri' => 'https://www.example.com/jwks.json']);
            }

            return new JsonMockResponse(
                ['keys' => [array_merge(self::getJWK()->all(), ['use' => 'sig'])]],
                ['response_headers' => ['Cache-Control' => 'public, max-age=120']]
            );
        });

        $cache = new ArrayAdapter();
        $handler = new OidcTokenHandler(
            new AlgorithmManager([new ES256()]),
            null,
            self::AUDIENCE,
            ['https://www.example.com'],
            'sub',
            null,
            new Clock(),
            0,
            true,
        );
        $handler->enableDiscovery($cache, $httpClient, 'oidc_ttl_cc');
        $this->assertSame('user-cache-control', $handler->getUserBadgeFrom($token)->getUserIdentifier());
        $this->assertSame(2, $requestCount);
        $this->assertSame('user-cache-control', $handler->getUserBadgeFrom($token)->getUserIdentifier());
        $this->assertSame(2, $requestCount);
    }

    public function testResetForcesANewDiscoveryOnTheNextAuthentication()
    {
        $requestedUrls = [];
        $httpClient = new MockHttpClient(static function ($method, $url) use (&$requestedUrls): JsonMockResponse {
            $requestedUrls[] = $url;

            if (str_contains($url, 'openid-configuration')) {
                return new JsonMockResponse(['jwks_uri' => 'https://www.example.com/jwks.json']);
            }

            return new JsonMockResponse(['keys' => [array_merge(self::getJWK()->all(), ['use' => 'sig'])]]);
        });
        $countConfigurationRequests = static function () use (&$requestedUrls): int {
            return \count(array_filter($requestedUrls, static fn (string $url): bool => str_contains($url, 'openid-configuration')));
        };

        $time = time();
        $token = self::buildJWS(json_encode([
            'iat' => $time,
            'nbf' => $time,
            'exp' => $time + 3600,
            'iss' => 'https://www.example.com',
            'aud' => self::AUDIENCE,
            'sub' => 'user-reset',
        ]));

        $cache = new ArrayAdapter();
        $handler = new OidcTokenHandler(new AlgorithmManager([new ES256()]), null, self::AUDIENCE, ['https://www.example.com'], 'sub', null, new Clock(), 0, true);
        $handler->enableDiscovery($cache, $httpClient, 'oidc_config');

        $handler->getUserBadgeFrom($token);
        $this->assertSame(1, $countConfigurationRequests());

        // expired JWKS and document entries alone recompute the keys from the memoized document
        $cache->deleteItems(['oidc_config', 'oidc_config.document.0']);
        $handler->getUserBadgeFrom($token);
        $this->assertSame(1, $countConfigurationRequests());

        // after reset(), recomputing the keys fetches the discovery document again
        $handler->reset();
        $cache->deleteItems(['oidc_config', 'oidc_config.document.0']);
        $handler->getUserBadgeFrom($token);
        $this->assertSame(2, $countConfigurationRequests());
    }

    public function testDiscoveryCachesJwksAccordingToExpires()
    {
        $time = time();
        $claims = [
            'iat' => $time,
            'nbf' => $time,
            'exp' => $time + 3600,
            'iss' => 'https://www.example.com',
            'aud' => self::AUDIENCE,
            'sub' => 'user-expires',
        ];

        $token = self::buildJWS(json_encode($claims));

        $requestCount = 0;
        $httpClient = new MockHttpClient(static function ($method, $url) use (&$requestCount) {
            ++$requestCount;
            if (str_contains($url, 'openid-configuration')) {
                return new JsonMockResponse(['jwks_uri' => 'https://www.example.com/jwks.json']);
            }

            return new JsonMockResponse(
                ['keys' => [array_merge(self::getJWK()->all(), ['use' => 'sig'])]],
                ['response_headers' => ['Expires' => gmdate('D, d M Y H:i:s \G\M\T', time() + 60)]]
            );
        });

        $cache = new ArrayAdapter();
        $handler = new OidcTokenHandler(
            new AlgorithmManager([new ES256()]),
            null,
            self::AUDIENCE,
            ['https://www.example.com'],
            'sub',
            null,
            new Clock(),
            0,
            true,
        );
        $handler->enableDiscovery($cache, $httpClient, 'oidc_ttl_expires');
        $this->assertSame('user-expires', $handler->getUserBadgeFrom($token)->getUserIdentifier());
        $this->assertSame(2, $requestCount);
        $this->assertSame('user-expires', $handler->getUserBadgeFrom($token)->getUserIdentifier());
        $this->assertSame(2, $requestCount);
    }

    public function testComputeDiscoveryKeysReturnsEmptyWhenNoClients()
    {
        $cache = new ArrayAdapter();
        $handler = new OidcTokenHandler(
            new AlgorithmManager([new ES256()]),
            null,
            self::AUDIENCE,
            ['https://www.example.com'],
            'sub',
            null,
            new Clock(),
            0,
            true,
        );

        $handler->enableDiscovery($cache, [], 'oidc_empty_clients');

        $item = $this->createMock(ItemInterface::class);
        $item->expects($this->never())->method('expiresAfter');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('No OIDC discovery client configured.');
        $handler->computeDiscoveryKeys($item);
    }

    public function testDiscoveryDoesNotFollowRedirects()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) {
            $this->assertSame(0, $options['max_redirects']);

            return new MockResponse('', ['http_code' => 301, 'response_headers' => ['location' => 'https://other.example.com/.well-known/openid-configuration']]);
        });

        $cache = new ArrayAdapter();
        $handler = new OidcTokenHandler(
            new AlgorithmManager([new ES256()]),
            null,
            self::AUDIENCE,
            ['https://www.example.com'],
            'sub',
            null,
            new Clock(),
            0,
            true,
        );
        $handler->enableDiscovery($cache, $httpClient, 'oidc_redirected_discovery');

        $item = $this->createMock(ItemInterface::class);
        $item->expects($this->never())->method('expiresAfter');

        try {
            $handler->computeDiscoveryKeys($item);
            $this->fail('A BadCredentialsException should have been thrown.');
        } catch (BadCredentialsException) {
        }

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testDiscoveryRejectsJwksUriDowngradedToHttp()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse(['jwks_uri' => 'http://169.254.169.254/latest/meta-data/']),
            new JsonMockResponse(['keys' => [array_merge(self::getJWK()->all(), ['use' => 'sig'])]]),
        ]);

        $cache = new ArrayAdapter();
        $handler = new OidcTokenHandler(
            new AlgorithmManager([new ES256()]),
            null,
            self::AUDIENCE,
            ['https://www.example.com'],
            'sub',
            null,
            new Clock(),
            0,
            true,
        );
        $handler->enableDiscovery($cache, $httpClient, 'oidc_insecure_jwks_uri');

        $item = $this->createMock(ItemInterface::class);
        $item->expects($this->never())->method('expiresAfter');

        try {
            $handler->computeDiscoveryKeys($item);
            $this->fail('A BadCredentialsException should have been thrown.');
        } catch (BadCredentialsException) {
        }

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testDiscoveryFollowsJwksUriOfAnIssuerServedOverHttp()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse(['jwks_uri' => 'http://www.example.com/jwks.json']),
            new JsonMockResponse(['keys' => [array_merge(self::getJWK()->all(), ['use' => 'sig'])]]),
        ], 'http://www.example.com');

        $cache = new ArrayAdapter();
        $handler = new OidcTokenHandler(
            new AlgorithmManager([new ES256()]),
            null,
            self::AUDIENCE,
            ['http://www.example.com'],
            'sub',
            null,
            new Clock(),
            0,
            true,
        );
        $handler->enableDiscovery($cache, $httpClient, 'oidc_http_issuer');

        $item = $this->createMock(ItemInterface::class);
        $item->expects($this->never())->method('expiresAfter');

        $this->assertCount(1, $handler->computeDiscoveryKeys($item));
    }

    public function testDiscoveryThrowsWhenJwksUriIsMissing()
    {
        $time = time();
        $claims = [
            'iat' => $time,
            'nbf' => $time,
            'exp' => $time + 3600,
            'iss' => 'https://www.example.com',
            'aud' => self::AUDIENCE,
            'sub' => 'user-missing-jwks-uri',
        ];
        $token = self::buildJWS(json_encode($claims));

        $httpClient = new MockHttpClient([
            new JsonMockResponse(['issuer' => 'https://www.example.com']),
        ]);

        $cache = new ArrayAdapter();
        $handler = new OidcTokenHandler(
            new AlgorithmManager([new ES256()]),
            null,
            self::AUDIENCE,
            ['https://www.example.com'],
            'sub',
            null,
            new Clock(),
            0,
            true,
        );
        $handler->enableDiscovery($cache, $httpClient, 'oidc_missing_jwks_uri');

        $this->expectException(BadCredentialsException::class);
        $handler->getUserBadgeFrom($token);
    }

    public function testDiscoveryExcludesEncryptionKeys()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse(['jwks_uri' => 'https://www.example.com/jwks.json']),
            new JsonMockResponse([
                'keys' => [
                    array_merge(self::getJWK()->all(), ['use' => 'enc']),
                    array_merge(self::getSecondJWK()->all(), ['use' => 'sig']),
                ],
            ]),
        ]);

        $cache = new ArrayAdapter();
        $handler = new OidcTokenHandler(
            new AlgorithmManager([new ES256()]),
            null,
            self::AUDIENCE,
            ['https://www.example.com'],
            'sub',
            null,
            new Clock(),
            0,
            true,
        );
        $handler->enableDiscovery($cache, $httpClient, 'oidc_non_sig_keys', false);

        $item = $this->createMock(ItemInterface::class);
        $item->expects($this->never())->method('expiresAfter');
        $keys = $handler->computeDiscoveryKeys($item);
        $this->assertCount(1, $keys);
        $this->assertSame('sig', $keys[0]['use']);
    }

    public function testDiscoveryExcludesEncryptionKeyOps()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse(['jwks_uri' => 'https://www.example.com/jwks.json']),
            new JsonMockResponse([
                'keys' => [
                    array_merge(self::getJWK()->all(), ['key_ops' => ['encrypt', 'decrypt']]),
                    array_merge(self::getSecondJWK()->all(), ['key_ops' => ['sign', 'verify']]),
                ],
            ]),
        ]);

        $cache = new ArrayAdapter();
        $handler = new OidcTokenHandler(
            new AlgorithmManager([new ES256()]),
            null,
            self::AUDIENCE,
            ['https://www.example.com'],
            'sub',
            null,
            new Clock(),
            0,
            true,
        );
        $handler->enableDiscovery($cache, $httpClient, 'oidc_enc_key_ops', false);

        $item = $this->createStub(ItemInterface::class);
        $keys = $handler->computeDiscoveryKeys($item);
        $this->assertCount(1, $keys);
        $this->assertSame(['sign', 'verify'], $keys[0]['key_ops']);
    }

    public function testDiscoveryIncludesKeysWithoutUsageDesignation()
    {
        $time = time();
        $claims = [
            'iat' => $time,
            'nbf' => $time,
            'exp' => $time + 3600,
            'iss' => 'https://www.example.com',
            'aud' => self::AUDIENCE,
            'sub' => 'user-no-use-field',
        ];
        $token = self::buildJWS(json_encode($claims));

        $jwkData = self::getJWK()->all();
        unset($jwkData['d']);

        $httpClient = new MockHttpClient([
            new JsonMockResponse(['jwks_uri' => 'https://www.example.com/jwks.json']),
            new JsonMockResponse(['keys' => [$jwkData]]),
        ]);

        $cache = new ArrayAdapter();
        $handler = new OidcTokenHandler(
            new AlgorithmManager([new ES256()]),
            null,
            self::AUDIENCE,
            ['https://www.example.com'],
            'sub',
            null,
            new Clock(),
            0,
            true,
        );
        $handler->enableDiscovery($cache, $httpClient, 'oidc_no_use', false);

        $userBadge = $handler->getUserBadgeFrom($token);

        $this->assertInstanceOf(UserBadge::class, $userBadge);
        $this->assertSame('user-no-use-field', $userBadge->getUserIdentifier());
    }

    public function testDiscoveryEnforcedUsageOnlyAcceptsExplicitSignatureKeys()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse(['jwks_uri' => 'https://www.example.com/jwks.json']),
            new JsonMockResponse([
                'keys' => [
                    array_merge(self::getJWK()->all(), ['use' => 'enc']),
                    array_merge(self::getSecondJWK()->all(), []),
                ],
            ]),
        ]);

        $cache = new ArrayAdapter();
        $handler = new OidcTokenHandler(
            new AlgorithmManager([new ES256()]),
            null,
            self::AUDIENCE,
            ['https://www.example.com'],
            'sub',
            null,
            new Clock(),
            0,
            true,
        );
        $handler->enableDiscovery($cache, $httpClient, 'oidc_enforced', true);

        $item = $this->createMock(ItemInterface::class);
        $item->expects($this->never())->method('expiresAfter');
        $this->assertSame([], $handler->computeDiscoveryKeys($item));
    }

    public function testDiscoveryEnforcedUsageAcceptsKeyOpsSign()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse(['jwks_uri' => 'https://www.example.com/jwks.json']),
            new JsonMockResponse([
                'keys' => [
                    array_merge(self::getJWK()->all(), ['key_ops' => ['sign']]),
                    array_merge(self::getSecondJWK()->all(), ['key_ops' => ['encrypt']]),
                ],
            ]),
        ]);

        $cache = new ArrayAdapter();
        $handler = new OidcTokenHandler(
            new AlgorithmManager([new ES256()]),
            null,
            self::AUDIENCE,
            ['https://www.example.com'],
            'sub',
            null,
            new Clock(),
            0,
            true,
        );
        $handler->enableDiscovery($cache, $httpClient, 'oidc_enforced_ops', true);
        $item = $this->createStub(ItemInterface::class);
        $keys = $handler->computeDiscoveryKeys($item);
        $this->assertCount(1, $keys);
        $this->assertSame(['sign'], $keys[0]['key_ops']);
    }

    public function testTokenWithFutureIatIsRejectedWithoutAllowedTimeDrift()
    {
        $time = time();
        $claims = [
            'iat' => $time + 3,
            'nbf' => $time,
            'exp' => $time + 3600,
            'iss' => 'https://www.example.com',
            'aud' => self::AUDIENCE,
            'sub' => 'e21bf182-1538-406e-8ccb-e25a17aba39f',
        ];
        $token = self::buildJWS(json_encode($claims));

        $loggerMock = $this->createMock(LoggerInterface::class);
        $loggerMock->expects($this->once())->method('error');

        $this->expectException(BadCredentialsException::class);
        $this->expectExceptionMessage('Invalid credentials.');

        (new OidcTokenHandler(
            new AlgorithmManager([new ES256()]),
            self::getJWKSet(),
            self::AUDIENCE,
            ['https://www.example.com'],
            'sub',
            $loggerMock,
            new Clock(),
            0,
            true,
        ))->getUserBadgeFrom($token);
    }

    public function testTokenWithFutureIatIsAcceptedWithAllowedTimeDrift()
    {
        $time = time();
        $claims = [
            'iat' => $time + 3,
            'nbf' => $time,
            'exp' => $time + 3600,
            'iss' => 'https://www.example.com',
            'aud' => self::AUDIENCE,
            'sub' => 'e21bf182-1538-406e-8ccb-e25a17aba39f',
        ];
        $token = self::buildJWS(json_encode($claims));

        $loggerMock = $this->createMock(LoggerInterface::class);
        $loggerMock->expects($this->never())->method('error');

        $userBadge = (new OidcTokenHandler(
            new AlgorithmManager([new ES256()]),
            self::getJWKSet(),
            self::AUDIENCE,
            ['https://www.example.com'],
            'sub',
            $loggerMock,
            new Clock(),
            5,
            true,
        ))->getUserBadgeFrom($token);

        $this->assertInstanceOf(UserBadge::class, $userBadge);
        $this->assertSame('e21bf182-1538-406e-8ccb-e25a17aba39f', $userBadge->getUserIdentifier());
    }

    public function testTokenWithFutureIatBeyondAllowedTimeDriftIsRejected()
    {
        $time = time();
        $claims = [
            'iat' => $time + 10,
            'nbf' => $time,
            'exp' => $time + 3600,
            'iss' => 'https://www.example.com',
            'aud' => self::AUDIENCE,
            'sub' => 'e21bf182-1538-406e-8ccb-e25a17aba39f',
        ];
        $token = self::buildJWS(json_encode($claims));

        $loggerMock = $this->createMock(LoggerInterface::class);
        $loggerMock->expects($this->once())->method('error');

        $this->expectException(BadCredentialsException::class);
        $this->expectExceptionMessage('Invalid credentials.');

        (new OidcTokenHandler(
            new AlgorithmManager([new ES256()]),
            self::getJWKSet(),
            self::AUDIENCE,
            ['https://www.example.com'],
            'sub',
            $loggerMock,
            new Clock(),
            5,
            true,
        ))->getUserBadgeFrom($token);
    }

    /**
     * A resource server answering for several identifiers, as one deployed behind more than one
     * API base URL is, declares them all, and RFC 7519 §4.1.3 lets the provider name the ones a
     * token is minted for as a string or as a list.
     */
    #[DataProvider('getAudiencesNamingADeclaredOne')]
    public function testAcceptsATokenIssuedForAnyOfTheDeclaredAudiences(string|array $tokenAudience)
    {
        $token = self::buildJWS(json_encode(['aud' => $tokenAudience] + self::getValidClaims()));

        $loggerMock = $this->createMock(LoggerInterface::class);
        $loggerMock->expects($this->never())->method('error');

        $userBadge = (new OidcTokenHandler(
            new AlgorithmManager([new ES256()]),
            self::getJWKSet(),
            ['https://api.example.com', self::AUDIENCE],
            ['https://www.example.com'],
            'sub',
            $loggerMock,
            new Clock(),
            0,
            true,
        ))->getUserBadgeFrom($token);

        $this->assertSame('e21bf182-1538-406e-8ccb-e25a17aba39f', $userBadge->getUserIdentifier());
    }

    public static function getAudiencesNamingADeclaredOne(): iterable
    {
        yield 'a string naming the first one' => ['https://api.example.com'];
        yield 'a string naming the last one' => [self::AUDIENCE];
        yield 'a list naming one of them' => [['https://elsewhere.example.com', self::AUDIENCE]];
        yield 'a list naming them all' => [['https://api.example.com', self::AUDIENCE]];
    }

    #[DataProvider('getAudiencesNamingNoDeclaredOne')]
    public function testRejectsATokenIssuedForNoneOfTheDeclaredAudiences(mixed $tokenAudience)
    {
        $token = self::buildJWS(json_encode(['aud' => $tokenAudience] + self::getValidClaims()));

        $loggerMock = $this->createMock(LoggerInterface::class);
        $loggerMock->expects($this->once())->method('error');

        $this->expectException(BadCredentialsException::class);
        $this->expectExceptionMessage('Invalid credentials.');

        (new OidcTokenHandler(
            new AlgorithmManager([new ES256()]),
            self::getJWKSet(),
            ['https://api.example.com', self::AUDIENCE],
            ['https://www.example.com'],
            'sub',
            $loggerMock,
            new Clock(),
            0,
            true,
        ))->getUserBadgeFrom($token);
    }

    public static function getAudiencesNamingNoDeclaredOne(): iterable
    {
        yield 'a string naming another audience' => ['https://elsewhere.example.com'];
        yield 'a list naming another audience' => [['https://elsewhere.example.com']];
        yield 'an empty list' => [[]];
        yield 'a non-string' => [42];
        yield 'a list of non-strings' => [[42]];
    }

    #[DataProvider('getAudiencesNamingNothing')]
    public function testRejectsAnAudienceNamingNothing(string|array $audience, string $expectedMessage)
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        new OidcTokenHandler(
            new AlgorithmManager([new ES256()]),
            self::getJWKSet(),
            $audience,
            ['https://www.example.com'],
            'sub',
            null,
            new Clock(),
            0,
            true,
        );
    }

    public static function getAudiencesNamingNothing(): iterable
    {
        yield 'an empty list' => [[], 'cannot be an empty list'];
        yield 'an empty string' => ['', 'must be a non-empty string or a list of non-empty strings'];
        yield 'an empty string among others' => [['https://api.example.com', ''], 'must be a non-empty string or a list of non-empty strings'];
        yield 'a non-string' => [[42], 'must be a non-empty string or a list of non-empty strings'];
    }
}
