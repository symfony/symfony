<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\OAuth2Client\Tests\Token;

use PHPUnit\Framework\TestCase;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException;
use Symfony\Component\Security\OAuth2Client\Token\AuthorizationCodeGrantAccessToken;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class AuthorizationCodeGrantAccessTokenTest extends TestCase
{
    /**
     * @dataProvider provideWrongKeys
     */
    public function testExtraKey(array $keys)
    {
        static::expectException(UndefinedOptionsException::class);

        new AuthorizationCodeGrantAccessToken($keys);
    }

    /**
     * @dataProvider provideInvalidKeys
     */
    public function testInvalidKeyType(array $keys)
    {
        static::expectException(InvalidOptionsException::class);

        new AuthorizationCodeGrantAccessToken($keys);
    }

    /**
     * @dataProvider provideValidKeys
     */
    public function testValidKeys(array $keys)
    {
        $token = new AuthorizationCodeGrantAccessToken($keys);

        static::assertNotNull($token->getTokenValue('access_token'));
    }

    public function provideWrongKeys(): \Generator
    {
        yield 'Extra test key' => [
            [
                'access_token' => 'foo',
                'token_type' => 'bar',
                'test' => 'foo',
            ],
        ];
    }

    public function provideInvalidKeys(): \Generator
    {
        yield 'Invalid access_token type' => [
            [
                'access_token' => 123,
                'token_type' => 'bar',
                'expires_in' => 100,
                'scope' => 'public',
            ],
        ];
    }

    public function provideValidKeys(): \Generator
    {
        yield 'Valid keys | All' => [
            [
                'access_token' => 'foo',
                'token_type' => 'bar',
                'refresh_token' => 'bar',
                'expires_in' => 100,
                'scope' => 'public',
            ],
        ];
    }
}
