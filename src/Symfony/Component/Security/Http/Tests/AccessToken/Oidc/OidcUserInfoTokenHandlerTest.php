<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\AccessToken\Oidc;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\Chunk\DataChunk;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpClient\Response\ResponseStream;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\OidcUser;
use Symfony\Component\Security\Http\AccessToken\Oidc\OidcUserInfoTokenHandler;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class OidcUserInfoTokenHandlerTest extends TestCase
{
    #[DataProvider('getClaims')]
    public function testGetsUserIdentifierFromOidcServerResponse(string $claim, string $expected)
    {
        $accessToken = 'a-secret-token';
        $claims = [
            'sub' => 'e21bf182-1538-406e-8ccb-e25a17aba39f',
            'email' => 'foo@example.com',
        ];
        $expectedUser = new OidcUser(...$claims, userIdentifier: $claims[$claim]);

        $responseMock = $this->createMock(ResponseInterface::class);
        $responseMock->expects($this->once())
            ->method('toArray')
            ->willReturn($claims);

        $clientMock = $this->createMock(HttpClientInterface::class);
        $clientMock->expects($this->once())
            ->method('request')->with('GET', '', ['auth_bearer' => $accessToken])
            ->willReturn($responseMock);

        $userBadge = (new OidcUserInfoTokenHandler($clientMock, null, $claim))->getUserBadgeFrom($accessToken);
        $actualUser = $userBadge->getUserLoader()();

        $this->assertInstanceOf(UserBadge::class, $userBadge);
        $this->assertSame($expected, $userBadge->getUserIdentifier());
        $this->assertSame($claims, $userBadge->getAttributes());
        $this->assertInstanceOf(OidcUser::class, $actualUser);
        $this->assertEquals($expectedUser, $actualUser);
        $this->assertEquals($claims, $userBadge->getAttributes());
        $this->assertEquals($claims[$claim], $actualUser->getUserIdentifier());
    }

    public static function getClaims(): iterable
    {
        yield ['sub', 'e21bf182-1538-406e-8ccb-e25a17aba39f'];
        yield ['email', 'foo@example.com'];
    }

    public function testThrowsAnExceptionIfUserPropertyIsMissing()
    {
        $responseMock = $this->createMock(ResponseInterface::class);
        $responseMock->expects($this->once())
            ->method('toArray')
            ->willReturn(['foo' => 'bar']);

        $clientMock = $this->createMock(HttpClientInterface::class);
        $clientMock->expects($this->once())
            ->method('request')->with('GET', '', ['auth_bearer' => 'a-secret-token'])
            ->willReturn($responseMock);

        $loggerMock = $this->createMock(LoggerInterface::class);
        $loggerMock->expects($this->once())
            ->method('error');

        $handler = new OidcUserInfoTokenHandler($clientMock, $loggerMock);

        $this->expectException(BadCredentialsException::class);
        $this->expectExceptionMessage('Invalid credentials.');

        $handler->getUserBadgeFrom('a-secret-token');
    }

    public function testDiscoveryFollowsUserInfoEndpointHostedOnAnotherDomain()
    {
        $claims = ['sub' => 'e21bf182-1538-406e-8ccb-e25a17aba39f'];
        $userInfoResponse = new JsonMockResponse($claims);
        $httpClient = new MockHttpClient([
            new JsonMockResponse(['userinfo_endpoint' => 'https://openidconnect.example.net/v1/userinfo']),
            $userInfoResponse,
        ]);

        $handler = new OidcUserInfoTokenHandler($httpClient);
        $handler->enableDiscovery(new ArrayAdapter(), 'oidc_config');

        $userBadge = $handler->getUserBadgeFrom('a-secret-token');

        $this->assertSame('e21bf182-1538-406e-8ccb-e25a17aba39f', $userBadge->getUserIdentifier());
        $this->assertSame('https://openidconnect.example.net/v1/userinfo', $userInfoResponse->getRequestUrl());
        $this->assertContains('Authorization: Bearer a-secret-token', $userInfoResponse->getRequestOptions()['headers']);
    }

    public function testDiscoveryDoesNotFollowRedirects()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) {
            $this->assertSame(0, $options['max_redirects']);

            return new MockResponse('', ['http_code' => 301, 'response_headers' => ['location' => 'https://other.example.com/.well-known/openid-configuration']]);
        }, 'https://oidc.example.com');

        $handler = new OidcUserInfoTokenHandler($httpClient);
        $handler->enableDiscovery(new ArrayAdapter(), 'oidc_config');

        try {
            $handler->getUserBadgeFrom('a-secret-token');
            $this->fail('A BadCredentialsException should have been thrown.');
        } catch (BadCredentialsException) {
        }

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testDiscoveryRejectsUserInfoEndpointDowngradedToHttp()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse(['userinfo_endpoint' => 'http://169.254.169.254/latest/meta-data/']),
            new JsonMockResponse(['sub' => 'e21bf182-1538-406e-8ccb-e25a17aba39f']),
        ]);

        $handler = new OidcUserInfoTokenHandler($httpClient);
        $handler->enableDiscovery(new ArrayAdapter(), 'oidc_config');

        try {
            $handler->getUserBadgeFrom('a-secret-token');
            $this->fail('A BadCredentialsException should have been thrown.');
        } catch (BadCredentialsException) {
        }

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testDiscoveryDoesNotCacheADowngradedUserInfoEndpoint()
    {
        $requestedUrls = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url) use (&$requestedUrls) {
            $requestedUrls[] = $url;

            return new JsonMockResponse(['userinfo_endpoint' => 'http://169.254.169.254/latest/meta-data/']);
        });

        $handler = new OidcUserInfoTokenHandler($httpClient);
        $handler->enableDiscovery(new ArrayAdapter(), 'oidc_config');

        for ($i = 0; $i < 2; ++$i) {
            try {
                $handler->getUserBadgeFrom('a-secret-token');
                $this->fail('A BadCredentialsException should have been thrown.');
            } catch (BadCredentialsException) {
            }
        }

        $this->assertSame([
            'https://example.com/.well-known/openid-configuration',
            'https://example.com/.well-known/openid-configuration',
        ], $requestedUrls);
    }

    public function testDiscoveryIsNotCachedWhenTheDiscoveryUrlIsUnknown()
    {
        $payload = json_encode(['userinfo_endpoint' => 'https://oidc.example.com/userinfo']);

        $configResponse = $this->createStub(ResponseInterface::class);
        $configResponse->method('getInfo')->willReturn(null);

        $userInfoResponse = $this->createStub(ResponseInterface::class);
        $userInfoResponse->method('toArray')->willReturn(['sub' => 'e21bf182-1538-406e-8ccb-e25a17aba39f']);

        $requestedUrls = [];
        $clientMock = $this->createStub(HttpClientInterface::class);
        $clientMock->method('request')->willReturnCallback(static function (string $method, string $url) use (&$requestedUrls, $configResponse, $userInfoResponse) {
            $requestedUrls[] = $url;

            return '.well-known/openid-configuration' === $url ? $configResponse : $userInfoResponse;
        });
        $clientMock->method('stream')->willReturnCallback(static fn (ResponseInterface $response) => new ResponseStream((static function () use ($response, $payload) {
            yield $response => new DataChunk(0, $payload);
        })()));

        $cache = new ArrayAdapter();
        $handler = new OidcUserInfoTokenHandler($clientMock);
        $handler->enableDiscovery($cache, 'oidc_config');

        $handler->getUserBadgeFrom('a-secret-token');
        $handler->getUserBadgeFrom('a-secret-token');

        // the instance memoizes the document, so the second authentication does not
        // re-request it; not knowing the URL that served it still keeps it out of the cache
        $this->assertFalse($cache->getItem('oidc_config.document')->isHit());
        $this->assertSame([
            '.well-known/openid-configuration',
            'https://oidc.example.com/userinfo',
            'https://oidc.example.com/userinfo',
        ], $requestedUrls);
    }

    public function testResetForcesANewDiscoveryOnTheNextAuthentication()
    {
        $payload = json_encode(['userinfo_endpoint' => 'https://oidc.example.com/userinfo']);

        $configResponse = $this->createStub(ResponseInterface::class);
        $configResponse->method('getInfo')->willReturn(null);

        $userInfoResponse = $this->createStub(ResponseInterface::class);
        $userInfoResponse->method('toArray')->willReturn(['sub' => 'e21bf182-1538-406e-8ccb-e25a17aba39f']);

        $requestedUrls = [];
        $clientMock = $this->createStub(HttpClientInterface::class);
        $clientMock->method('request')->willReturnCallback(static function (string $method, string $url) use (&$requestedUrls, $configResponse, $userInfoResponse) {
            $requestedUrls[] = $url;

            return '.well-known/openid-configuration' === $url ? $configResponse : $userInfoResponse;
        });
        $clientMock->method('stream')->willReturnCallback(static fn (ResponseInterface $response) => new ResponseStream((static function () use ($response, $payload) {
            yield $response => new DataChunk(0, $payload);
        })()));

        $cache = new ArrayAdapter();
        $handler = new OidcUserInfoTokenHandler($clientMock);
        $handler->enableDiscovery($cache, 'oidc_config');

        $handler->getUserBadgeFrom('a-secret-token');
        $handler->reset();
        $handler->getUserBadgeFrom('a-secret-token');

        // After reset(), the next authentication should request the discovery document again
        $this->assertSame([
            '.well-known/openid-configuration',
            'https://oidc.example.com/userinfo',
            '.well-known/openid-configuration',
            'https://oidc.example.com/userinfo',
        ], $requestedUrls);
    }

    #[DataProvider('provideLegacyConfigurationCacheEntries')]
    public function testDiscoveryIgnoresTheEntriesTheReplacedCodeCached(string|array $legacyEntry)
    {
        // the bare key holds what previous releases cached there, in formats of their
        // own, unchecked: the document is read and stored under a key of its own instead
        $cache = new ArrayAdapter();
        $cache->get('oidc_config', static fn () => $legacyEntry);

        $userInfoResponse = new JsonMockResponse(['sub' => 'e21bf182-1538-406e-8ccb-e25a17aba39f']);
        $httpClient = new MockHttpClient([
            new JsonMockResponse(['userinfo_endpoint' => 'https://oidc.example.com/userinfo']),
            $userInfoResponse,
        ]);

        $handler = new OidcUserInfoTokenHandler($httpClient);
        $handler->enableDiscovery($cache, 'oidc_config');

        $userBadge = $handler->getUserBadgeFrom('a-secret-token');

        $this->assertSame('e21bf182-1538-406e-8ccb-e25a17aba39f', $userBadge->getUserIdentifier());
        $this->assertSame('https://oidc.example.com/userinfo', $userInfoResponse->getRequestUrl());
        $this->assertSame($legacyEntry, $cache->getItem('oidc_config')->get());
        $this->assertIsArray($cache->getItem('oidc_config.document')->get());
    }

    public static function provideLegacyConfigurationCacheEntries(): iterable
    {
        yield 'the decoded document' => [['userinfo_endpoint' => 'http://169.254.169.254/latest/meta-data/']];
        yield 'the raw JSON' => [json_encode(['userinfo_endpoint' => 'http://169.254.169.254/latest/meta-data/'])];
    }

    public function testDiscoveryRejectsMissingUserInfoEndpoint()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse(['issuer' => 'https://example.com']),
            new JsonMockResponse(['sub' => 'e21bf182-1538-406e-8ccb-e25a17aba39f']),
        ]);

        $handler = new OidcUserInfoTokenHandler($httpClient);
        $handler->enableDiscovery(new ArrayAdapter(), 'oidc_config');

        try {
            $handler->getUserBadgeFrom('a-secret-token');
            $this->fail('A BadCredentialsException should have been thrown.');
        } catch (BadCredentialsException) {
        }

        $this->assertSame(1, $httpClient->getRequestsCount());
    }
}
