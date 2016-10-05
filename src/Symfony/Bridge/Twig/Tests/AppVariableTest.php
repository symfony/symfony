<?php

namespace Symfony\Bridge\Twig\Tests;

use Symfony\Bridge\Twig\AppVariable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;

class AppVariableTest extends \PHPUnit_Framework_TestCase
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
        $request = $this->getMock('Symfony\Component\HttpFoundation\Request');
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
        $tokenStorage = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface');
        $this->appVariable->setTokenStorage($tokenStorage);

        $token = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');
        $tokenStorage->method('getToken')->willReturn($token);

        $this->assertEquals($token, $this->appVariable->getToken());
    }

    public function testGetUser()
    {
        $this->setTokenStorage($user = $this->getMock('Symfony\Component\Security\Core\User\UserInterface'));

        $this->assertEquals($user, $this->appVariable->getUser());
    }

    public function testGetUserWithUsernameAsTokenUser()
    {
        $this->setTokenStorage($user = 'username');

        $this->assertNull($this->appVariable->getUser());
    }

    public function testGetTokenWithNoToken()
    {
        $tokenStorage = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface');
        $this->appVariable->setTokenStorage($tokenStorage);

        $this->assertNull($this->appVariable->getToken());
    }

    public function testGetUserWithNoToken()
    {
        $tokenStorage = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface');
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
        $requestStackMock = $this->getMock('Symfony\Component\HttpFoundation\RequestStack');
        $requestStackMock->method('getCurrentRequest')->willReturn($request);

        $this->appVariable->setRequestStack($requestStackMock);
    }

    protected function setTokenStorage($user)
    {
        $tokenStorage = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface');
        $this->appVariable->setTokenStorage($tokenStorage);

        $token = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');
        $tokenStorage->method('getToken')->willReturn($token);

        $token->method('getUser')->willReturn($user);
    }
}
