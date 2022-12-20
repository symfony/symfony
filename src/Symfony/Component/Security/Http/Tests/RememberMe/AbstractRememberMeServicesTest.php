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

        self::assertEquals('foo', $service->getRememberMeParameter());
    }

    public function testGetSecret()
    {
        $service = $this->getService();
        self::assertEquals('foosecret', $service->getSecret());
    }

    public function testAutoLoginReturnsNullWhenNoCookie()
    {
        $service = $this->getService(null, ['name' => 'foo', 'path' => null, 'domain' => null]);

        self::assertNull($service->autoLogin(new Request()));
    }

    public function testAutoLoginReturnsNullAfterLoginFail()
    {
        $service = $this->getService(null, ['name' => 'foo', 'path' => null, 'domain' => null]);

        $request = new Request();
        $request->cookies->set('foo', 'foo');

        $service->loginFail($request);
        self::assertNull($service->autoLogin($request));
    }

    public function testAutoLoginThrowsExceptionWhenImplementationDoesNotReturnUserInterface()
    {
        self::expectException(\RuntimeException::class);
        $service = $this->getService(null, ['name' => 'foo', 'path' => null, 'domain' => null]);
        $request = new Request();
        $request->cookies->set('foo', 'foo');

        $service
            ->expects(self::once())
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

        $user = self::createMock(UserInterface::class);
        $user
            ->expects(self::once())
            ->method('getRoles')
            ->willReturn([])
        ;

        $service
            ->expects(self::once())
            ->method('processAutoLoginCookie')
            ->willReturn($user)
        ;

        $returnedToken = $service->autoLogin($request);

        self::assertSame($user, $returnedToken->getUser());
        self::assertSame('foosecret', $returnedToken->getSecret());
        self::assertSame('fookey', $returnedToken->getFirewallName());
    }

    /**
     * @dataProvider provideOptionsForLogout
     */
    public function testLogout(array $options)
    {
        $service = $this->getService(null, $options);
        $request = new Request();
        $response = new Response();
        $token = self::createMock(TokenInterface::class);
        $service->logout($request, $response, $token);
        $cookie = $request->attributes->get(RememberMeServicesInterface::COOKIE_ATTR_NAME);
        self::assertInstanceOf(Cookie::class, $cookie);
        self::assertTrue($cookie->isCleared());
        self::assertSame($options['name'], $cookie->getName());
        self::assertSame($options['path'], $cookie->getPath());
        self::assertSame($options['domain'], $cookie->getDomain());
        self::assertSame($options['secure'], $cookie->isSecure());
        self::assertSame($options['httponly'], $cookie->isHttpOnly());
    }

    public function provideOptionsForLogout()
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

        self::assertTrue($request->attributes->get(RememberMeServicesInterface::COOKIE_ATTR_NAME)->isCleared());
    }

    public function testLoginSuccessIsNotProcessedWhenTokenDoesNotContainUserInterfaceImplementation()
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

        $service
            ->expects(self::never())
            ->method('onLoginSuccess')
        ;

        self::assertFalse($request->request->has('foo'));

        $service->loginSuccess($request, $response, $token);
    }

    public function testLoginSuccessIsNotProcessedWhenRememberMeIsNotRequested()
    {
        $service = $this->getService(null, ['name' => 'foo', 'always_remember_me' => false, 'remember_me_parameter' => 'foo', 'path' => null, 'domain' => null]);
        $request = new Request();
        $response = new Response();
        $account = self::createMock(UserInterface::class);
        $token = self::createMock(TokenInterface::class);
        $token
            ->expects(self::once())
            ->method('getUser')
            ->willReturn($account)
        ;

        $service
            ->expects(self::never())
            ->method('onLoginSuccess')
            ->willReturn(null)
        ;

        self::assertFalse($request->request->has('foo'));

        $service->loginSuccess($request, $response, $token);
    }

    public function testLoginSuccessWhenRememberMeAlwaysIsTrue()
    {
        $service = $this->getService(null, ['name' => 'foo', 'always_remember_me' => true, 'path' => null, 'domain' => null]);
        $request = new Request();
        $response = new Response();
        $account = self::createMock(UserInterface::class);
        $token = self::createMock(TokenInterface::class);
        $token
            ->expects(self::once())
            ->method('getUser')
            ->willReturn($account)
        ;

        $service
            ->expects(self::once())
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
        $account = self::createMock(UserInterface::class);
        $token = self::createMock(TokenInterface::class);
        $token
            ->expects(self::once())
            ->method('getUser')
            ->willReturn($account)
        ;

        $service
            ->expects(self::once())
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
        $account = self::createMock(UserInterface::class);
        $token = self::createMock(TokenInterface::class);
        $token
            ->expects(self::once())
            ->method('getUser')
            ->willReturn($account)
        ;

        $service
            ->expects(self::once())
            ->method('onLoginSuccess')
            ->willReturn(true)
        ;

        $service->loginSuccess($request, $response, $token);
    }

    public function getPositiveRememberMeParameterValues()
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
        self::assertIsString($encoded);

        $decoded = $this->callProtected($service, 'decodeCookie', [$encoded]);
        self::assertSame($cookieParts, $decoded);
    }

    public function testThereShouldBeNoCookieDelimiterInCookieParts()
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('cookie delimiter');
        $cookieParts = ['aa', 'b'.AbstractRememberMeServices::COOKIE_DELIMITER.'b', 'cc'];
        $service = $this->getService();

        $this->callProtected($service, 'encodeCookie', [$cookieParts]);
    }

    protected function getService($userProvider = null, $options = [], $logger = null)
    {
        if (null === $userProvider) {
            $userProvider = $this->getProvider();
        }

        return self::getMockForAbstractClass(AbstractRememberMeServices::class, [
            [$userProvider], 'foosecret', 'fookey', $options, $logger,
        ]);
    }

    protected function getProvider()
    {
        $provider = self::createMock(UserProviderInterface::class);
        $provider
            ->expects(self::any())
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
