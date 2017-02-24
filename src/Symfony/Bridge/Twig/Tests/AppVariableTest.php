<?php

namespace Symfony\Bridge\Twig\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\AppVariable;
use Symfony\Component\HttpFoundation\Request;
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
        return array(
            'debug on' => array(true),
            'debug off' => array(false),
        );
    }

    public function testEnvironment()
    {
        $this->appVariable->setEnvironment('dev');

        $this->assertEquals('dev', $this->appVariable->getEnvironment());
    }

    public function testGetSession()
    {
        $request = $this->getMockBuilder('Symfony\Component\HttpFoundation\Request')->getMock();
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
}
