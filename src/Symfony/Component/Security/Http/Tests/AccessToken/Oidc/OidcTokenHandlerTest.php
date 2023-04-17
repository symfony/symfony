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
use Jose\Component\Signature\Algorithm\ES256;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\CompactSerializer;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\OidcUser;
use Symfony\Component\Security\Http\AccessToken\Oidc\OidcTokenHandler;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

/**
 * @requires extension openssl
 */
class OidcTokenHandlerTest extends TestCase
{
    private const AUDIENCE = 'Symfony OIDC';

    /**
     * @dataProvider getClaims
     */
    public function testGetsUserIdentifierFromSignedToken(string $claim, string $expected)
    {
        $time = time();
        $claims = [
            'iat' => $time,
            'nbf' => $time,
            'exp' => $time + 3600,
            'iss' => 'https://www.example.com/',
            'aud' => self::AUDIENCE,
            'sub' => 'e21bf182-1538-406e-8ccb-e25a17aba39f',
            'email' => 'foo@example.com',
        ];
        $token = $this->buildJWS(json_encode($claims));
        $expectedUser = new OidcUser(...$claims);

        $loggerMock = $this->createMock(LoggerInterface::class);
        $loggerMock->expects($this->never())->method('error');

        $userBadge = (new OidcTokenHandler(
            new ES256(),
            $this->getJWK(),
            $loggerMock,
            $claim,
            self::AUDIENCE
        ))->getUserBadgeFrom($token);
        $actualUser = $userBadge->getUserLoader()();

        $this->assertEquals(new UserBadge($expected, fn () => $expectedUser, $claims), $userBadge);
        $this->assertInstanceOf(OidcUser::class, $actualUser);
        $this->assertEquals($expectedUser, $actualUser);
        $this->assertEquals($claims, $userBadge->getAttributes());
        $this->assertEquals($claims['sub'], $actualUser->getUserIdentifier());
    }

    public static function getClaims(): iterable
    {
        yield ['sub', 'e21bf182-1538-406e-8ccb-e25a17aba39f'];
        yield ['email', 'foo@example.com'];
    }

    /**
     * @dataProvider getInvalidTokens
     */
    public function testThrowsAnErrorIfTokenIsInvalid(string $token)
    {
        $this->expectException(BadCredentialsException::class);
        $this->expectExceptionMessage('Invalid credentials.');

        $loggerMock = $this->createMock(LoggerInterface::class);
        $loggerMock->expects($this->once())->method('error');

        (new OidcTokenHandler(
            new ES256(),
            $this->getJWK(),
            $loggerMock,
            'sub',
            self::AUDIENCE
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
                'iss' => 'https://www.example.com/',
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
                'iss' => 'https://www.example.com/',
                'aud' => 'invalid',
                'sub' => 'e21bf182-1538-406e-8ccb-e25a17aba39f',
                'email' => 'foo@example.com',
            ])),
        ];
    }

    public function testThrowsAnErrorIfUserPropertyIsMissing()
    {
        $this->expectException(BadCredentialsException::class);
        $this->expectExceptionMessage('Invalid credentials.');

        $loggerMock = $this->createMock(LoggerInterface::class);
        $loggerMock->expects($this->once())->method('error');

        $time = time();
        $claims = [
            'iat' => $time,
            'nbf' => $time,
            'exp' => $time + 3600,
            'iss' => 'https://www.example.com/',
            'aud' => self::AUDIENCE,
            'sub' => 'e21bf182-1538-406e-8ccb-e25a17aba39f',
        ];
        $token = $this->buildJWS(json_encode($claims));

        (new OidcTokenHandler(
            new ES256(),
            self::getJWK(),
            $loggerMock,
            'email',
            self::AUDIENCE
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
}
