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

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\OidcUser;
use Symfony\Component\Security\Http\AccessToken\Oidc\OidcUserInfoTokenHandler;
use Symfony\Component\Security\Http\Authenticator\FallbackUserLoader;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class OidcUserInfoTokenHandlerTest extends TestCase
{
    /**
     * @dataProvider getVerifiedClaims
     */
    public function testOnlyKeepsARecognizableBooleanForTheVerifiedClaims(mixed $value, ?bool $expected)
    {
        $claims = ['sub' => 'e21bf182-1538-406e-8ccb-e25a17aba39f', 'email_verified' => $value];

        $responseMock = $this->createMock(ResponseInterface::class);
        $responseMock->expects($this->once())->method('toArray')->willReturn($claims);

        $clientMock = $this->createMock(HttpClientInterface::class);
        $clientMock->expects($this->once())->method('request')->willReturn($responseMock);

        $user = (new OidcUserInfoTokenHandler($clientMock))->getUserBadgeFrom('a-secret-token')->getUserLoader()();

        $this->assertSame($expected, $user->getEmailVerified());
    }

    /**
     * A provider that does not serialize the claim as a JSON boolean must not end up
     * reporting a verified email: "false" cast to true, which is the reverse of what
     * the claim says, and anything unrecognizable leaves the flag unknown.
     */
    public static function getVerifiedClaims(): iterable
    {
        yield 'a boolean true' => [true, true];
        yield 'a boolean false' => [false, false];
        yield 'a stringly typed true' => ['true', true];
        yield 'a stringly typed false' => ['false', false];
        yield 'no' => ['no', false];
        yield 'off' => ['off', false];
        yield 'the number one' => [1, true];
        yield 'the number zero' => [0, false];
        yield 'anything else' => ['maybe', null];
    }

    /**
     * @dataProvider getClaims
     */
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

        $this->assertEquals(new UserBadge($expected, new FallbackUserLoader(static fn () => $expectedUser), $claims), $userBadge);
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
        $this->expectException(BadCredentialsException::class);
        $this->expectExceptionMessage('Invalid credentials.');

        $response = ['foo' => 'bar'];

        $responseMock = $this->createMock(ResponseInterface::class);
        $responseMock->expects($this->once())
            ->method('toArray')
            ->willReturn($response);

        $clientMock = $this->createMock(HttpClientInterface::class);
        $clientMock->expects($this->once())
            ->method('request')->with('GET', '', ['auth_bearer' => 'a-secret-token'])
            ->willReturn($responseMock);

        $loggerMock = $this->createMock(LoggerInterface::class);
        $loggerMock->expects($this->once())
            ->method('error');

        $handler = new OidcUserInfoTokenHandler($clientMock, $loggerMock);
        $handler->getUserBadgeFrom('a-secret-token');
    }
}
