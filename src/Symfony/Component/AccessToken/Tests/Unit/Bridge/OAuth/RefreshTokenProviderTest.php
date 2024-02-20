<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare (strict_types=1);

namespace Symfony\Component\AccessToken\Tests\Unit\Bridge\OAuth;

use PHPUnit\Framework\TestCase;
use Symfony\Component\AccessToken\Bridge\OAuth\ClientCredentials;
use Symfony\Component\AccessToken\Bridge\OAuth\OAuthFactory;
use Symfony\Component\AccessToken\Bridge\OAuth\RefreshTokenCredentials;
use Symfony\Component\AccessToken\Bridge\OAuth\RefreshTokenProvider;
use Symfony\Component\AccessToken\Credentials\BasicAuthCredentials;
use Symfony\Component\AccessToken\Credentials\Dsn;
use Symfony\Component\AccessToken\Exception\ProviderFetchException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Pierre Rineau <pierre.rineau@processus.org>
 */
class RefreshTokenProviderTest extends TestCase
{
    public function testSupports(): void
    {
        $instance = new RefreshTokenProvider(new MockHttpClient());

        self::assertTrue($instance->supports(new RefreshTokenCredentials('foo')));
        self::assertFalse($instance->supports(new ClientCredentials('foo', 'bar')));
        self::assertFalse($instance->supports(new BasicAuthCredentials('foo')));
    }

    public function testFetchToken(): void
    {
        $dsn = "oauth://foo:bar@example.tld?scope=baz&unexpected=value&grant_type=refresh_token&refresh_token=some_value";
        $httpClient = new MockHttpClient();
        $instance = new RefreshTokenProvider($httpClient);

        $requestCatcher = function (string $method, string $uri, array $options): ResponseInterface {
            $this->assertSame('post', \strtolower($method));
            $this->assertSame('https://example.tld/?unexpected=value', $uri);

            return new MockResponse(\json_encode([
                'access_token' => 'some_value',
                'token_type' => 'some_type',
            ]));
        };

        $httpClient->setResponseFactory($requestCatcher);
        $token = $instance->getAccessToken((new OAuthFactory())->createCredentials(Dsn::fromString($dsn)));

        self::assertSame('some_value', $token->getValue());
        self::assertSame('some_type', $token->getType());
    }

    public function testRaiseErrorWhenNotJson(): void
    {
        $dsn = "oauth://foo:bar@example.tld?grant_type=refresh_token&refresh_token=some_value";
        $httpClient = new MockHttpClient();
        $instance = new RefreshTokenProvider($httpClient);

        $requestCatcher = function (string $method, string $uri, array $options): ResponseInterface {
            return new MockResponse('foo');
        };

        $httpClient->setResponseFactory($requestCatcher);
        self::expectException(ProviderFetchException::class);
        self::expectExceptionMessageMatches('/OAuth2 token response is not JSON/');
        $instance->getAccessToken((new OAuthFactory())->createCredentials(Dsn::fromString($dsn)));
    }

    public function testRaiseErrorWhenNoToken(): void
    {
        $dsn = "oauth://foo:bar@example.tld?grant_type=refresh_token&refresh_token=some_value";
        $httpClient = new MockHttpClient();
        $instance = new RefreshTokenProvider($httpClient);

        $requestCatcher = function (string $method, string $uri, array $options): ResponseInterface {
            return new MockResponse('{}');
        };

        $httpClient->setResponseFactory($requestCatcher);
        self::expectException(ProviderFetchException::class);
        self::expectExceptionMessageMatches('/OAuth2 token is missing from response/');
        $instance->getAccessToken((new OAuthFactory())->createCredentials(Dsn::fromString($dsn)));
    }
}
