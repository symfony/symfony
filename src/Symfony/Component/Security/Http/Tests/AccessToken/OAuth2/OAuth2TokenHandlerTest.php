<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\AccessToken\OAuth2;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\OAuth2User;
use Symfony\Component\Security\Http\AccessToken\OAuth2\Oauth2TokenHandler;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class OAuth2TokenHandlerTest extends TestCase
{
    public function testGetsUserIdentifierFromOAuth2ServerResponse()
    {
        $accessToken = 'a-secret-token';
        $claims = [
            'active' => true,
            'client_id' => 'l238j323ds-23ij4',
            'username' => 'jdoe',
            'scope' => 'read write dolphin',
            'sub' => 'Z5O3upPC88QrAjx00dis',
            'aud' => 'https://protected.example.net/resource',
            'iss' => 'https://server.example.com/',
            'exp' => 1419356238,
            'iat' => 1419350238,
            'extension_field' => 'twenty-seven',
        ];
        $expectedUser = new OAuth2User(...$claims);

        $responseMock = $this->createMock(ResponseInterface::class);
        $responseMock->expects($this->once())
            ->method('toArray')
            ->willReturn($claims);

        $clientMock = $this->createMock(HttpClientInterface::class);
        $clientMock->expects($this->once())
            ->method('request')->with('POST', '', [
                'body' => [
                    'token' => $accessToken,
                    'token_type_hint' => 'access_token',
                ],
            ])
            ->willReturn($responseMock);

        $userBadge = (new OAuth2TokenHandler($clientMock))->getUserBadgeFrom($accessToken);
        $actualUser = $userBadge->getUserLoader()();

        $this->assertEquals(new UserBadge('Z5O3upPC88QrAjx00dis', fn () => $expectedUser, $claims), $userBadge);
        $this->assertInstanceOf(OAuth2User::class, $actualUser);
        $this->assertSame($claims, $userBadge->getAttributes());
        $this->assertSame($claims['sub'], $actualUser->getUserIdentifier());
    }
}
