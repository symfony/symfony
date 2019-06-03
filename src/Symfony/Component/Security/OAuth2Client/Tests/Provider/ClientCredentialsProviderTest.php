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
use Symfony\Component\Security\OAuth2Client\Provider\ClientCredentialsProvider;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ClientCredentialsProviderTest extends TestCase
{
    /**
     * @dataProvider provideWrongOptions
     */
    public function testWrongOptionsSent(array $options)
    {
        static::expectException(MissingOptionsException::class);

        $clientMock = new MockHttpClient([]);

        new ClientCredentialsProvider($clientMock, $options);
    }

    /**
     * @dataProvider provideValidOptions
     */
    public function testErrorOnAuthorizationTokenRequest(array $options, string $scope)
    {
        static::expectException(\RuntimeException::class);
        static::expectExceptionMessage(\sprintf(
            'The %s does not support the authorization process, the credentials should be obtained by the client, please refer to https://tools.ietf.org/html/rfc6749#section-4.4.1',
            ClientCredentialsProvider::class
        ));

        $clientMock = new MockHttpClient([new MockResponse()]);

        $provider = new ClientCredentialsProvider($clientMock, $options);

        $provider->fetchAuthorizationInformations(['scope' => $scope]);
    }

    /**
     * @dataProvider provideValidOptions
     */
    public function testValidOptionsAndInvalidAccessTokenRequest(array $options, string $scope)
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

        $provider = new ClientCredentialsProvider($clientMock, $options);

        $provider->fetchAccessToken(['scope' => $scope]);
    }

    /**
     * @dataProvider provideValidOptions
     */
    public function testValidOptionsAndValidAccessTokenRequestAndInvalidResponse(array $options, string $scope)
    {
        $clientMock = new MockHttpClient(
            [
                new MockResponse(\json_encode([
                    'access_token' => \uniqid(),
                    'token_type' => 'bearer',
                    'expires_in' => 3600,
                ]), [
                    'response_headers' => [
                        'Cache-Control' => 'public;s-maxage=200',
                    ],
                ]),
            ]
        );

        $provider = new ClientCredentialsProvider($clientMock, $options);

        $accessToken = $provider->fetchAccessToken([
            'scope' => $scope,
            'test' => \uniqid(),
        ]);

        static::assertNotNull($accessToken->getTokenValue('access_token'));
        static::assertNotNull($accessToken->getTokenValue('token_type'));
        static::assertNotNull($accessToken->getTokenValue('expires_in'));
    }

    /**
     * @dataProvider provideValidOptions
     */
    public function testValidOptionsAndValidAccessTokenRequestAndValidResponse(array $options, string $scope)
    {
        $clientMock = new MockHttpClient(
            [
                new MockResponse(\json_encode([
                    'access_token' => \uniqid(),
                    'token_type' => 'bearer',
                    'expires_in' => 3600,
                ]), [
                    'response_headers' => [
                        'Cache-Control' => 'no-store',
                        'Pragma' => 'no-cache',
                    ],
                ]),
            ]
        );

        $provider = new ClientCredentialsProvider($clientMock, $options);

        $accessToken = $provider->fetchAccessToken([
            'scope' => $scope,
            'test' => \uniqid(),
        ]);

        static::assertNotNull($accessToken->getTokenValue('access_token'));
        static::assertNotNull($accessToken->getTokenValue('token_type'));
        static::assertNotNull($accessToken->getTokenValue('expires_in'));
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
            'public',
        ];
    }
}
