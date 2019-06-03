<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\OAuthServer\Tests\Request;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\OAuthServer\Request\AccessTokenRequest;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class AccessTokenRequestTest extends TestCase
{
    public function testAuthorizationCodeAccessTokenRequestFromGlobals()
    {
        $_GET['grant_type'] = 'authorization_code';
        $_GET['code'] = \uniqid();
        $_GET['redirect_uri'] = 'https://foo.com/oauth';
        $_GET['client_id'] = \uniqid();

        $request = AccessTokenRequest::create();

        static::assertSame('globals', $request->getValue('type'));
        static::assertNull($request->getValue('username'));
        static::assertNull($request->getValue('password'));
        static::assertNull($request->getValue('scope'));
        static::assertNotNull($request->getValue('grant_type'));
        static::assertNotNull($request->getValue('code'));
        static::assertNotNull($request->getValue('redirect_uri'));
        static::assertNotNull($request->getValue('client_id'));
        static::assertArrayNotHasKey('username', $request->returnAsReadOnly());
        static::assertArrayNotHasKey('password', $request->returnAsReadOnly());
        static::assertArrayNotHasKey('scope', $request->returnAsReadOnly());
        static::assertArrayHasKey('grant_type', $request->returnAsReadOnly());
        static::assertArrayHasKey('code', $request->returnAsReadOnly());
        static::assertArrayHasKey('redirect_uri', $request->returnAsReadOnly());
        static::assertArrayHasKey('client_id', $request->returnAsReadOnly());

        unset($_GET['grant_type']);
        unset($_GET['code']);
        unset($_GET['redirect_uri']);
        unset($_GET['client_id']);
    }

    public function testAuthorizationCodeAccessTokenRequestFromHttpFoundation()
    {
        $requestMock = Request::create('/oauth', 'GET', [
            'grant_type' => 'authorization_code',
            'code' => \uniqid(),
            'redirect_uri' => 'https://foo.com/oauth',
            'client_id' => \uniqid(),
        ]);

        $request = AccessTokenRequest::create($requestMock);

        static::assertSame('http_foundation', $request->getValue('type'));
        static::assertNull($request->getValue('username'));
        static::assertNull($request->getValue('password'));
        static::assertNull($request->getValue('scope'));
        static::assertNotNull($request->getValue('grant_type'));
        static::assertNotNull($request->getValue('code'));
        static::assertNotNull($request->getValue('redirect_uri'));
        static::assertNotNull($request->getValue('client_id'));
        static::assertArrayNotHasKey('username', $request->returnAsReadOnly());
        static::assertArrayNotHasKey('password', $request->returnAsReadOnly());
        static::assertArrayNotHasKey('scope', $request->returnAsReadOnly());
        static::assertArrayHasKey('grant_type', $request->returnAsReadOnly());
        static::assertArrayHasKey('code', $request->returnAsReadOnly());
        static::assertArrayHasKey('redirect_uri', $request->returnAsReadOnly());
        static::assertArrayHasKey('client_id', $request->returnAsReadOnly());
    }

    public function testAuthorizationCodeAccessTokenRequestFromPsr7Request()
    {
        $requestMock = $this->createMock(ServerRequestInterface::class);
        $requestMock->method('getQueryParams')->willReturn([
            'grant_type' => 'authorization_code',
            'code' => \uniqid(),
            'redirect_uri' => 'https://foo.com/oauth',
            'client_id' => \uniqid(),
        ]);
        $requestMock->method('getParsedBody')->willReturn([]);
        $requestMock->method('getServerParams')->willReturn([]);

        $request = AccessTokenRequest::create($requestMock);

        static::assertSame('psr-7', $request->getValue('type'));
        static::assertNull($request->getValue('username'));
        static::assertNull($request->getValue('password'));
        static::assertNull($request->getValue('scope'));
        static::assertNotNull($request->getValue('grant_type'));
        static::assertNotNull($request->getValue('code'));
        static::assertNotNull($request->getValue('redirect_uri'));
        static::assertNotNull($request->getValue('client_id'));
        static::assertArrayNotHasKey('username', $request->returnAsReadOnly());
        static::assertArrayNotHasKey('password', $request->returnAsReadOnly());
        static::assertArrayNotHasKey('scope', $request->returnAsReadOnly());
        static::assertArrayHasKey('grant_type', $request->returnAsReadOnly());
        static::assertArrayHasKey('code', $request->returnAsReadOnly());
        static::assertArrayHasKey('redirect_uri', $request->returnAsReadOnly());
        static::assertArrayHasKey('client_id', $request->returnAsReadOnly());
    }

    public function testResourceOwnerCredentialsAccessTokenRequestFromGlobals()
    {
        $_GET['grant_type'] = 'password';
        $_GET['username'] = 'foo';
        $_GET['password'] = 'bar';
        $_GET['scope'] = 'public';

        $request = AccessTokenRequest::create();

        static::assertSame('globals', $request->getValue('type'));
        static::assertNull($request->getValue('code'));
        static::assertNull($request->getValue('redirect_uri'));
        static::assertNull($request->getValue('client_id'));
        static::assertNotNull($request->getValue('grant_type'));
        static::assertNotNull($request->getValue('username'));
        static::assertNotNull($request->getValue('password'));
        static::assertNotNull($request->getValue('scope'));
        static::assertArrayNotHasKey('code', $request->returnAsReadOnly());
        static::assertArrayNotHasKey('redirect_uri', $request->returnAsReadOnly());
        static::assertArrayNotHasKey('client_id', $request->returnAsReadOnly());
        static::assertArrayHasKey('grant_type', $request->returnAsReadOnly());
        static::assertArrayHasKey('username', $request->returnAsReadOnly());
        static::assertArrayHasKey('password', $request->returnAsReadOnly());
        static::assertArrayHasKey('scope', $request->returnAsReadOnly());

        unset($_GET['grant_type']);
        unset($_GET['username']);
        unset($_GET['password']);
        unset($_GET['scope']);
    }

    public function testResourceOwnerCredentialsAccessTokenRequestFromHttpFoundation()
    {
        $requestMock = Request::create('/oauth', 'GET', [
            'grant_type' => 'password',
            'username' => 'foo',
            'password' => 'bar',
            'scope' => 'public',
        ]);

        $request = AccessTokenRequest::create($requestMock);

        static::assertSame('http_foundation', $request->getValue('type'));
        static::assertNull($request->getValue('code'));
        static::assertNull($request->getValue('redirect_uri'));
        static::assertNull($request->getValue('client_id'));
        static::assertNotNull($request->getValue('grant_type'));
        static::assertNotNull($request->getValue('username'));
        static::assertNotNull($request->getValue('password'));
        static::assertNotNull($request->getValue('scope'));
        static::assertArrayNotHasKey('code', $request->returnAsReadOnly());
        static::assertArrayNotHasKey('redirect_uri', $request->returnAsReadOnly());
        static::assertArrayNotHasKey('client_id', $request->returnAsReadOnly());
        static::assertArrayHasKey('grant_type', $request->returnAsReadOnly());
        static::assertArrayHasKey('username', $request->returnAsReadOnly());
        static::assertArrayHasKey('password', $request->returnAsReadOnly());
        static::assertArrayHasKey('scope', $request->returnAsReadOnly());
    }

    public function testResourceOwnerCredentialsAccessTokenRequestFromPsr7Request()
    {
        $requestMock = $this->createMock(ServerRequestInterface::class);
        $requestMock->method('getQueryParams')->willReturn([
            'grant_type' => 'password',
            'username' => 'foo',
            'password' => 'bar',
            'scope' => 'public',
        ]);
        $requestMock->method('getParsedBody')->willReturn([]);
        $requestMock->method('getServerParams')->willReturn([]);

        $request = AccessTokenRequest::create($requestMock);

        static::assertSame('psr-7', $request->getValue('type'));
        static::assertNull($request->getValue('code'));
        static::assertNull($request->getValue('redirect_uri'));
        static::assertNull($request->getValue('client_id'));
        static::assertNotNull($request->getValue('grant_type'));
        static::assertNotNull($request->getValue('username'));
        static::assertNotNull($request->getValue('password'));
        static::assertNotNull($request->getValue('scope'));
        static::assertArrayNotHasKey('code', $request->returnAsReadOnly());
        static::assertArrayNotHasKey('redirect_uri', $request->returnAsReadOnly());
        static::assertArrayNotHasKey('client_id', $request->returnAsReadOnly());
        static::assertArrayHasKey('grant_type', $request->returnAsReadOnly());
        static::assertArrayHasKey('username', $request->returnAsReadOnly());
        static::assertArrayHasKey('password', $request->returnAsReadOnly());
        static::assertArrayHasKey('scope', $request->returnAsReadOnly());
    }

    public function testClientCredentialsAccessTokenRequestFromGlobals()
    {
        $_GET['grant_type'] = 'client_credentials';
        $_GET['scope'] = 'public';

        $request = AccessTokenRequest::create();

        static::assertSame('globals', $request->getValue('type'));
        static::assertNull($request->getValue('code'));
        static::assertNull($request->getValue('redirect_uri'));
        static::assertNull($request->getValue('client_id'));
        static::assertNull($request->getValue('username'));
        static::assertNull($request->getValue('password'));
        static::assertNotNull($request->getValue('grant_type'));
        static::assertNotNull($request->getValue('scope'));
        static::assertArrayNotHasKey('code', $request->returnAsReadOnly());
        static::assertArrayNotHasKey('redirect_uri', $request->returnAsReadOnly());
        static::assertArrayNotHasKey('client_id', $request->returnAsReadOnly());
        static::assertArrayNotHasKey('username', $request->returnAsReadOnly());
        static::assertArrayNotHasKey('password', $request->returnAsReadOnly());
        static::assertArrayHasKey('grant_type', $request->returnAsReadOnly());
        static::assertArrayHasKey('scope', $request->returnAsReadOnly());

        unset($_GET['grant_type']);
        unset($_GET['scope']);
    }

    public function testClientCredentialsAccessTokenRequestFromHttpFoundation()
    {
        $requestMock = Request::create('/oauth', 'GET', [
            'grant_type' => 'client_credentials',
            'scope' => 'public',
        ]);

        $request = AccessTokenRequest::create($requestMock);

        static::assertSame('http_foundation', $request->getValue('type'));
        static::assertNull($request->getValue('code'));
        static::assertNull($request->getValue('redirect_uri'));
        static::assertNull($request->getValue('client_id'));
        static::assertNull($request->getValue('username'));
        static::assertNull($request->getValue('password'));
        static::assertNotNull($request->getValue('grant_type'));
        static::assertNotNull($request->getValue('scope'));
        static::assertArrayNotHasKey('code', $request->returnAsReadOnly());
        static::assertArrayNotHasKey('redirect_uri', $request->returnAsReadOnly());
        static::assertArrayNotHasKey('client_id', $request->returnAsReadOnly());
        static::assertArrayNotHasKey('username', $request->returnAsReadOnly());
        static::assertArrayNotHasKey('password', $request->returnAsReadOnly());
        static::assertArrayHasKey('grant_type', $request->returnAsReadOnly());
        static::assertArrayHasKey('scope', $request->returnAsReadOnly());
    }

    public function testClientCredentialsAccessTokenRequestFromPsr7Request()
    {
        $requestMock = $this->createMock(ServerRequestInterface::class);
        $requestMock->method('getQueryParams')->willReturn([
            'grant_type' => 'client_credentials',
            'scope' => 'public',
        ]);
        $requestMock->method('getParsedBody')->willReturn([]);
        $requestMock->method('getServerParams')->willReturn([]);

        $request = AccessTokenRequest::create($requestMock);

        static::assertSame('psr-7', $request->getValue('type'));
        static::assertNull($request->getValue('code'));
        static::assertNull($request->getValue('redirect_uri'));
        static::assertNull($request->getValue('client_id'));
        static::assertNull($request->getValue('username'));
        static::assertNull($request->getValue('password'));
        static::assertNotNull($request->getValue('grant_type'));
        static::assertNotNull($request->getValue('scope'));
        static::assertArrayNotHasKey('code', $request->returnAsReadOnly());
        static::assertArrayNotHasKey('redirect_uri', $request->returnAsReadOnly());
        static::assertArrayNotHasKey('client_id', $request->returnAsReadOnly());
        static::assertArrayNotHasKey('username', $request->returnAsReadOnly());
        static::assertArrayNotHasKey('password', $request->returnAsReadOnly());
        static::assertArrayHasKey('grant_type', $request->returnAsReadOnly());
        static::assertArrayHasKey('scope', $request->returnAsReadOnly());
    }
}
