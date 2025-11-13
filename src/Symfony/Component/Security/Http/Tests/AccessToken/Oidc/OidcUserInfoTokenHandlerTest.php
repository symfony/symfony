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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\OidcUser;
use Symfony\Component\Security\Http\AccessToken\Oidc\OidcUserInfoTokenHandler;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class OidcUserInfoTokenHandlerTest extends TestCase
{
    #[DataProvider('getClaims')]
    public function testGetsUserIdentifierFromOidcServerResponse(string $claim, string $expected)
    {
        $accessToken = 'a-secret-token';
        $claims = [
            'sub' => 'e21bf182-1538-406e-8ccb-e25a17aba39f',
            'email' => 'foo@example.com',
        ];
        $expectedUser = new OidcUser(...$claims, userIdentifier: $claims[$claim]);

        $responseMock = $this->createMock(ResponseInterface::class);
        $responseMock->expects($this->once())
            ->method('toArray')
            ->willReturn($claims);

        $clientMock = $this->createMock(HttpClientInterface::class);
        $clientMock->expects($this->once())
            ->method('request')->with('GET', '', ['auth_bearer' => $accessToken])
            ->willReturn($responseMock);

        $userBadge = (new OidcUserInfoTokenHandler($clientMock, null, $claim))->getUserBadgeFrom($accessToken);
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

    public function testThrowsAnExceptionIfUserPropertyIsMissing()
    {
        $responseMock = $this->createMock(ResponseInterface::class);
        $responseMock->expects($this->once())
            ->method('toArray')
            ->willReturn(['foo' => 'bar']);

        $clientMock = $this->createMock(HttpClientInterface::class);
        $clientMock->expects($this->once())
            ->method('request')->with('GET', '', ['auth_bearer' => 'a-secret-token'])
            ->willReturn($responseMock);

        $loggerMock = $this->createMock(LoggerInterface::class);
        $loggerMock->expects($this->once())
            ->method('error');

        $handler = new OidcUserInfoTokenHandler($clientMock, $loggerMock);

        $this->expectException(BadCredentialsException::class);
        $this->expectExceptionMessage('Invalid credentials.');

        $handler->getUserBadgeFrom('a-secret-token');
    }
}
