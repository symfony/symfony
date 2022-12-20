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

        self::assertEquals($debugFlag, $this->appVariable->getDebug());
    }

    public function debugDataProvider()
    {
        return [
            'debug on' => [true],
            'debug off' => [false],
        ];
    }

    public function testEnvironment()
    {
        $this->appVariable->setEnvironment('dev');

        self::assertEquals('dev', $this->appVariable->getEnvironment());
    }

    /**
     * @runInSeparateProcess
     */
    public function testGetSession()
    {
        $request = self::createMock(Request::class);
        $request->method('hasSession')->willReturn(true);
        $request->method('getSession')->willReturn($session = new Session());

        $this->setRequestStack($request);

        self::assertEquals($session, $this->appVariable->getSession());
    }

    public function testGetSessionWithNoRequest()
    {
        $this->setRequestStack(null);

        self::assertNull($this->appVariable->getSession());
    }

    public function testGetRequest()
    {
        $this->setRequestStack($request = new Request());

        self::assertEquals($request, $this->appVariable->getRequest());
    }

    public function testGetToken()
    {
        $tokenStorage = self::createMock(TokenStorageInterface::class);
        $this->appVariable->setTokenStorage($tokenStorage);

        $token = self::createMock(TokenInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        self::assertEquals($token, $this->appVariable->getToken());
    }

    public function testGetUser()
    {
        $this->setTokenStorage($user = self::createMock(UserInterface::class));

        self::assertEquals($user, $this->appVariable->getUser());
    }

    /**
     * @group legacy
     */
    public function testGetUserWithUsernameAsTokenUser()
    {
        $this->setTokenStorage('username');

        self::assertNull($this->appVariable->getUser());
    }

    public function testGetTokenWithNoToken()
    {
        $tokenStorage = self::createMock(TokenStorageInterface::class);
        $this->appVariable->setTokenStorage($tokenStorage);

        self::assertNull($this->appVariable->getToken());
    }

    public function testGetUserWithNoToken()
    {
        $tokenStorage = self::createMock(TokenStorageInterface::class);
        $this->appVariable->setTokenStorage($tokenStorage);

        self::assertNull($this->appVariable->getUser());
    }

    public function testEnvironmentNotSet()
    {
        self::expectException(\RuntimeException::class);
        $this->appVariable->getEnvironment();
    }

    public function testDebugNotSet()
    {
        self::expectException(\RuntimeException::class);
        $this->appVariable->getDebug();
    }

    public function testGetTokenWithTokenStorageNotSet()
    {
        self::expectException(\RuntimeException::class);
        $this->appVariable->getToken();
    }

    public function testGetUserWithTokenStorageNotSet()
    {
        self::expectException(\RuntimeException::class);
        $this->appVariable->getUser();
    }

    public function testGetRequestWithRequestStackNotSet()
    {
        self::expectException(\RuntimeException::class);
        $this->appVariable->getRequest();
    }

    public function testGetSessionWithRequestStackNotSet()
    {
        self::expectException(\RuntimeException::class);
        $this->appVariable->getSession();
    }

    public function testGetFlashesWithNoRequest()
    {
        $this->setRequestStack(null);

        self::assertEquals([], $this->appVariable->getFlashes());
    }

    /**
     * @runInSeparateProcess
     */
    public function testGetFlashesWithNoSessionStarted()
    {
        $flashMessages = $this->setFlashMessages(false);
        self::assertEquals($flashMessages, $this->appVariable->getFlashes());
    }

    /**
     * @runInSeparateProcess
     */
    public function testGetFlashes()
    {
        $flashMessages = $this->setFlashMessages();
        self::assertEquals($flashMessages, $this->appVariable->getFlashes(null));

        $flashMessages = $this->setFlashMessages();
        self::assertEquals($flashMessages, $this->appVariable->getFlashes(''));

        $flashMessages = $this->setFlashMessages();
        self::assertEquals($flashMessages, $this->appVariable->getFlashes([]));

        $this->setFlashMessages();
        self::assertEquals([], $this->appVariable->getFlashes('this-does-not-exist'));

        $this->setFlashMessages();
        self::assertEquals(['this-does-not-exist' => []], $this->appVariable->getFlashes(['this-does-not-exist']));

        $flashMessages = $this->setFlashMessages();
        self::assertEquals($flashMessages['notice'], $this->appVariable->getFlashes('notice'));

        $flashMessages = $this->setFlashMessages();
        self::assertEquals(['notice' => $flashMessages['notice']], $this->appVariable->getFlashes(['notice']));

        $flashMessages = $this->setFlashMessages();
        self::assertEquals(['notice' => $flashMessages['notice'], 'this-does-not-exist' => []], $this->appVariable->getFlashes(['notice', 'this-does-not-exist']));

        $flashMessages = $this->setFlashMessages();
        self::assertEquals(['notice' => $flashMessages['notice'], 'error' => $flashMessages['error']], $this->appVariable->getFlashes(['notice', 'error']));

        self::assertEquals(['warning' => $flashMessages['warning']], $this->appVariable->getFlashes(['warning']), 'After getting some flash types (e.g. "notice" and "error"), the rest of flash messages must remain (e.g. "warning").');

        self::assertEquals(['this-does-not-exist' => []], $this->appVariable->getFlashes(['this-does-not-exist']));
    }

    protected function setRequestStack($request)
    {
        $requestStackMock = self::createMock(RequestStack::class);
        $requestStackMock->method('getCurrentRequest')->willReturn($request);

        $this->appVariable->setRequestStack($requestStackMock);
    }

    protected function setTokenStorage($user)
    {
        $tokenStorage = self::createMock(TokenStorageInterface::class);
        $this->appVariable->setTokenStorage($tokenStorage);

        $token = self::createMock(TokenInterface::class);
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

        $session = self::createMock(Session::class);
        $session->method('isStarted')->willReturn($sessionHasStarted);
        $session->method('getFlashBag')->willReturn($flashBag);

        $request = self::createMock(Request::class);
        $request->method('hasSession')->willReturn(true);
        $request->method('getSession')->willReturn($session);
        $this->setRequestStack($request);

        return $flashMessages;
    }
}
