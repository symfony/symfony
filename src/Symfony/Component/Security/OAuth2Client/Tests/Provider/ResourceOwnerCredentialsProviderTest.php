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
use Symfony\Component\Security\OAuth2Client\Provider\ResourceOwnerCredentialsProvider;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ResourceOwnerCredentialsProviderTest extends TestCase
{
    /**
     * @dataProvider provideWrongOptions
     */
    public function testWrongOptionsSent(array $options)
    {
        static::expectException(MissingOptionsException::class);

        $clientMock = new MockHttpClient([]);

        new ResourceOwnerCredentialsProvider($clientMock, $options);
    }

    /**
     * @dataProvider provideValidOptions
     */
    public function testErrorOnAuthorizationTokenRequest(array $options, string $code, array $credentials = [])
    {
        static::expectException(\RuntimeException::class);
        static::expectExceptionMessage(sprintf(
            'The %s does not support the authorization process, please refer to https://tools.ietf.org/html/rfc6749#section-4.3.1',
            ResourceOwnerCredentialsProvider::class
        ));

        $clientMock = new MockHttpClient([new MockResponse()]);

        $provider = new ResourceOwnerCredentialsProvider($clientMock, $options);

        $provider->fetchAuthorizationInformations($credentials);
    }

    /**
     * @dataProvider provideValidOptions
     */
    public function testValidOptionsAndInvalidAccessTokenRequest(array $options, string $code, array $credentials = [])
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

        $provider = new ResourceOwnerCredentialsProvider($clientMock, $options);

        $provider->fetchAccessToken($credentials);
    }

    /**
     * @dataProvider provideValidOptions
     */
    public function testValidOptionsAndValidAccessTokenRequest(array $options, string $code, array $credentials = [])
    {
        $clientMock = new MockHttpClient(
            [
                new MockResponse(json_encode([
                    'access_token' => $code,
                    'token_type' => 'test',
                    'expires_in' => 3600,
                    'refresh_token' => uniqid(),
                ]), [
                    'response_headers' => [
                        'http_method' => 'GET',
                        'http_code' => 200,
                    ],
                ]),
            ]
        );

        $provider = new ResourceOwnerCredentialsProvider($clientMock, $options);

        $accessToken = $provider->fetchAccessToken($credentials);

        static::assertNotNull($accessToken->getTokenValue('access_token'));
        static::assertNotNull($accessToken->getTokenValue('token_type'));
        static::assertNull($accessToken->getTokenValue('state'));
    }

    /**
     * @dataProvider provideValidOptions
     */
    public function testValidOptionsAndValidAccessTokenRequestAndRefreshTokenRequest(array $options, string $code, array $credentials = [])
    {
        $clientMock = new MockHttpClient(
            [
                new MockResponse(json_encode([
                    'access_token' => $code,
                    'token_type' => 'test',
                    'expires_in' => 3600,
                    'refresh_token' => uniqid(),
                ]), [
                    'response_headers' => [
                        'http_method' => 'GET',
                        'http_code' => 200,
                    ],
                ]),
                new MockResponse(json_encode([
                    'access_token' => $code,
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

        $provider = new ResourceOwnerCredentialsProvider($clientMock, $options);

        $accessToken = $provider->fetchAccessToken($credentials);

        static::assertNotNull($accessToken->getTokenValue('access_token'));
        static::assertNotNull($accessToken->getTokenValue('token_type'));
        static::assertNull($accessToken->getTokenValue('state'));
        static::assertSame(3600, $accessToken->getTokenValue('expires_in'));

        $refreshedToken = $provider->refreshToken($accessToken->getTokenValue('refresh_token'), 'public');

        static::assertNotNull($refreshedToken->getTokenValue('access_token'));
        static::assertNull($refreshedToken->getTokenValue('refresh_token'));
        static::assertSame(1200, $refreshedToken->getTokenValue('expires_in'));
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
            [
                'username' => 'foo',
                'password' => 'bar',
                'scope' => 'public',
            ],
        ];
    }
}
