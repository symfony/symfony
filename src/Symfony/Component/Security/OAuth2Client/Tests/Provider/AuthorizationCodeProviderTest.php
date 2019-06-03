<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\OAuth2Client\Tests\Provider;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\OptionsResolver\Exception\MissingOptionsException;
use Symfony\Component\Security\OAuth2Client\Exception\InvalidRequestException;
use Symfony\Component\Security\OAuth2Client\Exception\InvalidUrlException;
use Symfony\Component\Security\OAuth2Client\Provider\AuthorizationCodeProvider;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class AuthorizationCodeProviderTest extends TestCase
{
    /**
     * @dataProvider provideWrongOptions
     */
    public function testWrongOptionsSent(array $options)
    {
        static::expectException(MissingOptionsException::class);

        $clientMock = new MockHttpClient([]);

        new AuthorizationCodeProvider($clientMock, $options);
    }

    /**
     * @dataProvider provideWrongUrls
     */
    public function testWrongUrls(array $options)
    {
        static::expectException(InvalidUrlException::class);

        $clientMock = new MockHttpClient([]);

        new AuthorizationCodeProvider($clientMock, $options);
    }

    /**
     * @dataProvider provideValidOptions
     */
    public function testValidOptionsAndInvalidAuthorizationCodeRequest(array $options, string $code, string $state)
    {
        static::expectException(InvalidRequestException::class);

        $clientMock = new MockHttpClient(
            [
                new MockResponse('https://bar.com/authenticate?error=invalid_scope', [
                    'response_headers' => [
                        'http_method' => 'GET',
                        'http_code' => 400,
                    ],
                ]),
            ]
        );

        $provider = new AuthorizationCodeProvider($clientMock, $options);

        $provider->fetchAuthorizationInformations(['scope' => 'test', 'state' => $state]);
    }

    /**
     * @dataProvider provideValidOptions
     */
    public function testValidOptionsAndValidAuthorizationCodeRequest(array $options, string $code, string $state)
    {
        $clientMock = new MockHttpClient(
            [
                new MockResponse(\sprintf('https://bar.com/authenticate?code=%s&state=%s', $code, $state), [
                    'response_headers' => [
                        'http_method' => 'GET',
                        'http_code' => 200,
                    ],
                ]),
            ]
        );

        $provider = new AuthorizationCodeProvider($clientMock, $options);

        $authorizationCode = $provider->fetchAuthorizationInformations(['scope' => 'public', 'state' => $state]);

        static::assertSame($state, $authorizationCode->getState());
    }

    /**
     * @dataProvider provideValidOptions
     */
    public function testValidOptionsAndInvalidAccessTokenRequest(array $options, string $code, string $state)
    {
        static::expectException(InvalidRequestException::class);

        $clientMock = new MockHttpClient(
            [
                new MockResponse('https://bar.com/authenticate?error=invalid_scope', [
                    'response_headers' => [
                        'http_method' => 'GET',
                        'http_code' => 400,
                    ],
                ]),
            ]
        );

        $provider = new AuthorizationCodeProvider($clientMock, $options);

        $provider->fetchAccessToken(['code' => $code]);
    }

    /**
     * @dataProvider provideValidOptions
     */
    public function testValidOptionsAndValidAccessTokenWithRefreshTokenRequest(array $options, string $code, string $state)
    {
        $clientMock = new MockHttpClient(
            [
                new MockResponse(\json_encode([
                    'access_token' => $code,
                    'token_type' => 'test',
                    'expires_in' => 3600,
                    'refresh_token' => \uniqid(),
                ]), [
                    'response_headers' => [
                        'http_method' => 'GET',
                        'http_code' => 200,
                    ],
                ]),
                new MockResponse(\json_encode([
                    'access_token' => \uniqid(),
                    'token_type' => 'test',
                    'expires_in' => 1200,
                ]), [
                    'response_headers' => [
                        'http_method' => 'GET',
                        'http_code' => 200,
                    ],
                ]),
            ]
        );

        $provider = new AuthorizationCodeProvider($clientMock, $options);

        $accessToken = $provider->fetchAccessToken(['code' => $code]);

        static::assertNotNull($accessToken->getTokenValue('access_token'));
        static::assertNotNull($accessToken->getTokenValue('refresh_token'));
        static::assertInternalType('int', $accessToken->getTokenValue('expires_in'));
        static::assertSame(3600, $accessToken->getTokenValue('expires_in'));

        $refreshedToken = $provider->refreshToken($accessToken->getTokenValue('refresh_token'), 'public');

        static::assertNotNull($refreshedToken->getTokenValue('access_token'));
        static::assertNull($refreshedToken->getTokenValue('refresh_token'));
        static::assertSame(1200, $refreshedToken->getTokenValue('expires_in'));
    }

    /**
     * @dataProvider provideValidOptions
     */
    public function testValidOptionsAndValidAccessTokenWithoutRefreshTokenRequest(array $options, string $code, string $state)
    {
        $clientMock = new MockHttpClient(
            [
                new MockResponse(\json_encode([
                    'access_token' => $code,
                    'token_type' => 'test',
                    'expires_in' => 3600,
                ]), [
                    'response_headers' => [
                        'http_method' => 'GET',
                        'http_code' => 200,
                    ],
                ]),
            ]
        );

        $provider = new AuthorizationCodeProvider($clientMock, $options);

        $accessToken = $provider->fetchAccessToken(['code' => $code]);

        static::assertNotNull($accessToken->getTokenValue('access_token'));
        static::assertNull($accessToken->getTokenValue('refresh_token'));
        static::assertInternalType('int', $accessToken->getTokenValue('expires_in'));
    }

    public function provideWrongOptions(): \Generator
    {
        yield 'Missing client_id option' => [
            [
                'client_secret' => 'foo',
                'redirect_uri' => 'https://bar.com',
                'authorization_url' => 'https://bar.com/authenticate',
                'access_token_url' => 'https://bar.com/',
                'user_details_url' => 'https://bar.com/',
            ],
        ];
    }

    public function provideWrongUrls(): \Generator
    {
        yield 'Invalid urls options' => [
            [
                'client_id' => 'foo',
                'client_secret' => 'foo',
                'redirect_uri' => 'https:/bar.com',
                'authorization_url' => 'bar.com/authenticate',
                'access_token_url' => '/bar.com/',
                'user_details_url' => 'httpsbar.com/',
            ],
        ];
    }

    public function provideValidOptions(): \Generator
    {
        yield 'Valid options' => [
            [
                'client_id' => 'foo',
                'client_secret' => 'foo',
                'redirect_uri' => 'https://bar.com',
                'authorization_url' => 'https://bar.com/authenticate',
                'access_token_url' => 'https://bar.com/',
                'user_details_url' => 'https://bar.com/',
            ],
            '1234567nialbdodaizbazu7',
            '1325267BDZYABA',
        ];
    }
}
