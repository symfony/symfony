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
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Security\Core\User\OAuth2User;
use Symfony\Component\Security\Http\AccessToken\OAuth2\Oauth2TokenHandler;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

class OAuth2TokenHandlerTest extends TestCase
{
    public static function testGetsUserIdentifierFromOAuth2ServerResponse(): void
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

        $client = new MockHttpClient([
            new MockResponse(json_encode($claims, \JSON_THROW_ON_ERROR)),
        ]);

        $userBadge = (new Oauth2TokenHandler($client))->getUserBadgeFrom($accessToken);
        $actualUser = $userBadge->getUserLoader()();

        self::assertEquals(new UserBadge('Z5O3upPC88QrAjx00dis', fn () => $expectedUser, $claims), $userBadge);
        self::assertInstanceOf(OAuth2User::class, $actualUser);
        self::assertSame($claims, $userBadge->getAttributes());
        self::assertSame($claims['sub'], $actualUser->getUserIdentifier());
    }
}
