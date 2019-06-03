<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\OAuth2Client\Tests\Helper;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Security\OAuth2Client\Helper\TokenIntrospectionHelper;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TokenIntrospectionHelperUnitTest extends TestCase
{
    public function testValidTokenCanBeIntrospected()
    {
        $clientMock = new MockHttpClient([
            new MockResponse(\json_encode([
                'active' => false,
                'scope' => 'test',
                'client_id' => '1234567',
                'username' => 'random',
                'token_type' => 'authorization_code',
            ])),
        ]);

        $introspecter = new TokenIntrospectionHelper($clientMock);

        $introspectedToken = $introspecter->introspecte('https://www.bar.com', '123456randomtoken');

        static::assertSame($introspectedToken->getTokenValue('active'), false);
        static::assertSame($introspectedToken->getTokenValue('scope'), 'test');
        static::assertSame($introspectedToken->getTokenValue('client_id'), '1234567');
        static::assertSame($introspectedToken->getTokenValue('username'), 'random');
        static::assertSame($introspectedToken->getTokenValue('token_type'), 'authorization_code');
    }
}
