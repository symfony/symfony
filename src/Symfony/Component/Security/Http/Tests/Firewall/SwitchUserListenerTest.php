<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\Firewall;

use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Http\Firewall\SwitchUserListener;

class SwitchUserListenerTest extends \PHPUnit_Framework_TestCase
{
    private $tokenStorage;
    private $userProvider;
    private $userChecker;
    private $accessDecisionManager;

    protected function setUp()
    {
        $this->tokenStorage = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface');
        $this->userProvider = $this->getMock('Symfony\Component\Security\Core\User\UserProviderInterface');
        $this->userChecker = $this->getMock('Symfony\Component\Security\Core\User\UserCheckerInterface');
        $this->accessDecisionManager = $this->getMock('Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface');
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage $providerKey must not be empty
     */
    public function test__ConstructThrowsExceptionOnInvalidProviderKey()
    {
        new SwitchUserListener($this->tokenStorage, $this->userProvider, $this->userChecker, '', $this->accessDecisionManager);
    }

    public function testHandleOnSwitchThrowsExceptionIfUserCanNotSwitch()
    {
        $this->markTestIncomplete("not sure if this must be tested");
    }

    public function testHandleOnExitThrowsExceptionIfUserCanNotExit()
    {
        $this->markTestIncomplete("not sure if this must be tested");
    }

    public function testHandleOnSwitchThrowsExceptionIfUserTriesToSwitchToAnUnexistentUsername()
    {
        $this->markTestIncomplete("not sure if this must be tested");
    }

    public function testHandleWithNullUsernameParameter()
    {
        $request = $this->getMock('Symfony\Component\HttpFoundation\Request');
        $request->expects($this->any())
            ->method('get')
            ->with('_switch_user')
            ->will($this->returnValue(null));

        $event = $this->getMockBuilder('Symfony\Component\HttpKernel\Event\GetResponseEvent')
            ->disableOriginalConstructor()
            ->getMock();

        $event->expects($this->any())
            ->method('getRequest')
            ->will($this->returnValue($request));

        $listener = new SwitchUserListener($this->tokenStorage, $this->userProvider, $this->userChecker, 'foo_provider', $this->accessDecisionManager);
        $result = $listener->handle($event);

        $this->assertNull($result);
    }

    public function testHandleOnSwitch()
    {
        $token = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');
        $token->expects($this->any())
            ->method('getRoles')
            ->will($this->returnValue(array()));

        $tokenStorage = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface');
        $tokenStorage->expects($this->atLeastOnce())
            ->method('getToken')
            ->will($this->returnValue($token));
        $tokenStorage->expects($this->once())
            ->method('setToken')
            ->with($this->isInstanceOf('Symfony\Component\Security\Core\Authentication\Token\TokenInterface'));

        $accessDecisionManager = $this->getMock('Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface');
        $accessDecisionManager->expects($this->atLeastOnce())
            ->method('decide')
            ->will($this->returnValue(true));

        $user = $this->getMock('Symfony\Component\Security\Core\User\UserInterface');

        $userProvider = $this->getMock('Symfony\Component\Security\Core\User\UserProviderInterface');
        $userProvider->expects($this->once())
            ->method('loadUserByUsername')
            ->will($this->returnValue($user));

        $userChecker = $this->getMock('Symfony\Component\Security\Core\User\UserCheckerInterface');
        $userChecker->expects($this->once())
            ->method('checkPostAuth')->with($user);

        $request = $this->getMock('Symfony\Component\HttpFoundation\Request');
        $request->expects($this->any())
            ->method('get')
            ->with('_switch_user')
            ->will($this->returnValue('foo'));

        $request->expects($this->any())
            ->method('getUri')
            ->will($this->returnValue('/'));

        $request->query = $this->getMock('Symfony\Component\HttpFoundation\ParameterBag');
        $request->query->expects($this->once())
            ->method('remove');
        $request->query->expects($this->any())
            ->method('all')
            ->will($this->returnValue(array()));

        $request->server = $this->getMock('Symfony\Component\HttpFoundation\ServerBag');
        $request->server->expects($this->once())
            ->method('set')
            ->with('QUERY_STRING', '');

        $event = $this->getMockBuilder('Symfony\Component\HttpKernel\Event\GetResponseEvent')
            ->disableOriginalConstructor()
            ->getMock();

        $event->expects($this->any())
            ->method('getRequest')
            ->will($this->returnValue($request));

        $listener = new SwitchUserListener($tokenStorage, $userProvider, $userChecker, 'foo_provider', $accessDecisionManager);
        $listener->handle($event);
    }

    public function testHandleOnSwitchKeepsOtherQueryStringParameters()
    {
        $token = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');
        $token->expects($this->any())
            ->method('getRoles')
            ->will($this->returnValue(array()));

        $tokenStorage = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface');
        $tokenStorage->expects($this->atLeastOnce())
            ->method('getToken')
            ->will($this->returnValue($token));
        $tokenStorage->expects($this->once())
            ->method('setToken')
            ->with($this->isInstanceOf('Symfony\Component\Security\Core\Authentication\Token\TokenInterface'));

        $accessDecisionManager = $this->getMock('Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface');
        $accessDecisionManager->expects($this->atLeastOnce())
            ->method('decide')
            ->will($this->returnValue(true));

        $user = $this->getMock('Symfony\Component\Security\Core\User\UserInterface');

        $userProvider = $this->getMock('Symfony\Component\Security\Core\User\UserProviderInterface');
        $userProvider->expects($this->once())
            ->method('loadUserByUsername')
            ->will($this->returnValue($user));

        $userChecker = $this->getMock('Symfony\Component\Security\Core\User\UserCheckerInterface');
        $userChecker->expects($this->once())
            ->method('checkPostAuth')->with($user);

        $request = $this->getMock('Symfony\Component\HttpFoundation\Request');
        $request->expects($this->any())
            ->method('get')
            ->with('_switch_user')
            ->will($this->returnValue('foo'));

        $request->expects($this->any())
            ->method('getUri')
            ->will($this->returnValue('/'));

        $request->query = $this->getMock('Symfony\Component\HttpFoundation\ParameterBag');
        $request->query->expects($this->once())
            ->method('remove');
        $request->query->expects($this->any())
            ->method('all')
            ->will($this->returnValue(array('page' => 3, 'section' => 2)));

        $request->server = $this->getMock('Symfony\Component\HttpFoundation\ServerBag');
        $request->server->expects($this->once())
            ->method('set')
            ->with('QUERY_STRING', 'page=3&section=2');

        $event = $this->getMockBuilder('Symfony\Component\HttpKernel\Event\GetResponseEvent')
            ->disableOriginalConstructor()
            ->getMock();

        $event->expects($this->any())
            ->method('getRequest')
            ->will($this->returnValue($request));

        $listener = new SwitchUserListener($tokenStorage, $userProvider, $userChecker, 'foo_provider', $accessDecisionManager);
        $listener->handle($event);
    }

    /**
     * Not very heavy as test, should check "$sourceToken = $originalToken;"
     */
    public function testHandleOnSwitchFromAnAlreadySwitchedUser()
    {
        $originalToken = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');

        $role = $this->getMockBuilder('Symfony\Component\Security\Core\Role\SwitchUserRole')
            ->disableOriginalConstructor()
            ->getMock();
        $role->expects($this->any())
            ->method('getSource')
            ->will($this->returnValue($originalToken));

        $token = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');
        $token->expects($this->any())
            ->method('getRoles')
            ->will($this->returnValue(array($role)));

        $tokenStorage = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface');
        $tokenStorage->expects($this->atLeastOnce())
            ->method('getToken')
            ->will($this->returnValue($token));
        $tokenStorage->expects($this->once())
            ->method('setToken')
            ->with($this->isInstanceOf('Symfony\Component\Security\Core\Authentication\Token\TokenInterface'));

        $accessDecisionManager = $this->getMock('Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface');
        $accessDecisionManager->expects($this->atLeastOnce())
            ->method('decide')
            ->will($this->returnValue(true));

        $user = $this->getMock('Symfony\Component\Security\Core\User\UserInterface');

        $userProvider = $this->getMock('Symfony\Component\Security\Core\User\UserProviderInterface');
        $userProvider->expects($this->once())
            ->method('loadUserByUsername')
            ->will($this->returnValue($user));

        $userChecker = $this->getMock('Symfony\Component\Security\Core\User\UserCheckerInterface');
        $userChecker->expects($this->once())
            ->method('checkPostAuth')->with($user);

        $request = $this->getMock('Symfony\Component\HttpFoundation\Request');
        $request->expects($this->any())
            ->method('get')
            ->with('_switch_user')
            ->will($this->returnValue('foo'));

        $request->expects($this->any())
            ->method('getUri')
            ->will($this->returnValue('/'));

        $request->query = $this->getMock('Symfony\Component\HttpFoundation\ParameterBag');
        $request->query->expects($this->once())
            ->method('remove');
        $request->query->expects($this->any())
            ->method('all')
            ->will($this->returnValue(array()));

        $request->server = $this->getMock('Symfony\Component\HttpFoundation\ServerBag');
        $request->server->expects($this->once())
            ->method('set')
            ->with('QUERY_STRING', '');

        $event = $this->getMockBuilder('Symfony\Component\HttpKernel\Event\GetResponseEvent')
            ->disableOriginalConstructor()
            ->getMock();

        $event->expects($this->any())
            ->method('getRequest')
            ->will($this->returnValue($request));

        $listener = new SwitchUserListener($tokenStorage, $userProvider, $userChecker, 'foo_provider', $accessDecisionManager);
        $listener->handle($event);
    }

    public function testHandleOnExit()
    {
        $originalToken = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');

        $role = $this->getMockBuilder('Symfony\Component\Security\Core\Role\SwitchUserRole')
            ->disableOriginalConstructor()
            ->getMock();
        $role->expects($this->any())
            ->method('getSource')
            ->will($this->returnValue($originalToken));

        $token = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');
        $token->expects($this->any())
            ->method('getRoles')
            ->will($this->returnValue(array($role)));

        $tokenStorage = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface');
        $tokenStorage->expects($this->atLeastOnce())
            ->method('getToken')
            ->will($this->returnValue($token));
        $tokenStorage->expects($this->once())
            ->method('setToken')
            ->with($this->isInstanceOf('Symfony\Component\Security\Core\Authentication\Token\TokenInterface'));

        $accessDecisionManager = $this->getMock('Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface');
        $accessDecisionManager->expects($this->atLeastOnce())
            ->method('decide')
            ->will($this->returnValue(true));

        $request = $this->getMock('Symfony\Component\HttpFoundation\Request');
        $request->expects($this->any())
            ->method('get')
            ->with('_switch_user')
            ->will($this->returnValue('_exit'));

        $request->expects($this->any())
            ->method('getUri')
            ->will($this->returnValue('/'));

        $request->query = $this->getMock('Symfony\Component\HttpFoundation\ParameterBag');
        $request->query->expects($this->once())
            ->method('remove');
        $request->query->expects($this->any())
            ->method('all')
            ->will($this->returnValue(array()));

        $request->server = $this->getMock('Symfony\Component\HttpFoundation\ServerBag');
        $request->server->expects($this->once())
            ->method('set')
            ->with('QUERY_STRING', '');

        $event = $this->getMockBuilder('Symfony\Component\HttpKernel\Event\GetResponseEvent')
            ->disableOriginalConstructor()
            ->getMock();

        $event->expects($this->any())
            ->method('getRequest')
            ->will($this->returnValue($request));

        $listener = new SwitchUserListener($tokenStorage, $this->userProvider, $this->userChecker, 'foo_provider', $accessDecisionManager);
        $listener->handle($event);
    }

    /**
     * @expectedException \Symfony\Component\Security\Core\Exception\AccessDeniedException
     */
    public function testSwitchUserThrowsExceptionIfAccessDecisionManagerDenies()
    {
        $token = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');

        $tokenStorage = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface');
        $tokenStorage->expects($this->atLeastOnce())
            ->method('getToken')
            ->will($this->returnValue($token));

        $accessDecisionManager = $this->getMock('Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface');
        $accessDecisionManager->expects($this->exactly(2))
            ->method('decide')
            ->will($this->returnValue(false));

        $request = $this->getMock('Symfony\Component\HttpFoundation\Request');

        $reflectedClass = new \ReflectionClass('Symfony\Component\Security\Http\Firewall\SwitchUserListener');
        $method = $reflectedClass->getMethod('switchUser');
        $method->setAccessible(true);

        $object = $reflectedClass->newInstanceArgs(array(
            $tokenStorage,
            $this->userProvider,
            $this->userChecker,
            'foo_provider',
            $accessDecisionManager
        ));

        $method->invokeArgs($object, array($request));
    }

    /**
     * @expectedException \Symfony\Component\Security\Core\Exception\UsernameNotFoundException
     */
    public function testSwitchUserThrowsExceptionIfUserTriesToSwitchToAnUnexistentUsername()
    {
        $token = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');

        $tokenStorage = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface');
        $tokenStorage->expects($this->atLeastOnce())
            ->method('getToken')
            ->will($this->returnValue($token));

        $accessDecisionManager = $this->getMock('Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface');
        $accessDecisionManager->expects($this->atLeastOnce())
            ->method('decide')
            ->will($this->returnValue(true));

        $userProvider = $this->getMock('Symfony\Component\Security\Core\User\UserProviderInterface');
        $userProvider->expects($this->once())
            ->method('loadUserByUsername')
            ->will($this->throwException(new UsernameNotFoundException()));

        $request = $this->getMock('Symfony\Component\HttpFoundation\Request');
        $request->expects($this->any())
            ->method('get')
            ->with('_switch_user')
            ->will($this->returnValue('username'));

        $reflectedClass = new \ReflectionClass('Symfony\Component\Security\Http\Firewall\SwitchUserListener');
        $method = $reflectedClass->getMethod('switchUser');
        $method->setAccessible(true);

        $object = $reflectedClass->newInstanceArgs(array(
            $tokenStorage,
            $userProvider,
            $this->userChecker,
            'foo_provider',
            $accessDecisionManager
        ));

        $method->invokeArgs($object, array($request));
    }

    public function testSwitchUserAddsRoleOnSwitch()
    {
        $this->markTestIncomplete("quite difficult to test, but needed");
    }

    public function testSwitchUser()
    {
        $token = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');
        $token->expects($this->any())
            ->method('getRoles')
            ->will($this->returnValue(array()));

        $tokenStorage = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface');
        $tokenStorage->expects($this->atLeastOnce())
            ->method('getToken')
            ->will($this->returnValue($token));

        $accessDecisionManager = $this->getMock('Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface');
        $accessDecisionManager->expects($this->atLeastOnce())
            ->method('decide')
            ->will($this->returnValue(true));

        $user = $this->getMock('Symfony\Component\Security\Core\User\UserInterface');

        $userProvider = $this->getMock('Symfony\Component\Security\Core\User\UserProviderInterface');
        $userProvider->expects($this->once())
            ->method('loadUserByUsername')
            ->will($this->returnValue($user));

        $logger = $this->getMock('Psr\Log\LoggerInterface');
        $logger->expects($this->atLeastOnce())
            ->method('info');

        $dispatcher = $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');
        $dispatcher->expects($this->atLeastOnce())
            ->method('dispatch');

        $request = $this->getMock('Symfony\Component\HttpFoundation\Request');
        $request->expects($this->any())
            ->method('get')
            ->with('_switch_user')
            ->will($this->returnValue('foo'));

        $reflectedClass = new \ReflectionClass('Symfony\Component\Security\Http\Firewall\SwitchUserListener');
        $method = $reflectedClass->getMethod('switchUser');
        $method->setAccessible(true);

        $object = $reflectedClass->newInstanceArgs(array(
            $tokenStorage,
            $userProvider,
            $this->userChecker,
            'foo_provider',
            $accessDecisionManager,
            $logger,
            '_switch_user',
            'ROLE_ALLOWED_TO_SWITCH',
            $dispatcher
        ));

        $result = $method->invokeArgs($object, array($request));

        $this->assertInstanceOf("Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken", $result, "A Usernamepassword token was expected..");
    }

    public function testSwitchUserOnUserIsSwitchingToHimself()
    {
        $token = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');
        $token->expects($this->any())
            ->method('getRoles')
            ->will($this->returnValue(array()));
        $token->expects($this->any())
            ->method('getUsername')
            ->will($this->returnValue('foo'));

        $tokenStorage = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface');
        $tokenStorage->expects($this->atLeastOnce())
            ->method('getToken')
            ->will($this->returnValue($token));

        $accessDecisionManager = $this->getMock('Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface');
        $accessDecisionManager->expects($this->atLeastOnce())
            ->method('decide')
            ->will($this->returnValue(true));

        $request = $this->getMock('Symfony\Component\HttpFoundation\Request');
        $request->expects($this->any())
            ->method('get')
            ->with('_switch_user')
            ->will($this->returnValue('foo'));

        $reflectedClass = new \ReflectionClass('Symfony\Component\Security\Http\Firewall\SwitchUserListener');
        $method = $reflectedClass->getMethod('switchUser');
        $method->setAccessible(true);

        $object = $reflectedClass->newInstanceArgs(array(
            $tokenStorage,
            $this->userProvider,
            $this->userChecker,
            'foo_provider',
            $accessDecisionManager
        ));

        $result = $method->invokeArgs($object, array($request));
        $this->assertTrue($result === $token, "The token of the currently authenticated user was expected.");
    }

    public function testSwitchUserOnUserIsSwitchingFromAnAlreadySwitchedUser()
    {
        $originalToken = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');

        $role = $this->getMockBuilder('Symfony\Component\Security\Core\Role\SwitchUserRole')
            ->disableOriginalConstructor()
            ->getMock();
        $role->expects($this->any())
            ->method('getSource')
            ->will($this->returnValue($originalToken));

        $token = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');
        $token->expects($this->any())
            ->method('getRoles')
            ->will($this->returnValue(array($role)));

        $tokenStorage = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface');
        $tokenStorage->expects($this->atLeastOnce())
            ->method('getToken')
            ->will($this->returnValue($token));

        $accessDecisionManager = $this->getMock('Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface');
        $accessDecisionManager->expects($this->atLeastOnce())
            ->method('decide')
            ->will($this->returnValue(true));

        $user = $this->getMock('Symfony\Component\Security\Core\User\UserInterface');

        $userProvider = $this->getMock('Symfony\Component\Security\Core\User\UserProviderInterface');
        $userProvider->expects($this->once())
            ->method('loadUserByUsername')
            ->will($this->returnValue($user));

        $logger = $this->getMock('Psr\Log\LoggerInterface');
        $logger->expects($this->atLeastOnce())
            ->method('info');

        $dispatcher = $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');
        $dispatcher->expects($this->atLeastOnce())
            ->method('dispatch');

        $request = $this->getMock('Symfony\Component\HttpFoundation\Request');
        $request->expects($this->any())
            ->method('get')
            ->with('_switch_user')
            ->will($this->returnValue('foo'));

        $reflectedClass = new \ReflectionClass('Symfony\Component\Security\Http\Firewall\SwitchUserListener');
        $method = $reflectedClass->getMethod('switchUser');
        $method->setAccessible(true);

        $object = $reflectedClass->newInstanceArgs(array(
            $tokenStorage,
            $userProvider,
            $this->userChecker,
            'foo_provider',
            $accessDecisionManager,
            $logger,
            '_switch_user',
            'ROLE_ALLOWED_TO_SWITCH',
            $dispatcher
        ));

        $result = $method->invokeArgs($object, array($request));
        $this->assertInstanceOf("Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken", $result, "A Usernamepassword token was expected..");
    }

    /**
     * @expectedException \Symfony\Component\Security\Core\Exception\AccessDeniedException
     */
    public function testExitSwitchUserThrowsExceptionIfAccessDecisionManagerDenies()
    {
        $token = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');

        $tokenStorage = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface');
        $tokenStorage->expects($this->atLeastOnce())
            ->method('getToken')
            ->will($this->returnValue($token));

        $accessDecisionManager = $this->getMock('Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface');
        $accessDecisionManager->expects($this->once())
            ->method('decide')
            ->with($token, array(SwitchUserListener::ROLE_PREVOIUS_ADMIN))
            ->will($this->returnValue(false));

        $request = $this->getMock('Symfony\Component\HttpFoundation\Request');

        $reflectedClass = new \ReflectionClass('Symfony\Component\Security\Http\Firewall\SwitchUserListener');
        $method = $reflectedClass->getMethod('exitSwitchUser');
        $method->setAccessible(true);

        $object = $reflectedClass->newInstanceArgs(array(
            $tokenStorage,
            $this->userProvider,
            $this->userChecker,
            'foo_provider',
            $accessDecisionManager
        ));

        $method->invokeArgs($object, array($request));
    }

    /**
     * @expectedException Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException
     */
    public function testExitSwitchUserThrowsExceptionIfOriginalTokenIsNotFound()
    {
        $token = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');
        $token->expects($this->atLeastOnce())
            ->method('getRoles')
            ->will($this->returnValue(array()));

        $tokenStorage = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface');
        $tokenStorage->expects($this->atLeastOnce())
            ->method('getToken')
            ->will($this->returnValue($token));

        $accessDecisionManager = $this->getMock('Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface');
        $accessDecisionManager->expects($this->atLeastOnce())
            ->method('decide')
            ->with($token, array(SwitchUserListener::ROLE_PREVOIUS_ADMIN))
            ->will($this->returnValue(true));

        $request = $this->getMock('Symfony\Component\HttpFoundation\Request');

        $reflectedClass = new \ReflectionClass('Symfony\Component\Security\Http\Firewall\SwitchUserListener');
        $method = $reflectedClass->getMethod('exitSwitchUser');
        $method->setAccessible(true);

        $object = $reflectedClass->newInstanceArgs(array(
            $tokenStorage,
            $this->userProvider,
            $this->userChecker,
            'foo_provider',
            $accessDecisionManager
        ));

        $method->invokeArgs($object, array($request));
    }

    public function testExitSwitchUser()
    {
        $user = $this->getMock('Symfony\Component\Security\Core\User\UserInterface');

        $originalToken = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');
        $originalToken->expects($this->any())
            ->method('getUser')
            ->will($this->returnValue($user));

        $role = $this->getMockBuilder('Symfony\Component\Security\Core\Role\SwitchUserRole')
            ->disableOriginalConstructor()
            ->getMock();
        $role->expects($this->any())
            ->method('getSource')
            ->will($this->returnValue($originalToken));

        $token = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');
        $token->expects($this->any())
            ->method('getRoles')
            ->will($this->returnValue(array($role)));

        $tokenStorage = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface');
        $tokenStorage->expects($this->atLeastOnce())
            ->method('getToken')
            ->will($this->returnValue($token));

        $accessDecisionManager = $this->getMock('Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface');
        $accessDecisionManager->expects($this->once())
            ->method('decide')
            ->with($token, array(SwitchUserListener::ROLE_PREVOIUS_ADMIN))
            ->will($this->returnValue(true));

        $logger = $this->getMock('Psr\Log\LoggerInterface');

        $dispatcher = $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');
        $dispatcher->expects($this->atLeastOnce())
            ->method('dispatch');

        $request = $this->getMock('Symfony\Component\HttpFoundation\Request');

        $reflectedClass = new \ReflectionClass('Symfony\Component\Security\Http\Firewall\SwitchUserListener');
        $method = $reflectedClass->getMethod('exitSwitchUser');
        $method->setAccessible(true);

        $object = $reflectedClass->newInstanceArgs(array(
            $tokenStorage,
            $this->userProvider,
            $this->userChecker,
            'foo_provider',
            $accessDecisionManager,
            $logger,
            '_switch_user',
            'ROLE_ALLOWED_TO_SWITCH',
            $dispatcher
        ));

        $result = $method->invokeArgs($object, array($request));
        $this->assertTrue($result === $originalToken, "Original token was expected.");
    }

    public function testGetOriginalTokenOnTokenHasRolesButNoSwitchUserRole()
    {
        $role = $this->getMock('Symfony\Component\Security\Core\Role\RoleInterface');

        $token = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');
        $token->expects($this->any())
            ->method('getRoles')
            ->will($this->returnValue(array($role)));

        $reflectedClass = new \ReflectionClass('Symfony\Component\Security\Http\Firewall\SwitchUserListener');
        $method = $reflectedClass->getMethod('getOriginalToken');
        $method->setAccessible(true);

        $object = $reflectedClass->newInstanceArgs(array(
            $this->tokenStorage,
            $this->userProvider,
            $this->userChecker,
            'foo_provider',
            $this->accessDecisionManager
        ));

        $result = $method->invokeArgs($object, array($token));
        $this->assertNull($result, $result, "Original token should be null.");
    }

    public function testGetOriginalTokenOnTokenHasNoRoles()
    {
        $token = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');
        $token->expects($this->any())
            ->method('getRoles')
            ->will($this->returnValue(array()));

        $reflectedClass = new \ReflectionClass('Symfony\Component\Security\Http\Firewall\SwitchUserListener');
        $method = $reflectedClass->getMethod('getOriginalToken');
        $method->setAccessible(true);

        $object = $reflectedClass->newInstanceArgs(array(
            $this->tokenStorage,
            $this->userProvider,
            $this->userChecker,
            'foo_provider',
            $this->accessDecisionManager
        ));

        $result = $method->invokeArgs($object, array($token));
        $this->assertNull($result, $result, "Original token should be null.");
    }

    public function testGetOriginalToken()
    {
        $originalToken = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');

        $role = $this->getMockBuilder('Symfony\Component\Security\Core\Role\SwitchUserRole')
            ->disableOriginalConstructor()
            ->getMock();
        $role->expects($this->any())
            ->method('getSource')
            ->will($this->returnValue($originalToken));

        $token = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');
        $token->expects($this->any())
            ->method('getRoles')
            ->will($this->returnValue(array($role)));

        $reflectedClass = new \ReflectionClass('Symfony\Component\Security\Http\Firewall\SwitchUserListener');
        $method = $reflectedClass->getMethod('getOriginalToken');
        $method->setAccessible(true);

        $object = $reflectedClass->newInstanceArgs(array(
            $this->tokenStorage,
            $this->userProvider,
            $this->userChecker,
            'foo_provider',
            $this->accessDecisionManager
        ));

        $result = $method->invokeArgs($object, array($token));
        $this->assertTrue($result === $originalToken, "Original token was expected.");
    }
}
