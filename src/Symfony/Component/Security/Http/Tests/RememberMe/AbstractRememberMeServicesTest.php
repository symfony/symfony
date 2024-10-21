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
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\RememberMe\AbstractRememberMeServices;
use Symfony\Component\Security\Http\RememberMe\RememberMeServicesInterface;

/**
 * @group legacy
 */
class AbstractRememberMeServicesTest extends TestCase
{
    public function testGetRememberMeParameter()
    {
        $service = $this->getService(null, ['remember_me_parameter' => 'foo']);

        $this->assertEquals('foo', $service->getRememberMeParameter());
    }

    public function testGetSecret()
    {
        $service = $this->getService();
        $this->assertEquals('foosecret', $service->getSecret());
    }

    public function testAutoLoginReturnsNullWhenNoCookie()
    {
        $service = $this->getService(null, ['name' => 'foo', 'path' => null, 'domain' => null]);

        $this->assertNull($service->autoLogin(new Request()));
    }

    public function testAutoLoginReturnsNullAfterLoginFail()
    {
        $service = $this->getService(null, ['name' => 'foo', 'path' => null, 'domain' => null]);

        $request = new Request();
        $request->cookies->set('foo', 'foo');

        $service->loginFail($request);
        $this->assertNull($service->autoLogin($request));
    }

    public function testAutoLoginThrowsExceptionWhenImplementationDoesNotReturnUserInterface()
    {
        $this->expectException(\RuntimeException::class);
        $service = $this->getService(null, ['name' => 'foo', 'path' => null, 'domain' => null]);
        $request = new Request();
        $request->cookies->set('foo', 'foo');

        $service
            ->expects($this->once())
            ->method('processAutoLoginCookie')
            ->willReturn(null)
        ;

        $service->autoLogin($request);
    }

    public function testAutoLogin()
    {
        $service = $this->getService(null, ['name' => 'foo', 'path' => null, 'domain' => null]);
        $request = new Request();
        $request->cookies->set('foo', 'foo');

        $user = $this->createMock(UserInterface::class);
        $user
            ->expects($this->once())
            ->method('getRoles')
            ->willReturn([])
        ;

        $service
            ->expects($this->once())
            ->method('processAutoLoginCookie')
            ->willReturn($user)
        ;

        $returnedToken = $service->autoLogin($request);

        $this->assertSame($user, $returnedToken->getUser());
        $this->assertSame('foosecret', $returnedToken->getSecret());
        $this->assertSame('fookey', $returnedToken->getFirewallName());
    }

    /**
     * @dataProvider provideOptionsForLogout
     */
    public function testLogout(array $options)
    {
        $service = $this->getService(null, $options);
        $request = new Request();
        $response = new Response();
        $token = $this->createMock(TokenInterface::class);
        $service->logout($request, $response, $token);
        $cookie = $request->attributes->get(RememberMeServicesInterface::COOKIE_ATTR_NAME);
        $this->assertInstanceOf(Cookie::class, $cookie);
        $this->assertTrue($cookie->isCleared());
        $this->assertSame($options['name'], $cookie->getName());
        $this->assertSame($options['path'], $cookie->getPath());
        $this->assertSame($options['domain'], $cookie->getDomain());
        $this->assertSame($options['secure'], $cookie->isSecure());
        $this->assertSame($options['httponly'], $cookie->isHttpOnly());
    }

    public static function provideOptionsForLogout()
    {
        return [
            [['name' => 'foo', 'path' => '/', 'domain' => null, 'secure' => false, 'httponly' => true]],
            [['name' => 'foo', 'path' => '/bar', 'domain' => 'baz.com', 'secure' => true, 'httponly' => false]],
        ];
    }

    public function testLoginFail()
    {
        $service = $this->getService(null, ['name' => 'foo', 'path' => null, 'domain' => null]);
        $request = new Request();

        $service->loginFail($request);

        $this->assertTrue($request->attributes->get(RememberMeServicesInterface::COOKIE_ATTR_NAME)->isCleared());
    }

    public function testLoginSuccessIsNotProcessedWhenTokenDoesNotContainUserInterfaceImplementation()
    {
        $service = $this->getService(null, ['name' => 'foo', 'always_remember_me' => true, 'path' => null, 'domain' => null]);
        $request = new Request();
        $response = new Response();
        $token = $this->createMock(TokenInterface::class);
        $token
            ->expects($this->once())
            ->method('getUser')
            ->willReturn('foo')
        ;

        $service
            ->expects($this->never())
            ->method('onLoginSuccess')
        ;

        $this->assertFalse($request->request->has('foo'));

        $service->loginSuccess($request, $response, $token);
    }

    public function testLoginSuccessIsNotProcessedWhenRememberMeIsNotRequested()
    {
        $service = $this->getService(null, ['name' => 'foo', 'always_remember_me' => false, 'remember_me_parameter' => 'foo', 'path' => null, 'domain' => null]);
        $request = new Request();
        $response = new Response();
        $account = $this->createMock(UserInterface::class);
        $token = $this->createMock(TokenInterface::class);
        $token
            ->expects($this->once())
            ->method('getUser')
            ->willReturn($account)
        ;

        $service
            ->expects($this->never())
            ->method('onLoginSuccess')
            ->willReturn(null)
        ;

        $this->assertFalse($request->request->has('foo'));

        $service->loginSuccess($request, $response, $token);
    }

    public function testLoginSuccessWhenRememberMeAlwaysIsTrue()
    {
        $service = $this->getService(null, ['name' => 'foo', 'always_remember_me' => true, 'path' => null, 'domain' => null]);
        $request = new Request();
        $response = new Response();
        $account = $this->createMock(UserInterface::class);
        $token = $this->createMock(TokenInterface::class);
        $token
            ->expects($this->once())
            ->method('getUser')
            ->willReturn($account)
        ;

        $service
            ->expects($this->once())
            ->method('onLoginSuccess')
            ->willReturn(null)
        ;

        $service->loginSuccess($request, $response, $token);
    }

    /**
     * @dataProvider getPositiveRememberMeParameterValues
     */
    public function testLoginSuccessWhenRememberMeParameterWithPathIsPositive($value)
    {
        $service = $this->getService(null, ['name' => 'foo', 'always_remember_me' => false, 'remember_me_parameter' => 'foo[bar]', 'path' => null, 'domain' => null]);

        $request = new Request();
        $request->request->set('foo', ['bar' => $value]);
        $response = new Response();
        $account = $this->createMock(UserInterface::class);
        $token = $this->createMock(TokenInterface::class);
        $token
            ->expects($this->once())
            ->method('getUser')
            ->willReturn($account)
        ;

        $service
            ->expects($this->once())
            ->method('onLoginSuccess')
            ->willReturn(true)
        ;

        $service->loginSuccess($request, $response, $token);
    }

    /**
     * @dataProvider getPositiveRememberMeParameterValues
     */
    public function testLoginSuccessWhenRememberMeParameterIsPositive($value)
    {
        $service = $this->getService(null, ['name' => 'foo', 'always_remember_me' => false, 'remember_me_parameter' => 'foo', 'path' => null, 'domain' => null]);

        $request = new Request();
        $request->request->set('foo', $value);
        $response = new Response();
        $account = $this->createMock(UserInterface::class);
        $token = $this->createMock(TokenInterface::class);
        $token
            ->expects($this->once())
            ->method('getUser')
            ->willReturn($account)
        ;

        $service
            ->expects($this->once())
            ->method('onLoginSuccess')
            ->willReturn(true)
        ;

        $service->loginSuccess($request, $response, $token);
    }

    public static function getPositiveRememberMeParameterValues()
    {
        return [
            ['true'],
            ['1'],
            ['on'],
            ['yes'],
            [true],
        ];
    }

    public function testEncodeCookieAndDecodeCookieAreInvertible()
    {
        $cookieParts = ['aa', 'bb', 'cc'];
        $service = $this->getService();

        $encoded = $this->callProtected($service, 'encodeCookie', [$cookieParts]);
        $this->assertIsString($encoded);

        $decoded = $this->callProtected($service, 'decodeCookie', [$encoded]);
        $this->assertSame($cookieParts, $decoded);
    }

    public function testThereShouldBeNoCookieDelimiterInCookieParts()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cookie delimiter');
        $cookieParts = ['aa', 'b'.AbstractRememberMeServices::COOKIE_DELIMITER.'b', 'cc'];
        $service = $this->getService();

        $this->callProtected($service, 'encodeCookie', [$cookieParts]);
    }

    protected function getService($userProvider = null, $options = [], $logger = null)
    {
        if (null === $userProvider) {
            $userProvider = $this->getProvider();
        }

        return $this->getMockBuilder(AbstractRememberMeServices::class)
            ->setConstructorArgs([
                [$userProvider], 'foosecret', 'fookey', $options, $logger,
            ])
            ->onlyMethods(['processAutoLoginCookie', 'onLoginSuccess'])
            ->getMock();
    }

    protected function getProvider()
    {
        $provider = $this->createMock(UserProviderInterface::class);
        $provider
            ->expects($this->any())
            ->method('supportsClass')
            ->willReturn(true)
        ;

        return $provider;
    }

    private function callProtected($object, $method, array $args)
    {
        $reflection = new \ReflectionClass(\get_class($object));
        $reflectionMethod = $reflection->getMethod($method);
        $reflectionMethod->setAccessible(true);

        return $reflectionMethod->invokeArgs($object, $args);
    }
}
