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
use Symfony\Component\Security\OAuth2Client\Provider\ImplicitProvider;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ImplicitProviderTest extends TestCase
{
    /**
     * @dataProvider provideWrongOptions
     */
    public function testWrongOptionsSent(array $options)
    {
        static::expectException(MissingOptionsException::class);

        $clientMock = new MockHttpClient([]);

        new ImplicitProvider($clientMock, $options);
    }

    /**
     * @dataProvider provideValidOptions
     */
    public function testErrorOnAuthorizationTokenRequest(array $options, string $code, string $state)
    {
        static::expectException(\RuntimeException::class);
        static::expectExceptionMessage(\sprintf(
            'The %s doesn\'t support the authorization process, please refer to https://tools.ietf.org/html/rfc6749#section-4.2',
            ImplicitProvider::class
        ));

        $clientMock = new MockHttpClient([new MockResponse()]);

        $provider = new ImplicitProvider($clientMock, $options);

        $provider->fetchAuthorizationInformations(['scope' => 'test', 'state' => $state]);
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

        $provider = new ImplicitProvider($clientMock, $options);

        $provider->fetchAccessToken(['scope' => 'public', 'state' => $state]);
    }

    /**
     * @dataProvider provideValidOptions
     */
    public function testValidOptionsAndValidAccessTokenRequest(array $options, string $code, string $state)
    {
        $clientMock = new MockHttpClient(
            [
                new MockResponse(\sprintf('https://bar.com/authenticate?access_token=%s&token_type=valid&state=%s', $code, $state), [
                    'response_headers' => [
                        'http_method' => 'GET',
                        'http_code' => 200,
                    ],
                ]),
            ]
        );

        $provider = new ImplicitProvider($clientMock, $options);

        $accessToken = $provider->fetchAccessToken(['scope' => 'public', 'state' => $state]);

        static::assertNotNull($accessToken->getTokenValue('access_token'));
        static::assertNotNull($accessToken->getTokenValue('token_type'));
        static::assertNotNull($accessToken->getTokenValue('state'));
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
            '1325267BDZYABA',
        ];
    }
}
