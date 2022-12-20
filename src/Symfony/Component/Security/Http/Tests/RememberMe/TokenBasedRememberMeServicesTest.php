<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\RememberMe;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Security\Core\Authentication\Token\RememberMeToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\InMemoryUserProvider;
use Symfony\Component\Security\Http\RememberMe\RememberMeServicesInterface;
use Symfony\Component\Security\Http\RememberMe\TokenBasedRememberMeServices;

/**
 * @group legacy
 */
class TokenBasedRememberMeServicesTest extends TestCase
{
    public function testAutoLoginReturnsNullWhenNoCookie()
    {
        $service = $this->getService(null, ['name' => 'foo']);

        self::assertNull($service->autoLogin(new Request()));
    }

    public function testAutoLoginThrowsExceptionOnInvalidCookie()
    {
        $service = $this->getService(null, ['name' => 'foo', 'path' => null, 'domain' => null, 'always_remember_me' => false, 'remember_me_parameter' => 'foo']);
        $request = new Request();
        $request->request->set('foo', 'true');
        $request->cookies->set('foo', 'foo');

        self::assertNull($service->autoLogin($request));
        self::assertTrue($request->attributes->get(RememberMeServicesInterface::COOKIE_ATTR_NAME)->isCleared());
    }

    public function testAutoLoginThrowsExceptionOnNonExistentUser()
    {
        $userProvider = $this->getProvider();
        $service = $this->getService($userProvider, ['name' => 'foo', 'path' => null, 'domain' => null, 'always_remember_me' => true, 'lifetime' => 3600]);
        $request = new Request();
        $request->cookies->set('foo', $this->getCookie('fooclass', 'foouser', time() + 3600, 'foopass'));

        self::assertNull($service->autoLogin($request));
        self::assertTrue($request->attributes->get(RememberMeServicesInterface::COOKIE_ATTR_NAME)->isCleared());
    }

    public function testAutoLoginDoesNotAcceptCookieWithInvalidHash()
    {
        $userProvider = $this->getProvider();
        $service = $this->getService($userProvider, ['name' => 'foo', 'path' => null, 'domain' => null, 'always_remember_me' => true, 'lifetime' => 3600]);
        $request = new Request();
        $request->cookies->set('foo', base64_encode('class:'.base64_encode('foouser').':123456789:fooHash'));

        $user = new InMemoryUser('foouser', 'foopass');
        $userProvider->createUser($user);

        self::assertNull($service->autoLogin($request));
        self::assertTrue($request->attributes->get(RememberMeServicesInterface::COOKIE_ATTR_NAME)->isCleared());
    }

    public function testAutoLoginDoesNotAcceptAnExpiredCookie()
    {
        $userProvider = $this->getProvider();
        $service = $this->getService($userProvider, ['name' => 'foo', 'path' => null, 'domain' => null, 'always_remember_me' => true, 'lifetime' => 3600]);
        $request = new Request();
        $request->cookies->set('foo', $this->getCookie('fooclass', 'foouser', time() - 1, 'foopass'));

        $user = new InMemoryUser('foouser', 'foopass');
        $userProvider->createUser($user);

        self::assertNull($service->autoLogin($request));
        self::assertTrue($request->attributes->get(RememberMeServicesInterface::COOKIE_ATTR_NAME)->isCleared());
    }

    /**
     * @dataProvider provideUsernamesForAutoLogin
     *
     * @param string $username
     */
    public function testAutoLogin($username)
    {
        $userProvider = $this->getProvider();
        $user = new InMemoryUser($username, 'foopass', ['ROLE_FOO']);
        $userProvider->createUser($user);

        $service = $this->getService($userProvider, ['name' => 'foo', 'always_remember_me' => true, 'lifetime' => 3600]);
        $request = new Request();
        $request->cookies->set('foo', $this->getCookie(InMemoryUser::class, $username, time() + 3600, 'foopass'));

        $returnedToken = $service->autoLogin($request);

        self::assertInstanceOf(RememberMeToken::class, $returnedToken);
        self::assertTrue($user->isEqualTo($returnedToken->getUser()));
        self::assertEquals('foosecret', $returnedToken->getSecret());
    }

    public function provideUsernamesForAutoLogin()
    {
        return [
            ['foouser', 'Simple username'],
            ['foo'.TokenBasedRememberMeServices::COOKIE_DELIMITER.'user', 'Username might contain the delimiter'],
        ];
    }

    public function testLogout()
    {
        $service = $this->getService(null, ['name' => 'foo', 'path' => null, 'domain' => null, 'secure' => true, 'httponly' => false]);
        $request = new Request();
        $response = new Response();
        $token = self::createMock(TokenInterface::class);

        $service->logout($request, $response, $token);

        $cookie = $request->attributes->get(RememberMeServicesInterface::COOKIE_ATTR_NAME);
        self::assertTrue($cookie->isCleared());
        self::assertEquals('/', $cookie->getPath());
        self::assertNull($cookie->getDomain());
        self::assertTrue($cookie->isSecure());
        self::assertFalse($cookie->isHttpOnly());
    }

    public function testLoginFail()
    {
        $service = $this->getService(null, ['name' => 'foo', 'path' => '/foo', 'domain' => 'foodomain.foo']);
        $request = new Request();

        $service->loginFail($request);

        $cookie = $request->attributes->get(RememberMeServicesInterface::COOKIE_ATTR_NAME);
        self::assertTrue($cookie->isCleared());
        self::assertEquals('/foo', $cookie->getPath());
        self::assertEquals('foodomain.foo', $cookie->getDomain());
    }

    public function testLoginSuccessIgnoresTokensWhichDoNotContainAnUserInterfaceImplementation()
    {
        $service = $this->getService(null, ['name' => 'foo', 'always_remember_me' => true, 'path' => null, 'domain' => null]);
        $request = new Request();
        $response = new Response();
        $token = self::createMock(TokenInterface::class);
        $token
            ->expects(self::once())
            ->method('getUser')
            ->willReturn('foo')
        ;

        $cookies = $response->headers->getCookies();
        self::assertCount(0, $cookies);

        $service->loginSuccess($request, $response, $token);

        $cookies = $response->headers->getCookies();
        self::assertCount(0, $cookies);
    }

    public function testLoginSuccess()
    {
        $service = $this->getService(null, ['name' => 'foo', 'domain' => 'myfoodomain.foo', 'path' => '/foo/path', 'secure' => true, 'httponly' => true, 'samesite' => Cookie::SAMESITE_STRICT, 'lifetime' => 3600, 'always_remember_me' => true]);
        $request = new Request();
        $response = new Response();

        $user = new InMemoryUser('foouser', 'foopass');
        $token = self::createMock(TokenInterface::class);
        $token
            ->expects(self::atLeastOnce())
            ->method('getUser')
            ->willReturn($user)
        ;

        $cookies = $response->headers->getCookies();
        self::assertCount(0, $cookies);

        $service->loginSuccess($request, $response, $token);

        $cookies = $response->headers->getCookies(ResponseHeaderBag::COOKIES_ARRAY);
        $cookie = $cookies['myfoodomain.foo']['/foo/path']['foo'];
        self::assertFalse($cookie->isCleared());
        self::assertTrue($cookie->isSecure());
        self::assertTrue($cookie->isHttpOnly());
        self::assertTrue($cookie->getExpiresTime() > time() + 3590 && $cookie->getExpiresTime() < time() + 3610);
        self::assertEquals('myfoodomain.foo', $cookie->getDomain());
        self::assertEquals('/foo/path', $cookie->getPath());
        self::assertSame(Cookie::SAMESITE_STRICT, $cookie->getSameSite());
    }

    protected function getCookie($class, $username, $expires, $password)
    {
        $service = $this->getService();
        $r = new \ReflectionMethod($service, 'generateCookieValue');
        $r->setAccessible(true);

        return $r->invoke($service, $class, $username, $expires, $password);
    }

    protected function encodeCookie(array $parts)
    {
        $service = $this->getService();
        $r = new \ReflectionMethod($service, 'encodeCookie');
        $r->setAccessible(true);

        return $r->invoke($service, $parts);
    }

    protected function getService($userProvider = null, $options = [], $logger = null)
    {
        if (null === $userProvider) {
            $userProvider = $this->getProvider();
        }

        $service = new TokenBasedRememberMeServices([$userProvider], 'foosecret', 'fookey', $options, $logger);

        return $service;
    }

    protected function getProvider()
    {
        return new InMemoryUserProvider();
    }
}
