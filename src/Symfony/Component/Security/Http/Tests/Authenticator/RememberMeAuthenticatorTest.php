<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\Authenticator;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Authenticator\RememberMeAuthenticator;
use Symfony\Component\Security\Http\RememberMe\RememberMeDetails;
use Symfony\Component\Security\Http\RememberMe\RememberMeHandlerInterface;
use Symfony\Component\Security\Http\RememberMe\ResponseListener;

class RememberMeAuthenticatorTest extends TestCase
{
    private $rememberMeHandler;
    private $tokenStorage;
    private $authenticator;

    protected function setUp(): void
    {
        $this->rememberMeHandler = $this->createMock(RememberMeHandlerInterface::class);
        $this->tokenStorage = new TokenStorage();
        $this->authenticator = new RememberMeAuthenticator($this->rememberMeHandler, 's3cr3t', $this->tokenStorage, '_remember_me_cookie');
    }

    public function testSupportsTokenStorageWithToken()
    {
        $this->tokenStorage->setToken(new UsernamePasswordToken(new InMemoryUser('username', 'credentials'), 'main'));

        $this->assertFalse($this->authenticator->supports(Request::create('/')));
    }

    /**
     * @dataProvider provideSupportsData
     */
    public function testSupports($request, $support)
    {
        $this->assertSame($support, $this->authenticator->supports($request));
    }

    public static function provideSupportsData()
    {
        yield [Request::create('/'), false];

        $request = Request::create('/', 'GET', [], ['_remember_me_cookie' => 'rememberme']);
        yield [$request, null];

        $request = Request::create('/', 'GET', [], ['_remember_me_cookie' => 'rememberme']);
        $request->attributes->set(ResponseListener::COOKIE_ATTR_NAME, new Cookie('_remember_me_cookie', null));
        yield [$request, false];
    }

    public function testAuthenticate()
    {
        $rememberMeDetails = new RememberMeDetails(InMemoryUser::class, 'wouter', 1, 'secret');
        $request = Request::create('/', 'GET', [], ['_remember_me_cookie' => $rememberMeDetails->toString()]);
        $passport = $this->authenticator->authenticate($request);

        $this->rememberMeHandler->expects($this->once())->method('consumeRememberMeCookie')->with($this->callback(fn ($arg) => $rememberMeDetails == $arg));
        $passport->getUser(); // trigger the user loader
    }

    public function testAuthenticateWithoutToken()
    {
        $this->expectException(\LogicException::class);

        $this->authenticator->authenticate(Request::create('/'));
    }

    public function testAuthenticateWithoutOldToken()
    {
        $this->expectException(AuthenticationException::class);

        $request = Request::create('/', 'GET', [], ['_remember_me_cookie' => base64_encode('foo:bar')]);
        $this->authenticator->authenticate($request);
    }

    public function testAuthenticateWithTokenWithoutDelimiter()
    {
        $this->expectException(AuthenticationException::class);

        $request = Request::create('/', 'GET', [], ['_remember_me_cookie' => 'invalid']);
        $this->authenticator->authenticate($request);
    }
}
