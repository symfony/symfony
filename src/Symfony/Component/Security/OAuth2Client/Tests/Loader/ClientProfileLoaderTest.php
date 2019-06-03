<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\OAuth2Client\Tests\Loader;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Security\OAuth2Client\Loader\ClientProfileLoader;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ClientProfileLoaderTest extends TestCase
{
    /**
     * @dataProvider provideWrongAccessToken
     */
    public function testWrongAccessToken(string $clientProfileUrl, string $accessToken)
    {
        $client = new MockHttpClient([
            new MockResponse(\json_encode([
                'error' => 'This access_token seems expired.',
            ]), [
                'response_headers' => [
                    'Content-Type' => 'application/json',
                    'http_code' => 401,
                ],
            ]),
        ]);

        $loader = new ClientProfileLoader($client, $clientProfileUrl);

        $clientProfile = $loader->fetchClientProfile('GET', [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$accessToken,
        ]);

        static::assertArrayHasKey('error', $clientProfile->getContent());
    }

    /**
     * @dataProvider provideValidAccessToken
     */
    public function testValidAccessToken(string $clientProfileUrl, string $accessToken)
    {
        $client = new MockHttpClient([
            new MockResponse(\json_encode([
                'username' => 'Foo',
                'email' => 'foo@bar.com',
                'id' => 123456,
            ]), [
                'response_headers' => [
                    'Content-Type' => 'application/json',
                    'http_code' => 200,
                ],
            ]),
        ]);

        $loader = new ClientProfileLoader($client, $clientProfileUrl);

        $clientProfile = $loader->fetchClientProfile('GET', [
            'Accept' => 'application/json',
            'Authorization' => 'basic '.$accessToken,
        ]);

        static::assertArrayNotHasKey('error', $clientProfile->getContent());
        static::assertArrayHasKey('username', $clientProfile->getContent());
        static::assertSame('Foo', $clientProfile->get('username'));
        static::assertSame('foo@bar.com', $clientProfile->get('email'));
    }

    public function provideWrongAccessToken(): \Generator
    {
        yield 'Expired access_token' => [
            'http://api.foo.com/profile/user',
            \uniqid(),
        ];
    }

    public function provideValidAccessToken(): \Generator
    {
        yield 'Expired access_token' => [
            'http://api.foo.com/profile/user',
            '1234567nialbdodaizbazu7',
        ];
    }
}
