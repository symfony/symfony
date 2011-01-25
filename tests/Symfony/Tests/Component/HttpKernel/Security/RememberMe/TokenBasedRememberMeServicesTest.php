<?php

namespace Symfony\Tests\Component\HttpKernel\Security\RememberMe;

use Symfony\Component\Security\Authentication\Token\RememberMeToken;
use Symfony\Component\Security\Authentication\Token\Token;
use Symfony\Component\Security\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Authentication\RememberMe\PersistentToken;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Security\RememberMe\TokenBasedRememberMeServices;
use Symfony\Component\Security\Exception\TokenNotFoundException;
use Symfony\Component\Security\Exception\CookieTheftException;

class TokenBasedRememberMeServicesTest extends \PHPUnit_Framework_TestCase
{
    public function testAutoLoginReturnsNullWhenNoCookie()
    {
        $service = $this->getService(null, array('name' => 'foo'));

        $this->assertNull($service->autoLogin(new Request()));
    }

    /**
     * @expectedException Symfony\Component\Security\Exception\AuthenticationException
     * @expectedMessage The cookie is invalid.
     */
    public function testAutoLoginThrowsExceptionOnInvalidCookie()
    {
        $service = $this->getService(null, array('name' => 'foo', 'always_remember_me' => false, 'remember_me_parameter' => 'foo'));
        $request = new Request;
        $request->request->set('foo', 'true');
        $request->cookies->set('foo', 'foo');

        $service->autoLogin($request);
    }

    /**
     * @expectedException Symfony\Component\Security\Exception\UsernameNotFoundException
     */
    public function testAutoLoginThrowsExceptionOnNonExistentUser()
    {
        $userProvider = $this->getProvider();
        $service = $this->getService($userProvider, array('name' => 'foo', 'always_remember_me' => true, 'lifetime' => 3600));
        $request = new Request;
        $request->cookies->set('foo', $this->getCookie('fooclass', 'foouser', time()+3600, 'foopass'));

        $userProvider
            ->expects($this->once())
            ->method('loadUserByUsername')
            ->will($this->throwException(new UsernameNotFoundException('user not found')))
        ;

        $service->autoLogin($request);
    }

    /**
     * @expectedException Symfony\Component\Security\Exception\AuthenticationException
     * @expectedMessage The cookie's hash is invalid.
     */
    public function testAutoLoginDoesNotAcceptCookieWithInvalidHash()
    {
        $userProvider = $this->getProvider();
        $service = $this->getService($userProvider, array('name' => 'foo', 'always_remember_me' => true, 'lifetime' => 3600));
        $request = new Request;
        $request->cookies->set('foo', base64_encode('class:'.base64_encode('foouser').':123456789:fooHash'));

        $user = $this->getMock('Symfony\Component\Security\User\AccountInterface');
        $user
            ->expects($this->once())
            ->method('getPassword')
            ->will($this->returnValue('foopass'))
        ;

        $userProvider
            ->expects($this->once())
            ->method('loadUserByUsername')
            ->with($this->equalTo('foouser'))
            ->will($this->returnValue($user))
        ;

        $service->autoLogin($request);
    }

    /**
     * @expectedException Symfony\Component\Security\Exception\AuthenticationException
     * @expectedMessage The cookie has expired.
     */
    public function testAutoLoginDoesNotAcceptAnExpiredCookie()
    {
        $userProvider = $this->getProvider();
        $service = $this->getService($userProvider, array('name' => 'foo', 'always_remember_me' => true, 'lifetime' => 3600));
        $request = new Request;
        $request->cookies->set('foo', $this->getCookie('fooclass', 'foouser', time() - 1, 'foopass'));

        $user = $this->getMock('Symfony\Component\Security\User\AccountInterface');
        $user
            ->expects($this->once())
            ->method('getPassword')
            ->will($this->returnValue('foopass'))
        ;

        $userProvider
            ->expects($this->once())
            ->method('loadUserByUsername')
            ->with($this->equalTo('foouser'))
            ->will($this->returnValue($user))
        ;

        $service->autoLogin($request);
    }

    public function testAutoLogin()
    {
        $user = $this->getMock('Symfony\Component\Security\User\AccountInterface');
        $user
            ->expects($this->once())
            ->method('getRoles')
            ->will($this->returnValue(array('ROLE_FOO')))
        ;
        $user
            ->expects($this->once())
            ->method('getPassword')
            ->will($this->returnValue('foopass'))
        ;

        $userProvider = $this->getProvider();
        $userProvider
            ->expects($this->once())
            ->method('loadUserByUsername')
            ->with($this->equalTo('foouser'))
            ->will($this->returnValue($user))
        ;

        $service = $this->getService($userProvider, array('name' => 'foo', 'always_remember_me' => true, 'lifetime' => 3600));
        $request = new Request;
        $request->cookies->set('foo', $this->getCookie('fooclass', 'foouser', time()+3600, 'foopass'));

        $returnedToken = $service->autoLogin($request);

        $this->assertInstanceOf('Symfony\Component\Security\Authentication\Token\RememberMeToken', $returnedToken);
        $this->assertSame($user, $returnedToken->getUser());
        $this->assertEquals('fookey', $returnedToken->getKey());
    }

    public function testLogout()
    {
        $service = $this->getService(null, array('name' => 'foo', 'path' => null, 'domain' => null));
        $request = new Request();
        $response = new Response();
        $token = $this->getMock('Symfony\Component\Security\Authentication\Token\TokenInterface');

        $this->assertFalse($response->headers->hasCookie('foo'));

        $service->logout($request, $response, $token);

        $cookie = $response->headers->getCookie('foo');
        $this->assertTrue($cookie->isCleared());
        $this->assertNull($cookie->getPath());
        $this->assertNull($cookie->getDomain());
    }

    public function testLoginFail()
    {
        $service = $this->getService(null, array('name' => 'foo', 'path' => '/foo', 'domain' => 'foodomain.foo'));
        $request = new Request();
        $response = new Response();

        $this->assertFalse($response->headers->hasCookie('foo'));

        $service->loginFail($request, $response);

        $cookie = $response->headers->getCookie('foo');
        $this->assertTrue($cookie->isCleared());
        $this->assertEquals('/foo', $cookie->getPath());
        $this->assertEquals('foodomain.foo', $cookie->getDomain());
    }

    public function testLoginSuccessDoesNotRenewRememberMeToken()
    {
        $service = $this->getService(null, array('name' => 'foo', 'domain' => 'myfoodomain.foo', 'path' => '/foo/path', 'secure' => true, 'httponly' => true, 'lifetime' => 3600));
        $request = new Request;
        $response = new Response;

        $user = $this->getMock('Symfony\Component\Security\User\AccountInterface');
        $user
            ->expects($this->once())
            ->method('getRoles')
            ->will($this->returnValue(array('ROLE_FOO')))
        ;

        $token = new RememberMeToken($user, 'fookey', 'foo');

        $this->assertFalse($response->headers->hasCookie('foo'));

        $service->loginSuccess($request, $response, $token);

        $this->assertFalse($response->headers->hasCookie('foo'));
    }

    public function testLoginSuccessIgnoresTokensWhichDoNotContainAnAccountInterfaceImplementation()
    {
        $service = $this->getService(null, array('name' => 'foo', 'always_remember_me' => true));
        $request = new Request;
        $response = new Response;
        $token = $this->getMock('Symfony\Component\Security\Authentication\Token\TokenInterface');
        $token
            ->expects($this->once())
            ->method('getUser')
            ->will($this->returnValue('foo'))
        ;

        $this->assertFalse($response->headers->hasCookie('foo'));

        $service->loginSuccess($request, $response, $token);

        $this->assertFalse($response->headers->hasCookie('foo'));
    }

    public function testLoginSuccess()
    {
        $service = $this->getService(null, array('name' => 'foo', 'domain' => 'myfoodomain.foo', 'path' => '/foo/path', 'secure' => true, 'httponly' => true, 'lifetime' => 3600, 'always_remember_me' => true));
        $request = new Request;
        $response = new Response;

        $token = $this->getMock('Symfony\Component\Security\Authentication\Token\TokenInterface');
        $user = $this->getMock('Symfony\Component\Security\User\AccountInterface');
        $user
            ->expects($this->once())
            ->method('getPassword')
            ->will($this->returnValue('foopass'))
        ;
        $user
            ->expects($this->once())
            ->method('getUsername')
            ->will($this->returnValue('foouser'))
        ;
        $token
            ->expects($this->atLeastOnce())
            ->method('getUser')
            ->will($this->returnValue($user))
        ;

        $this->assertFalse($response->headers->hasCookie('foo'));

        $service->loginSuccess($request, $response, $token);

        $cookie = $response->headers->getCookie('foo');
        $this->assertFalse($cookie->isCleared());
        $this->assertTrue($cookie->isSecure());
        $this->assertTrue($cookie->isHttponly());
        $this->assertTrue($cookie->getExpire() > time() + 3590 && $cookie->getExpire() < time() + 3610);
        $this->assertEquals('myfoodomain.foo', $cookie->getDomain());
        $this->assertEquals('/foo/path', $cookie->getPath());
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

    protected function getService($userProvider = null, $options = array(), $logger = null)
    {
        if (null === $userProvider) {
            $userProvider = $this->getProvider();
        }

        $service = new TokenBasedRememberMeServices(array($userProvider), 'fookey', 'fookey', $options, $logger);

        return $service;
    }

    protected function getProvider()
    {
        $provider = $this->getMock('Symfony\Component\Security\User\UserProviderInterface');
        $provider
            ->expects($this->any())
            ->method('supportsClass')
            ->will($this->returnValue(true))
        ;

        return $provider;
    }
}
