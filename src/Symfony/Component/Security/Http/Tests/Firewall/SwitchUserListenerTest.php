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

use Symfony\Component\Security\Http\Event\SwitchUserEvent;
use Symfony\Component\Security\Http\Firewall\SwitchUserListener;
use Symfony\Component\Security\Http\SecurityEvents;

class SwitchUserListenerTest extends \PHPUnit_Framework_TestCase
{
    private $tokenStorage;

    private $userProvider;

    private $userChecker;

    private $accessDecisionManager;

    private $request;

    private $event;

    protected function setUp()
    {
        $this->tokenStorage = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface')->getMock();
        $this->userProvider = $this->getMockBuilder('Symfony\Component\Security\Core\User\UserProviderInterface')->getMock();
        $this->userChecker = $this->getMockBuilder('Symfony\Component\Security\Core\User\UserCheckerInterface')->getMock();
        $this->accessDecisionManager = $this->getMockBuilder('Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface')->getMock();
        $this->request = $this->getMockBuilder('Symfony\Component\HttpFoundation\Request')->getMock();
        $this->request->query = $this->getMockBuilder('Symfony\Component\HttpFoundation\ParameterBag')->getMock();
        $this->request->server = $this->getMockBuilder('Symfony\Component\HttpFoundation\ServerBag')->getMock();
        $this->event = $this->getEvent($this->request);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage $providerKey must not be empty
     */
    public function testProviderKeyIsRequired()
    {
        new SwitchUserListener($this->tokenStorage, $this->userProvider, $this->userChecker, '', $this->accessDecisionManager);
    }

    public function testEventIsIgnoredIfUsernameIsNotPassedWithTheRequest()
    {
        $this->request->expects($this->any())->method('get')->with('_switch_user')->will($this->returnValue(null));

        $this->event->expects($this->never())->method('setResponse');
        $this->tokenStorage->expects($this->never())->method('setToken');

        $listener = new SwitchUserListener($this->tokenStorage, $this->userProvider, $this->userChecker, 'provider123', $this->accessDecisionManager);
        $listener->handle($this->event);
    }

    /**
     * @expectedException \Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException
     */
    public function testExitUserThrowsAuthenticationExceptionIfOriginalTokenCannotBeFound()
    {
        $token = $this->getToken(array($this->getMockBuilder('Symfony\Component\Security\Core\Role\RoleInterface')->getMock()));

        $this->tokenStorage->expects($this->any())->method('getToken')->will($this->returnValue($token));
        $this->request->expects($this->any())->method('get')->with('_switch_user')->will($this->returnValue('_exit'));

        $listener = new SwitchUserListener($this->tokenStorage, $this->userProvider, $this->userChecker, 'provider123', $this->accessDecisionManager);
        $listener->handle($this->event);
    }

    public function testExitUserUpdatesToken()
    {
        $originalToken = $this->getToken();
        $role = $this->getMockBuilder('Symfony\Component\Security\Core\Role\SwitchUserRole')
            ->disableOriginalConstructor()
            ->getMock();
        $role->expects($this->any())->method('getSource')->will($this->returnValue($originalToken));

        $this->tokenStorage->expects($this->any())
            ->method('getToken')
            ->will($this->returnValue($this->getToken(array($role))));

        $this->request->expects($this->any())->method('get')->with('_switch_user')->will($this->returnValue('_exit'));
        $this->request->expects($this->any())->method('getUri')->will($this->returnValue('/'));
        $this->request->query->expects($this->once())->method('remove', '_switch_user');
        $this->request->query->expects($this->any())->method('all')->will($this->returnValue(array()));
        $this->request->server->expects($this->once())->method('set')->with('QUERY_STRING', '');

        $this->tokenStorage->expects($this->once())
            ->method('setToken')->with($originalToken);
        $this->event->expects($this->once())
            ->method('setResponse')->with($this->isInstanceOf('Symfony\Component\HttpFoundation\RedirectResponse'));

        $listener = new SwitchUserListener($this->tokenStorage, $this->userProvider, $this->userChecker, 'provider123', $this->accessDecisionManager);
        $listener->handle($this->event);
    }

    public function testExitUserDispatchesEventWithRefreshedUser()
    {
        $originalUser = $this->getMockBuilder('Symfony\Component\Security\Core\User\UserInterface')->getMock();
        $refreshedUser = $this->getMockBuilder('Symfony\Component\Security\Core\User\UserInterface')->getMock();
        $this
            ->userProvider
            ->expects($this->any())
            ->method('refreshUser')
            ->with($originalUser)
            ->willReturn($refreshedUser);
        $originalToken = $this->getToken();
        $originalToken
            ->expects($this->any())
            ->method('getUser')
            ->willReturn($originalUser);
        $role = $this
            ->getMockBuilder('Symfony\Component\Security\Core\Role\SwitchUserRole')
            ->disableOriginalConstructor()
            ->getMock();
        $role->expects($this->any())->method('getSource')->willReturn($originalToken);
        $this
            ->tokenStorage
            ->expects($this->any())
            ->method('getToken')
            ->willReturn($this->getToken(array($role)));
        $this
            ->request
            ->expects($this->any())
            ->method('get')
            ->with('_switch_user')
            ->willReturn('_exit');
        $this
            ->request
            ->expects($this->any())
            ->method('getUri')
            ->willReturn('/');
        $this
            ->request
            ->query
            ->expects($this->any())
            ->method('all')
            ->will($this->returnValue(array()));

        $dispatcher = $this->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcherInterface')->getMock();
        $dispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(SecurityEvents::SWITCH_USER, $this->callback(function (SwitchUserEvent $event) use ($refreshedUser) {
                return $event->getTargetUser() === $refreshedUser;
            }))
        ;

        $listener = new SwitchUserListener($this->tokenStorage, $this->userProvider, $this->userChecker, 'provider123', $this->accessDecisionManager, null, '_switch_user', 'ROLE_ALLOWED_TO_SWITCH', $dispatcher);
        $listener->handle($this->event);
    }

    public function testExitUserDoesNotDispatchEventWithStringUser()
    {
        $originalUser = 'anon.';
        $this
            ->userProvider
            ->expects($this->never())
            ->method('refreshUser');
        $originalToken = $this->getToken();
        $originalToken
            ->expects($this->any())
            ->method('getUser')
            ->willReturn($originalUser);
        $role = $this
            ->getMockBuilder('Symfony\Component\Security\Core\Role\SwitchUserRole')
            ->disableOriginalConstructor()
            ->getMock();
        $role
            ->expects($this->any())
            ->method('getSource')
            ->willReturn($originalToken);
        $this
            ->tokenStorage
            ->expects($this->any())
            ->method('getToken')
            ->willReturn($this->getToken(array($role)));
        $this
            ->request
            ->expects($this->any())
            ->method('get')
            ->with('_switch_user')
            ->willReturn('_exit');
        $this
            ->request
            ->query
            ->expects($this->any())
            ->method('all')
            ->will($this->returnValue(array()));
        $this
            ->request
            ->expects($this->any())
            ->method('getUri')
            ->willReturn('/');

        $dispatcher = $this->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcherInterface')->getMock();
        $dispatcher
            ->expects($this->never())
            ->method('dispatch')
        ;

        $listener = new SwitchUserListener($this->tokenStorage, $this->userProvider, $this->userChecker, 'provider123', $this->accessDecisionManager, null, '_switch_user', 'ROLE_ALLOWED_TO_SWITCH', $dispatcher);
        $listener->handle($this->event);
    }

    /**
     * @expectedException \Symfony\Component\Security\Core\Exception\AccessDeniedException
     */
    public function testSwitchUserIsDisallowed()
    {
        $token = $this->getToken(array($this->getMockBuilder('Symfony\Component\Security\Core\Role\RoleInterface')->getMock()));

        $this->tokenStorage->expects($this->any())->method('getToken')->will($this->returnValue($token));
        $this->request->expects($this->any())->method('get')->with('_switch_user')->will($this->returnValue('kuba'));

        $this->accessDecisionManager->expects($this->once())
            ->method('decide')->with($token, array('ROLE_ALLOWED_TO_SWITCH'))
            ->will($this->returnValue(false));

        $listener = new SwitchUserListener($this->tokenStorage, $this->userProvider, $this->userChecker, 'provider123', $this->accessDecisionManager);
        $listener->handle($this->event);
    }

    public function testSwitchUser()
    {
        $token = $this->getToken(array($this->getMockBuilder('Symfony\Component\Security\Core\Role\RoleInterface')->getMock()));
        $user = $this->getMockBuilder('Symfony\Component\Security\Core\User\UserInterface')->getMock();
        $user->expects($this->any())->method('getRoles')->will($this->returnValue(array()));

        $this->tokenStorage->expects($this->any())->method('getToken')->will($this->returnValue($token));
        $this->request->expects($this->any())->method('get')->with('_switch_user')->will($this->returnValue('kuba'));
        $this->request->query->expects($this->once())->method('remove', '_switch_user');
        $this->request->query->expects($this->any())->method('all')->will($this->returnValue(array()));

        $this->request->expects($this->any())->method('getUri')->will($this->returnValue('/'));
        $this->request->server->expects($this->once())->method('set')->with('QUERY_STRING', '');

        $this->accessDecisionManager->expects($this->once())
            ->method('decide')->with($token, array('ROLE_ALLOWED_TO_SWITCH'))
            ->will($this->returnValue(true));

        $this->userProvider->expects($this->once())
            ->method('loadUserByUsername')->with('kuba')
            ->will($this->returnValue($user));
        $this->userChecker->expects($this->once())
            ->method('checkPostAuth')->with($user);
        $this->tokenStorage->expects($this->once())
            ->method('setToken')->with($this->isInstanceOf('Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken'));

        $listener = new SwitchUserListener($this->tokenStorage, $this->userProvider, $this->userChecker, 'provider123', $this->accessDecisionManager);
        $listener->handle($this->event);
    }

    public function testSwitchUserKeepsOtherQueryStringParameters()
    {
        $token = $this->getToken(array($this->getMockBuilder('Symfony\Component\Security\Core\Role\RoleInterface')->getMock()));
        $user = $this->getMockBuilder('Symfony\Component\Security\Core\User\UserInterface')->getMock();
        $user->expects($this->any())->method('getRoles')->will($this->returnValue(array()));

        $this->tokenStorage->expects($this->any())->method('getToken')->will($this->returnValue($token));
        $this->request->expects($this->any())->method('get')->with('_switch_user')->will($this->returnValue('kuba'));
        $this->request->query->expects($this->once())->method('remove', '_switch_user');
        $this->request->query->expects($this->any())->method('all')->will($this->returnValue(array('page' => 3, 'section' => 2)));
        $this->request->expects($this->any())->method('getUri')->will($this->returnValue('/'));
        $this->request->server->expects($this->once())->method('set')->with('QUERY_STRING', 'page=3&section=2');

        $this->accessDecisionManager->expects($this->once())
            ->method('decide')->with($token, array('ROLE_ALLOWED_TO_SWITCH'))
            ->will($this->returnValue(true));

        $this->userProvider->expects($this->once())
            ->method('loadUserByUsername')->with('kuba')
            ->will($this->returnValue($user));
        $this->userChecker->expects($this->once())
            ->method('checkPostAuth')->with($user);
        $this->tokenStorage->expects($this->once())
            ->method('setToken')->with($this->isInstanceOf('Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken'));

        $listener = new SwitchUserListener($this->tokenStorage, $this->userProvider, $this->userChecker, 'provider123', $this->accessDecisionManager);
        $listener->handle($this->event);
    }

    private function getEvent($request)
    {
        $event = $this->getMockBuilder('Symfony\Component\HttpKernel\Event\GetResponseEvent')
            ->disableOriginalConstructor()
            ->getMock();

        $event->expects($this->any())
            ->method('getRequest')
            ->will($this->returnValue($request));

        return $event;
    }

    private function getToken(array $roles = array())
    {
        $token = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock();
        $token->expects($this->any())
            ->method('getRoles')
            ->will($this->returnValue($roles));

        return $token;
    }
}
