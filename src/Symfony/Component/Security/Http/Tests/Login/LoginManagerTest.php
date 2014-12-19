<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\Logout;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Http\Login\LoginManager;
use Symfony\Component\Security\Http\Tests\RememberMe\FakeRememberMeServicesResolver;

class LoginManagerTest extends \PHPUnit_Framework_TestCase
{
    const FIREWALL_NAME = 'firewall_name';
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $sessionAuthenticationStrategy;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $requestStack;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $securityContext;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $userChecker;

    /**
     * @var FakeRememberMeServicesResolver
     */
    private $rememberMeServicesResolver;

    public function setUp()
    {
        $this->requestStack = $this->getMockBuilder('Symfony\\Component\\HttpFoundation\\RequestStack')
            ->disableOriginalConstructor()
            ->getMock();
        $this->securityContext = $this->getMockBuilder('Symfony\\Component\\Security\\Core\\SecurityContext')
            ->disableOriginalConstructor()
            ->getMock();
        $this->userChecker = $this->getMock('Symfony\\Component\\Security\\Core\\User\\UserChecker');
        $this->sessionAuthenticationStrategy = $this->getMockBuilder('Symfony\\Component\\Security\\Http\\Session\\SessionAuthenticationStrategy')
            ->disableOriginalConstructor()
            ->getMock();
        $this->rememberMeServicesResolver = new FakeRememberMeServicesResolver();
    }

    public function testLoginWithoutRequest()
    {
        $loginManager = $this->createLoginManager();
        $user = new User('norzechowicz', 'password123', array('ROLE_USER'));

        $this->userChecker->expects($this->once())
            ->method('checkPostAuth')
            ->with($this->equalTo($user));

        $this->requestStack->expects($this->once())
            ->method('getMasterRequest')
            ->will($this->returnValue(null));

        $this->securityContext->expects($this->once())
            ->method('setToken')
            ->with($this->isInstanceOf('Symfony\Component\Security\Core\Authentication\Token\TokenInterface'));

        $loginManager->loginUser(self::FIREWALL_NAME, $user);
    }

    public function testLoginWithRequest()
    {
        $loginManager = $this->createLoginManager();
        $user = new User('norzechowicz', 'password123', array('ROLE_USER'));

        $this->userChecker->expects($this->once())
            ->method('checkPostAuth')
            ->with($this->equalTo($user));

        $this->requestStack->expects($this->once())
            ->method('getMasterRequest')
            ->will($this->returnValue(Request::create('/foo')));

        $this->sessionAuthenticationStrategy->expects($this->once())
            ->method('onAuthentication')
            ->with(
                $this->isInstanceOf('Symfony\Component\HttpFoundation\Request'),
                $this->isInstanceOf('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')
            );

        $this->securityContext->expects($this->once())
            ->method('setToken')
            ->with($this->isInstanceOf('Symfony\Component\Security\Core\Authentication\Token\TokenInterface'));

        $loginManager->loginUser(self::FIREWALL_NAME, $user);
    }

    public function testLoginWithRequestResponseAndRememberMeServices()
    {
        $loginManager = $this->createLoginManager();
        $user = new User('norzechowicz', 'password123', array('ROLE_USER'));

        $this->userChecker->expects($this->once())
            ->method('checkPostAuth')
            ->with($this->equalTo($user));

        $this->requestStack->expects($this->once())
            ->method('getMasterRequest')
            ->will($this->returnValue(Request::create('/foo')));

        $this->sessionAuthenticationStrategy->expects($this->once())
            ->method('onAuthentication')
            ->with(
                $this->isInstanceOf('Symfony\Component\HttpFoundation\Request'),
                $this->isInstanceOf('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')
            );

        $rememberMeServices = $this->getMock('Symfony\\Component\\Security\\Http\\RememberMe\\RememberMeServicesInterface');
        $rememberMeServices->expects($this->once())
            ->method('loginSuccess')
            ->with(
                $this->isInstanceOf('Symfony\Component\HttpFoundation\Request'),
                $this->isInstanceOf('Symfony\Component\HttpFoundation\Response'),
                $this->isInstanceOf('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')
            );
        $this->rememberMeServicesResolver->addRememberMeServices(self::FIREWALL_NAME, $rememberMeServices);

        $this->securityContext->expects($this->once())
            ->method('setToken')
            ->with($this->isInstanceOf('Symfony\Component\Security\Core\Authentication\Token\TokenInterface'));

        $loginManager->loginUser(self::FIREWALL_NAME, $user, Response::create());
    }

    /**
     * @expectedException \Symfony\Component\Security\Core\Exception\AuthenticationException
     */
    public function testLoginShouldFailWithoutAuthenticatedToken()
    {
        $loginManager = $this->createLoginManager();
        $user = new User('norzechowicz', 'password123');

        $loginManager->loginUser(self::FIREWALL_NAME, $user);
    }

    /**
     * @return LoginManager
     */
    private function createLoginManager()
    {
        return new LoginManager(
            $this->securityContext,
            $this->userChecker,
            $this->requestStack,
            $this->sessionAuthenticationStrategy,
            $this->rememberMeServicesResolver
        );
    }
}
