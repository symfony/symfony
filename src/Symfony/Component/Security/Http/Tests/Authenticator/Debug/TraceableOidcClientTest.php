<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\Authenticator\Debug;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Debug\TraceableOidcClient;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcClientInterface;
use Symfony\Component\Security\Http\Exception\OidcInvalidGrantException;

class TraceableOidcClientTest extends TestCase
{
    private const ID_TOKEN = 'eyJhbGciOiJSUzI1NiIsImtpZCI6ImtleS0xIn0.eyJzdWIiOiJ1c2VyLTQyIiwiZXhwIjoxNzAwMDAwMDAwfQ.raw-signature';

    public function testExchangeCodeIsRecordedWithoutItsCredentials()
    {
        $response = [
            'access_token' => 'raw-access-token',
            'id_token' => self::ID_TOKEN,
            'refresh_token' => 'raw-refresh-token',
            'token_type' => 'Bearer',
            'expires_in' => 300,
            'scope' => 'openid profile',
            'session_state' => 'raw-session-state',
        ];
        $client = $this->createMock(OidcClientInterface::class);
        $client->method('getClientAuthenticationMethod')->willReturn('client_secret_basic');
        $client->expects($this->once())->method('exchangeCode')->with('raw-code', 'https://app.example.com/callback', 'raw-verifier')->willReturn($response);

        $traceable = new TraceableOidcClient($client);

        // the decorated response is handed back untouched
        $this->assertSame($response, $traceable->exchangeCode('raw-code', 'https://app.example.com/callback', 'raw-verifier'));

        $calls = $traceable->getCalls();
        $this->assertCount(1, $calls);
        $this->assertSame('authorization_code', $calls[0]['operation']);
        $this->assertGreaterThanOrEqual(0, $calls[0]['duration']);
        $this->assertSame(['redirect_uri' => 'https://app.example.com/callback', 'code_verifier' => true, 'client_authentication' => 'client_secret_basic'], $calls[0]['request']);
        $this->assertNull($calls[0]['error']);

        $recorded = $calls[0]['response'];
        $this->assertSame('Bearer', $recorded['token_type']);
        $this->assertSame(300, $recorded['expires_in']);
        $this->assertSame('openid profile', $recorded['scope']);
        $this->assertSame('jwt', $recorded['id_token']['format']);
        $this->assertSame(['sub' => 'user-42', 'exp' => 1700000000], $recorded['id_token']['claims']);
        $this->assertSame('opaque', $recorded['access_token']['format']);
        $this->assertSame('opaque', $recorded['refresh_token']['format']);
        $this->assertSame(['session_state'], $recorded['other_fields']);

        $this->assertStringNotContainsString('raw-', json_encode($calls));
        $this->assertStringNotContainsString(self::ID_TOKEN, json_encode($calls));
    }

    public function testRefreshTokenReportsWhetherTheRefreshTokenWasRotated()
    {
        $client = $this->createStub(OidcClientInterface::class);
        $client->method('getClientAuthenticationMethod')->willReturn('none');
        $client->method('refreshToken')->willReturnOnConsecutiveCalls(
            ['access_token' => 'raw-access-2', 'refresh_token' => 'raw-refresh-2'],
            ['access_token' => 'raw-access-3', 'refresh_token' => 'raw-refresh-2'],
            ['access_token' => 'raw-access-4'],
        );

        $traceable = new TraceableOidcClient($client);
        $traceable->refreshToken('raw-refresh-1', ['openid']);
        $traceable->refreshToken('raw-refresh-2');
        $traceable->refreshToken('raw-refresh-2');

        $calls = $traceable->getCalls();
        $this->assertSame('refresh_token', $calls[0]['operation']);
        $this->assertSame(['scopes' => ['openid'], 'client_authentication' => 'none'], $calls[0]['request']);
        $this->assertTrue($calls[0]['response']['refresh_token_rotated']);
        $this->assertFalse($calls[1]['response']['refresh_token_rotated']);
        $this->assertNull($calls[2]['response']['refresh_token']);
        $this->assertFalse($calls[2]['response']['refresh_token_rotated']);
        $this->assertStringNotContainsString('raw-', json_encode($calls));
    }

    public function testFetchUserInfoRecordsTheClaims()
    {
        $client = $this->createMock(OidcClientInterface::class);
        $client->expects($this->once())->method('fetchUserInfo')->with('raw-access-token')->willReturn(['sub' => 'user-42', 'email' => 'user@example.com']);

        $traceable = new TraceableOidcClient($client);
        $this->assertSame(['sub' => 'user-42', 'email' => 'user@example.com'], $traceable->fetchUserInfo('raw-access-token'));

        $calls = $traceable->getCalls();
        $this->assertSame('userinfo', $calls[0]['operation']);
        $this->assertSame([], $calls[0]['request']);
        $this->assertSame(['claims' => ['sub' => 'user-42', 'email' => 'user@example.com']], $calls[0]['response']);
        $this->assertStringNotContainsString('raw-', json_encode($calls));
    }

    public function testAFailureIsRecordedWithTheErrorResponseOfTheProvider()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse(['error' => 'invalid_grant', 'error_description' => 'Code not valid', 'error_uri' => 'https://provider.example.com/errors/invalid_grant'], ['http_code' => 400]));
        $httpException = null;
        try {
            $httpClient->request('POST', 'https://provider.example.com/token')->getHeaders();
        } catch (ClientException $httpException) {
        }

        $client = $this->createStub(OidcClientInterface::class);
        $client->method('getClientAuthenticationMethod')->willReturn('client_secret_post');
        $client->method('exchangeCode')->willThrowException(new AuthenticationException('The OIDC token endpoint request failed.', previous: $httpException));

        $traceable = new TraceableOidcClient($client);

        try {
            $traceable->exchangeCode('raw-code', 'https://app.example.com/callback');
            $this->fail('The exception should be rethrown.');
        } catch (AuthenticationException $e) {
            $this->assertSame('The OIDC token endpoint request failed.', $e->getMessage());
        }

        $calls = $traceable->getCalls();
        $this->assertNull($calls[0]['response']);
        $this->assertSame([
            'class' => AuthenticationException::class,
            'message' => 'The OIDC token endpoint request failed.',
            'status_code' => 400,
            'error' => 'invalid_grant',
            'error_description' => 'Code not valid',
            'error_uri' => 'https://provider.example.com/errors/invalid_grant',
        ], $calls[0]['error']);
    }

    public function testAFailureWithoutAReadableResponseKeepsTheMessage()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse('<html>Bad gateway</html>', ['http_code' => 502]));
        $httpException = null;
        try {
            $httpClient->request('POST', 'https://provider.example.com/token')->getHeaders();
        } catch (\Throwable $httpException) {
        }

        $client = $this->createStub(OidcClientInterface::class);
        $client->method('getClientAuthenticationMethod')->willReturn('none');
        $client->method('refreshToken')->willThrowException(new OidcInvalidGrantException('The OIDC provider rejected the refresh token.', previous: $httpException));

        $traceable = new TraceableOidcClient($client);

        try {
            $traceable->refreshToken('raw-refresh-token');
        } catch (OidcInvalidGrantException) {
        }

        $error = $traceable->getCalls()[0]['error'];
        $this->assertSame(OidcInvalidGrantException::class, $error['class']);
        $this->assertSame(502, $error['status_code']);
        $this->assertNull($error['error']);
        $this->assertNull($error['error_description']);
    }

    public function testTheClientAuthenticationMethodIsDelegated()
    {
        $client = $this->createStub(OidcClientInterface::class);
        $client->method('getClientAuthenticationMethod')->willReturn('private_key_jwt');

        $this->assertSame('private_key_jwt', (new TraceableOidcClient($client))->getClientAuthenticationMethod());
    }

    public function testResetForgetsTheCalls()
    {
        $client = $this->createStub(OidcClientInterface::class);
        $client->method('fetchUserInfo')->willReturn(['sub' => 'user-42']);

        $traceable = new TraceableOidcClient($client);
        $traceable->fetchUserInfo('raw-access-token');
        $this->assertCount(1, $traceable->getCalls());

        $traceable->reset();
        $this->assertSame([], $traceable->getCalls());
    }
}
