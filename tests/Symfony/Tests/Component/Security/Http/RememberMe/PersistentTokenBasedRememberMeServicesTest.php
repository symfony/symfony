<?php

namespace Symfony\Tests\Component\Security\Http\RememberMe;

use Symfony\Component\Security\Core\Authentication\Token\RememberMeToken;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\Authentication\RememberMe\PersistentToken;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\RememberMe\PersistentTokenBasedRememberMeServices;
use Symfony\Component\Security\Core\Exception\TokenNotFoundException;
use Symfony\Component\Security\Core\Exception\CookieTheftException;

class PersistentTokenBasedRememberMeServicesTest extends \PHPUnit_Framework_TestCase
{
    public function testAutoLoginReturnsNullWhenNoCookie()
    {
        $service = $this->getService(null, array('name' => 'foo'));

        $this->assertNull($service->autoLogin(new Request()));
    }

    /**
     * @expectedException Symfony\Component\Security\Core\Exception\AuthenticationException
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
     * @expectedException Symfony\Component\Security\Core\Exception\TokenNotFoundException
     */
    public function testAutoLoginThrowsExceptionOnNonExistentToken()
    {
        $service = $this->getService(null, array('name' => 'foo', 'always_remember_me' => false, 'remember_me_parameter' => 'foo'));
        $request = new Request;
        $request->request->set('foo', 'true');
        $request->cookies->set('foo', $this->encodeCookie(array(
            $series = 'fooseries',
            $tokenValue = 'foovalue',
        )));

        $tokenProvider = $this->getMock('Symfony\Component\Security\Core\Authentication\RememberMe\TokenProviderInterface');
        $tokenProvider
            ->expects($this->once())
            ->method('loadTokenBySeries')
            ->will($this->throwException(new TokenNotFoundException('Token not found.')))
        ;
        $service->setTokenProvider($tokenProvider);

        $service->autoLogin($request);
    }

    /**
     * @expectedException Symfony\Component\Security\Core\Exception\UsernameNotFoundException
     */
    public function testAutoLoginThrowsExceptionOnNonExistentUser()
    {
        $userProvider = $this->getProvider();
        $service = $this->getService($userProvider, array('name' => 'foo', 'always_remember_me' => true, 'lifetime' => 3600));
        $request = new Request;
        $request->cookies->set('foo', $this->encodeCookie(array('fooseries', 'foovalue')));

        $tokenProvider = $this->getMock('Symfony\Component\Security\Core\Authentication\RememberMe\TokenProviderInterface');
        $tokenProvider
            ->expects($this->once())
            ->method('loadTokenBySeries')
            ->will($this->returnValue(new PersistentToken('fooclass', 'fooname', 'fooseries', 'foovalue', new \DateTime())))
        ;
        $service->setTokenProvider($tokenProvider);

        $userProvider
            ->expects($this->once())
            ->method('loadUserByUsername')
            ->will($this->throwException(new UsernameNotFoundException('user not found')))
        ;

        $service->autoLogin($request);
    }

    public function testAutoLoginThrowsExceptionOnStolenCookieAndRemovesItFromThePersistentBackend()
    {
        $userProvider = $this->getProvider();
        $service = $this->getService($userProvider, array('name' => 'foo', 'always_remember_me' => true));
        $request = new Request;
        $request->cookies->set('foo', $this->encodeCookie(array('fooseries', 'foovalue')));

        $tokenProvider = $this->getMock('Symfony\Component\Security\Core\Authentication\RememberMe\TokenProviderInterface');
        $service->setTokenProvider($tokenProvider);

        $tokenProvider
            ->expects($this->once())
            ->method('loadTokenBySeries')
            ->will($this->returnValue(new PersistentToken('fooclass', 'foouser', 'fooseries', 'anotherFooValue', new \DateTime())))
        ;

        $tokenProvider
            ->expects($this->once())
            ->method('deleteTokenBySeries')
            ->with($this->equalTo('fooseries'))
            ->will($this->returnValue(null))
        ;

        try {
            $service->autoLogin($request);
        } catch (CookieTheftException $theft) {
            return;
        }

        $this->fail('Expected CookieTheftException was not thrown.');
    }

    /**
     * @expectedException Symfony\Component\Security\Core\Exception\AuthenticationException
     * @expectedMessage The cookie has expired.
     */
    public function testAutoLoginDoesNotAcceptAnExpiredCookie()
    {
        $service = $this->getService(null, array('name' => 'foo', 'always_remember_me' => true, 'lifetime' => 3600));
        $request = new Request;
        $request->cookies->set('foo', $this->encodeCookie(array('fooseries', 'foovalue')));

        $tokenProvider = $this->getMock('Symfony\Component\Security\Core\Authentication\RememberMe\TokenProviderInterface');
        $tokenProvider
            ->expects($this->once())
            ->method('loadTokenBySeries')
            ->with($this->equalTo('fooseries'))
            ->will($this->returnValue(new PersistentToken('fooclass', 'username', 'fooseries', 'newFooValue', new \DateTime('yesterday'))))
        ;
        $service->setTokenProvider($tokenProvider);

        $service->autoLogin($request);
    }

    public function testAutoLogin()
    {
        $user = $this->getMock('Symfony\Component\Security\Core\User\AccountInterface');
        $user
            ->expects($this->once())
            ->method('getRoles')
            ->will($this->returnValue(array('ROLE_FOO')))
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
        $request->cookies->set('foo', $this->encodeCookie(array('fooseries', 'foovalue')));

        $tokenProvider = $this->getMock('Symfony\Component\Security\Core\Authentication\RememberMe\TokenProviderInterface');
        $tokenProvider
            ->expects($this->once())
            ->method('loadTokenBySeries')
            ->with($this->equalTo('fooseries'))
            ->will($this->returnValue(new PersistentToken('fooclass', 'foouser', 'fooseries', 'foovalue', new \DateTime())))
        ;
        $service->setTokenProvider($tokenProvider);

        $returnedToken = $service->autoLogin($request);

        $this->assertInstanceOf('Symfony\Component\Security\Core\Authentication\Token\RememberMeToken', $returnedToken);
        $this->assertInstanceOf('Symfony\Component\Security\Core\Authentication\RememberMe\PersistentTokenInterface', $returnedToken->getPersistentToken());
        $this->assertSame($user, $returnedToken->getUser());
        $this->assertEquals('fookey', $returnedToken->getKey());
    }

    public function testLogout()
    {
        $service = $this->getService(null, array('name' => 'foo', 'path' => '/foo', 'domain' => 'foodomain.foo'));
        $request = new Request();
        $request->cookies->set('foo', $this->encodeCookie(array('fooseries', 'foovalue')));
        $response = new Response();
        $token = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');

        $tokenProvider = $this->getMock('Symfony\Component\Security\Core\Authentication\RememberMe\TokenProviderInterface');
        $tokenProvider
            ->expects($this->once())
            ->method('deleteTokenBySeries')
            ->with($this->equalTo('fooseries'))
            ->will($this->returnValue(null))
        ;
        $service->setTokenProvider($tokenProvider);

        $this->assertFalse($response->headers->hasCookie('foo'));

        $service->logout($request, $response, $token);

        $cookie = $response->headers->getCookie('foo');
        $this->assertTrue($cookie->isCleared());
        $this->assertEquals('/foo', $cookie->getPath());
        $this->assertEquals('foodomain.foo', $cookie->getDomain());
    }

    public function testLogoutSimplyIgnoresNonSetRequestCookie()
    {
        $service = $this->getService(null, array('name' => 'foo', 'path' => null, 'domain' => null));
        $request = new Request;
        $response = new Response;
        $token = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');

        $tokenProvider = $this->getMock('Symfony\Component\Security\Core\Authentication\RememberMe\TokenProviderInterface');
        $tokenProvider
            ->expects($this->never())
            ->method('deleteTokenBySeries')
        ;
        $service->setTokenProvider($tokenProvider);

        $this->assertFalse($response->headers->hasCookie('foo'));
        $service->logout($request, $response, $token);

        $cookie = $response->headers->getCookie('foo');
        $this->assertTrue($cookie->isCleared());
        $this->assertNull($cookie->getPath());
        $this->assertNull($cookie->getDomain());
    }

    public function testLogoutSimplyIgnoresInvalidCookie()
    {
        $service = $this->getService(null, array('name' => 'foo', 'path' => null, 'domain' => null));
        $request = new Request;
        $request->cookies->set('foo', 'somefoovalue');
        $response = new Response;
        $token = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');

        $tokenProvider = $this->getMock('Symfony\Component\Security\Core\Authentication\RememberMe\TokenProviderInterface');
        $tokenProvider
            ->expects($this->never())
            ->method('deleteTokenBySeries')
        ;
        $service->setTokenProvider($tokenProvider);

        $this->assertFalse($response->headers->hasCookie('foo'));

        $service->logout($request, $response, $token);

        $this->assertTrue($response->headers->getCookie('foo')->isCleared());
    }

    public function testLoginFail()
    {
        $service = $this->getService(null, array('name' => 'foo', 'path' => null, 'domain' => null));
        $request = new Request();
        $response = new Response();

        $this->assertFalse($response->headers->hasCookie('foo'));

        $service->loginFail($request, $response);

        $this->assertTrue($response->headers->getCookie('foo')->isCleared());
    }

    public function testLoginSuccessRenewsRememberMeTokenWhenUsedForLogin()
    {
        $service = $this->getService(null, array('name' => 'foo', 'domain' => 'myfoodomain.foo', 'path' => '/foo/path', 'secure' => true, 'httponly' => true, 'lifetime' => 3600));
        $request = new Request;
        $response = new Response;

        $user = $this->getMock('Symfony\Component\Security\Core\User\AccountInterface');
        $user
            ->expects($this->once())
            ->method('getRoles')
            ->will($this->returnValue(array('ROLE_FOO')))
        ;

        $token = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\RememberMeToken', array(), array($user, 'fookey', 'fookey'));
        $token
            ->expects($this->once())
            ->method('getPersistentToken')
            ->will($this->returnValue(new PersistentToken('fooclass', 'foouser', 'fooseries', 'foovalue', new \DateTime())))
        ;

        $tokenProvider = $this->getMock('Symfony\Component\Security\Core\Authentication\RememberMe\TokenProviderInterface');
        $tokenProvider
            ->expects($this->once())
            ->method('updateToken')
            ->with($this->equalTo('fooseries'))
            ->will($this->returnValue(null))
        ;
        $service->setTokenProvider($tokenProvider);

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

    /**
     * @expectedException RuntimeException
     * @expectedMessage RememberMeToken must contain a PersistentTokenInterface implementation when used as login.
     */
    public function testLoginSuccessThrowsExceptionWhenRememberMeTokenDoesNotContainPersistentTokenImplementation()
    {
        $service = $this->getService(null, array('always_remember_me' => true, 'name' => 'foo'));
        $request = new Request;
        $response = new Response;

        $user = $this->getMock('Symfony\Component\Security\Core\User\AccountInterface');
        $user
            ->expects($this->once())
            ->method('getRoles')
            ->will($this->returnValue(array('ROLE_FOO')))
        ;

        $token = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\RememberMeToken', array(), array($user, 'fookey', 'fookey'));
        $token
            ->expects($this->once())
            ->method('getPersistentToken')
            ->will($this->returnValue(null))
        ;

        $service->loginSuccess($request, $response, $token);
    }

    public function testLoginSuccessSetsCookieWhenLoggedInWithNonRememberMeTokenInterfaceImplementation()
    {
        $service = $this->getService(null, array('name' => 'foo', 'domain' => 'myfoodomain.foo', 'path' => '/foo/path', 'secure' => true, 'httponly' => true, 'lifetime' => 3600, 'always_remember_me' => true));
        $request = new Request;
        $response = new Response;

        $account = $this->getMock('Symfony\Component\Security\Core\User\AccountInterface');
        $account
            ->expects($this->once())
            ->method('getUsername')
            ->will($this->returnValue('foo'))
        ;
        $token = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');
        $token
            ->expects($this->any())
            ->method('getUser')
            ->will($this->returnValue($account))
        ;

        $tokenProvider = $this->getMock('Symfony\Component\Security\Core\Authentication\RememberMe\TokenProviderInterface');
        $tokenProvider
            ->expects($this->once())
            ->method('createNewToken')
        ;
        $service->setTokenProvider($tokenProvider);

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

        return new PersistentTokenBasedRememberMeServices(array($userProvider), 'fookey', 'fookey', $options, $logger);
    }

    protected function getProvider()
    {
        $provider = $this->getMock('Symfony\Component\Security\Core\User\UserProviderInterface');
        $provider
            ->expects($this->any())
            ->method('supportsClass')
            ->will($this->returnValue(true))
        ;

        return $provider;
    }
}
