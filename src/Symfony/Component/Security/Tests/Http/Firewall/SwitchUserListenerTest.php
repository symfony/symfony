<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Tests\Http\Firewall;

use Symfony\Component\Security\Http\Firewall\SwitchUserListener;

class SwitchUserListenerTest extends \PHPUnit_Framework_TestCase
{
    private $securityContext;

    private $userProvider;

    private $userChecker;

    private $accessDecisionManager;

    private $request;

    private $event;

    protected function setUp()
    {
        if (!class_exists('Symfony\Component\HttpFoundation\Request')) {
            $this->markTestSkipped('The "HttpFoundation" component is not available');
        }

        if (!class_exists('Symfony\Component\HttpKernel\HttpKernel')) {
            $this->markTestSkipped('The "HttpKernel" component is not available');
        }

        $this->securityContext = $this->getMock('Symfony\Component\Security\Core\SecurityContextInterface');
        $this->userProvider = $this->getMock('Symfony\Component\Security\Core\User\UserProviderInterface');
        $this->userChecker = $this->getMock('Symfony\Component\Security\Core\User\UserCheckerInterface');
        $this->accessDecisionManager = $this->getMock('Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface');
        $this->request = $this->getMock('Symfony\Component\HttpFoundation\Request');
        $this->request->server = $this->getMock('Symfony\Component\HttpFoundation\ServerBag');
        $this->event = $this->getEvent($this->request);
    }

    public function testSwitchUserEncoded()
    {
        $token = $this->getToken(array($this->getMock('Symfony\Component\Security\Core\Role\RoleInterface')));
        $user = $this->getMock('Symfony\Component\Security\Core\User\UserInterface');
        $user->expects($this->any())->method('getRoles')->will($this->returnValue(array()));

        $this->securityContext->expects($this->any())->method('getToken')->will($this->returnValue($token));
        $this->request->expects($this->any())->method('get')->with('_switch_user')->will($this->returnValue('foo%40bar.com'));
        $this->request->expects($this->any())->method('getUri')->will($this->returnValue('/'));
        $this->request->server->expects($this->once())->method('set')->with('QUERY_STRING', '');

        $this->accessDecisionManager->expects($this->once())
            ->method('decide')->with($token, array('ROLE_ALLOWED_TO_SWITCH'))
            ->will($this->returnValue(true));

        $this->userProvider->expects($this->once())
            ->method('loadUserByUsername')->with('foo@bar.com')
            ->will($this->returnValue($user));
        $this->userChecker->expects($this->once())
            ->method('checkPostAuth')->with($user);
        $this->securityContext->expects($this->once())
            ->method('setToken')->with($this->isInstanceOf('Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken'));

        $listener = new SwitchUserListener($this->securityContext, $this->userProvider, $this->userChecker, 'provider123', $this->accessDecisionManager);
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
        $token = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');
        $token->expects($this->any())
            ->method('getRoles')
            ->will($this->returnValue($roles));

        return $token;
    }
}