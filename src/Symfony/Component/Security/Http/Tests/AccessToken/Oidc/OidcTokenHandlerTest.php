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
use Jose\Component\Signature\Algorithm\ES256;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\CompactSerializer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\OidcUser;
use Symfony\Component\Security\Http\AccessToken\Oidc\OidcTokenHandler;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

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
        ))->getUserBadgeFrom($token);
    }

    public static function getInvalidTokens(): iterable
    {
        // Invalid token
        yield ['invalid'];
        // Token is expired
        yield [
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
        // Invalid audience
        yield [
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
        ))->getUserBadgeFrom($token);
    }

    private static function buildJWS(string $payload): string
    {
        return (new CompactSerializer())->serialize((new JWSBuilder(new AlgorithmManager([
            new ES256(),
        ])))->create()
            ->withPayload($payload)
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
            ['https://www.example.com']
        );
        $handler->enableDiscovery($cache, $httpClient, 'oidc_config');

        $userBadge = $handler->getUserBadgeFrom($token);

        $this->assertInstanceOf(UserBadge::class, $userBadge);
        $this->assertSame('e21bf182-1538-406e-8ccb-e25a17aba39f', $userBadge->getUserIdentifier());
    }

    public function testGetsUserIdentifierWithMultipleDiscoveryEndpoints()
    {
        $time = time();

        $httpClient1 = new MockHttpClient(function ($method, $url) {
            if (str_contains($url, 'openid-configuration')) {
                return new JsonMockResponse(['jwks_uri' => 'https://provider1.example.com/.well-known/jwks.json']);
            }

            return new JsonMockResponse(['keys' => [array_merge(self::getJWK()->all(), ['use' => 'sig'])]]);
        });

        $httpClient2 = new MockHttpClient(function ($method, $url) {
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
            ['https://www.example.com']
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

    private static function buildJWSWithKey(string $payload, JWK $jwk): string
    {
        return (new CompactSerializer())->serialize((new JWSBuilder(new AlgorithmManager([
            new ES256(),
        ])))->create()
            ->withPayload($payload)
            ->addSignature($jwk, ['alg' => 'ES256'])
            ->build()
        );
    }

    public function testDiscoveryCachesJwksAccordingToCacheControl()
    {
        $time = time();
        $claims = [
            'iat' => $time, 'nbf' => $time,
            'exp' => $time + 3600,
            'iss' => 'https://www.example.com',
            'aud' => self::AUDIENCE,
            'sub' => 'user-cache-control',
        ];
        $token = self::buildJWS(json_encode($claims));

        $requestCount = 0;
        $httpClient = new MockHttpClient(function ($method, $url) use (&$requestCount) {
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
            ['https://www.example.com']
        );
        $handler->enableDiscovery($cache, $httpClient, 'oidc_ttl_cc');
        $this->assertSame('user-cache-control', $handler->getUserBadgeFrom($token)->getUserIdentifier());
        $this->assertSame(2, $requestCount);
        $this->assertSame('user-cache-control', $handler->getUserBadgeFrom($token)->getUserIdentifier());
        $this->assertSame(2, $requestCount);
    }

    public function testDiscoveryCachesJwksAccordingToExpires()
    {
        $time = time();
        $claims = [
            'iat' => $time, 'nbf' => $time,
            'exp' => $time + 3600,
            'iss' => 'https://www.example.com',
            'aud' => self::AUDIENCE,
            'sub' => 'user-expires',
        ];

        $token = self::buildJWS(json_encode($claims));

        $requestCount = 0;
        $httpClient = new MockHttpClient(function ($method, $url) use (&$requestCount) {
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
            ['https://www.example.com']
        );
        $handler->enableDiscovery($cache, $httpClient, 'oidc_ttl_expires');
        $this->assertSame('user-expires', $handler->getUserBadgeFrom($token)->getUserIdentifier());
        $this->assertSame(2, $requestCount);
        $this->assertSame('user-expires', $handler->getUserBadgeFrom($token)->getUserIdentifier());
        $this->assertSame(2, $requestCount);
    }
}
