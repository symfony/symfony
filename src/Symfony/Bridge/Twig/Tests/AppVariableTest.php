<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\AppVariable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Translation\LocaleSwitcher;

class AppVariableTest extends TestCase
{
    /**
     * @var AppVariable
     */
    protected $appVariable;

    protected function setUp(): void
    {
        $this->appVariable = new AppVariable();
    }

    /**
     * @dataProvider debugDataProvider
     */
    public function testDebug($debugFlag)
    {
        $this->appVariable->setDebug($debugFlag);

        $this->assertEquals($debugFlag, $this->appVariable->getDebug());
    }

    public static function debugDataProvider()
    {
        return [
            'debug on' => [true],
            'debug off' => [false],
        ];
    }

    public function testEnvironment()
    {
        $this->appVariable->setEnvironment('dev');

        $this->assertEquals('dev', $this->appVariable->getEnvironment());
    }

    /**
     * @runInSeparateProcess
     */
    public function testGetSession()
    {
        $request = $this->createMock(Request::class);
        $request->method('hasSession')->willReturn(true);
        $request->method('getSession')->willReturn($session = new Session());

        $this->setRequestStack($request);

        $this->assertEquals($session, $this->appVariable->getSession());
    }

    public function testGetSessionWithNoRequest()
    {
        $this->setRequestStack(null);

        $this->assertNull($this->appVariable->getSession());
    }

    public function testGetRequest()
    {
        $this->setRequestStack($request = new Request());

        $this->assertEquals($request, $this->appVariable->getRequest());
    }

    public function testGetToken()
    {
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $this->appVariable->setTokenStorage($tokenStorage);

        $token = $this->createMock(TokenInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $this->assertEquals($token, $this->appVariable->getToken());
    }

    public function testGetUser()
    {
        $this->setTokenStorage($user = $this->createMock(UserInterface::class));

        $this->assertEquals($user, $this->appVariable->getUser());
    }

    public function testGetLocale()
    {
        $localeSwitcher = $this->createMock(LocaleSwitcher::class);
        $this->appVariable->setLocaleSwitcher($localeSwitcher);

        $localeSwitcher->method('getLocale')->willReturn('fr');

        self::assertEquals('fr', $this->appVariable->getLocale());
    }

    public function testGetTokenWithNoToken()
    {
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $this->appVariable->setTokenStorage($tokenStorage);

        $this->assertNull($this->appVariable->getToken());
    }

    public function testGetUserWithNoToken()
    {
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $this->appVariable->setTokenStorage($tokenStorage);

        $this->assertNull($this->appVariable->getUser());
    }

    public function testEnvironmentNotSet()
    {
        $this->expectException(\RuntimeException::class);
        $this->appVariable->getEnvironment();
    }

    public function testDebugNotSet()
    {
        $this->expectException(\RuntimeException::class);
        $this->appVariable->getDebug();
    }

    public function testGetTokenWithTokenStorageNotSet()
    {
        $this->expectException(\RuntimeException::class);
        $this->appVariable->getToken();
    }

    public function testGetUserWithTokenStorageNotSet()
    {
        $this->expectException(\RuntimeException::class);
        $this->appVariable->getUser();
    }

    public function testGetRequestWithRequestStackNotSet()
    {
        $this->expectException(\RuntimeException::class);
        $this->appVariable->getRequest();
    }

    public function testGetSessionWithRequestStackNotSet()
    {
        $this->expectException(\RuntimeException::class);
        $this->appVariable->getSession();
    }

    public function testGetLocaleWithLocaleSwitcherNotSet()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The "app.locale" variable is not available.');
        $this->appVariable->getLocale();
    }

    public function testGetFlashesWithNoRequest()
    {
        $this->setRequestStack(null);

        $this->assertEquals([], $this->appVariable->getFlashes());
    }

    /**
     * @runInSeparateProcess
     */
    public function testGetFlashesWithNoSessionStarted()
    {
        $flashMessages = $this->setFlashMessages(false);
        $this->assertEquals($flashMessages, $this->appVariable->getFlashes());
    }

    /**
     * @runInSeparateProcess
     */
    public function testGetFlashes()
    {
        $flashMessages = $this->setFlashMessages();
        $this->assertEquals($flashMessages, $this->appVariable->getFlashes(null));

        $flashMessages = $this->setFlashMessages();
        $this->assertEquals($flashMessages, $this->appVariable->getFlashes(''));

        $flashMessages = $this->setFlashMessages();
        $this->assertEquals($flashMessages, $this->appVariable->getFlashes([]));

        $this->setFlashMessages();
        $this->assertEquals([], $this->appVariable->getFlashes('this-does-not-exist'));

        $this->setFlashMessages();
        $this->assertEquals(
            ['this-does-not-exist' => []],
            $this->appVariable->getFlashes(['this-does-not-exist'])
        );

        $flashMessages = $this->setFlashMessages();
        $this->assertEquals($flashMessages['notice'], $this->appVariable->getFlashes('notice'));

        $flashMessages = $this->setFlashMessages();
        $this->assertEquals(
            ['notice' => $flashMessages['notice']],
            $this->appVariable->getFlashes(['notice'])
        );

        $flashMessages = $this->setFlashMessages();
        $this->assertEquals(
            ['notice' => $flashMessages['notice'], 'this-does-not-exist' => []],
            $this->appVariable->getFlashes(['notice', 'this-does-not-exist'])
        );

        $flashMessages = $this->setFlashMessages();
        $this->assertEquals(
            ['notice' => $flashMessages['notice'], 'error' => $flashMessages['error']],
            $this->appVariable->getFlashes(['notice', 'error'])
        );

        $this->assertEquals(
            ['warning' => $flashMessages['warning']],
            $this->appVariable->getFlashes(['warning']),
            'After getting some flash types (e.g. "notice" and "error"), the rest of flash messages must remain (e.g. "warning").'
        );

        $this->assertEquals(
            ['this-does-not-exist' => []],
            $this->appVariable->getFlashes(['this-does-not-exist'])
        );
    }

    public function testGetCurrentRoute()
    {
        $this->setRequestStack(new Request(attributes: ['_route' => 'some_route']));

        $this->assertSame('some_route', $this->appVariable->getCurrent_route());
    }

    public function testGetCurrentRouteWithRequestStackNotSet()
    {
        $this->expectException(\RuntimeException::class);
        $this->appVariable->getCurrent_route();
    }

    public function testGetCurrentRouteParameters()
    {
        $routeParams = ['some_param' => true];
        $this->setRequestStack(new Request(attributes: ['_route_params' => $routeParams]));

        $this->assertSame($routeParams, $this->appVariable->getCurrent_route_parameters());
    }

    public function testGetCurrentRouteParametersWithoutAttribute()
    {
        $this->setRequestStack(new Request());

        $this->assertSame([], $this->appVariable->getCurrent_route_parameters());
    }

    public function testGetCurrentRouteParametersWithRequestStackNotSet()
    {
        $this->expectException(\RuntimeException::class);
        $this->appVariable->getCurrent_route_parameters();
    }

    protected function setRequestStack($request)
    {
        $requestStackMock = $this->createMock(RequestStack::class);
        $requestStackMock->method('getCurrentRequest')->willReturn($request);

        $this->appVariable->setRequestStack($requestStackMock);
    }

    protected function setTokenStorage($user)
    {
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $this->appVariable->setTokenStorage($tokenStorage);

        $token = $this->createMock(TokenInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $token->method('getUser')->willReturn($user);
    }

    private function setFlashMessages($sessionHasStarted = true)
    {
        $flashMessages = [
            'notice' => ['Notice #1 message'],
            'warning' => ['Warning #1 message'],
            'error' => ['Error #1 message', 'Error #2 message'],
        ];
        $flashBag = new FlashBag();
        $flashBag->initialize($flashMessages);

        $session = $this->createMock(Session::class);
        $session->method('isStarted')->willReturn($sessionHasStarted);
        $session->method('getFlashBag')->willReturn($flashBag);

        $request = $this->createMock(Request::class);
        $request->method('hasSession')->willReturn(true);
        $request->method('getSession')->willReturn($session);
        $this->setRequestStack($request);

        return $flashMessages;
    }
}
