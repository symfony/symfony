<?php

namespace Symfony\Bridge\Twig\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\AppVariable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\HttpFoundation\Session\Session;

class AppVariableTest extends TestCase
{
    /**
     * @var AppVariable
     */
    protected $appVariable;

    protected function setUp()
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

        $this->assertEquals('dev', $this->appVariable->getEnvironment());
    }

    /**
     * @runInSeparateProcess
     */
    public function testGetSession()
    {
        $session = $this->getMockBuilder(Session::class)->disableOriginalConstructor()->getMock();
        $request = $this->getMockBuilder('Symfony\Component\HttpFoundation\Request')->getMock();
        $request->method('getSession')->willReturn($session);

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
        $tokenStorage = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface')->getMock();
        $this->appVariable->setTokenStorage($tokenStorage);

        $token = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock();
        $tokenStorage->method('getToken')->willReturn($token);

        $this->assertEquals($token, $this->appVariable->getToken());
    }

    public function testGetUser()
    {
        $this->setTokenStorage($user = $this->getMockBuilder('Symfony\Component\Security\Core\User\UserInterface')->getMock());

        $this->assertEquals($user, $this->appVariable->getUser());
    }

    public function testGetUserWithUsernameAsTokenUser()
    {
        $this->setTokenStorage($user = 'username');

        $this->assertNull($this->appVariable->getUser());
    }

    public function testGetTokenWithNoToken()
    {
        $tokenStorage = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface')->getMock();
        $this->appVariable->setTokenStorage($tokenStorage);

        $this->assertNull($this->appVariable->getToken());
    }

    public function testGetUserWithNoToken()
    {
        $tokenStorage = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface')->getMock();
        $this->appVariable->setTokenStorage($tokenStorage);

        $this->assertNull($this->appVariable->getUser());
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testEnvironmentNotSet()
    {
        $this->appVariable->getEnvironment();
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testDebugNotSet()
    {
        $this->appVariable->getDebug();
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testGetTokenWithTokenStorageNotSet()
    {
        $this->appVariable->getToken();
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testGetUserWithTokenStorageNotSet()
    {
        $this->appVariable->getUser();
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testGetRequestWithRequestStackNotSet()
    {
        $this->appVariable->getRequest();
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testGetSessionWithRequestStackNotSet()
    {
        $this->appVariable->getSession();
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

        $flashMessages = $this->setFlashMessages();
        $this->assertEquals([], $this->appVariable->getFlashes('this-does-not-exist'));

        $flashMessages = $this->setFlashMessages();
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

    protected function setRequestStack($request)
    {
        $requestStackMock = $this->getMockBuilder('Symfony\Component\HttpFoundation\RequestStack')->getMock();
        $requestStackMock->method('getCurrentRequest')->willReturn($request);

        $this->appVariable->setRequestStack($requestStackMock);
    }

    protected function setTokenStorage($user)
    {
        $tokenStorage = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface')->getMock();
        $this->appVariable->setTokenStorage($tokenStorage);

        $token = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock();
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

        $session = $this->getMockBuilder('Symfony\Component\HttpFoundation\Session\Session')->disableOriginalConstructor()->getMock();
        $session->method('isStarted')->willReturn($sessionHasStarted);
        $session->method('getFlashBag')->willReturn($flashBag);

        $request = $this->getMockBuilder('Symfony\Component\HttpFoundation\Request')->getMock();
        $request->method('getSession')->willReturn($session);
        $this->setRequestStack($request);

        return $flashMessages;
    }
}
