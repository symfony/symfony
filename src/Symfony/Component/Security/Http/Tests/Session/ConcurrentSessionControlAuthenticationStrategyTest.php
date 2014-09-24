<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\Session;

use Symfony\Component\Security\Http\Session\ConcurrentSessionControlAuthenticationStrategy;

/**
 * @author Antonio J. García Lagar <aj@garcialagar.es>
 */
class ConcurrentSessionControlAuthenticationStrategyTest extends \PHPUnit_Framework_TestCase
{
    public function testSessionsCountLesserThanAllowed()
    {
        $request = $this->getRequest($this->getSession());

        $registry = $this->getSessionRegistry();
        $registry->expects($this->once())
            ->method('getAllSessions')
            ->will($this->returnValue(array(
                $this->getSessionInformation(),
                $this->getSessionInformation()
            )));

        $strategy = new ConcurrentSessionControlAuthenticationStrategy($registry, 3);
        $this->assertNull($strategy->onAuthentication($request, $this->getToken()));
    }

    public function testSessionsCountEqualsThanAllowedWithRegisteredSession()
    {
        $request = $this->getRequest($this->getSession('bar'));

        $registry = $this->getSessionRegistry();
        $registry->expects($this->once())
            ->method('getAllSessions')
            ->will($this->returnValue(array(
                $this->getSessionInformation('bar'),
                $this->getSessionInformation('foo')
            )));

        $strategy = new ConcurrentSessionControlAuthenticationStrategy($registry, 2);
        $this->assertNull($strategy->onAuthentication($request, $this->getToken()));
    }

    /**
     * @expectedException Symfony\Component\Security\Core\Exception\MaxSessionsExceededException
     * @expectedExceptionMessage Maximum number of sessions (2) exceeded
     */
    public function testSessionsCountEqualsThanAllowedWithUnregisteredSession()
    {
        $request = $this->getRequest($this->getSession('foobar'));

        $registry = $this->getSessionRegistry();
        $registry->expects($this->once())
            ->method('getAllSessions')
            ->will($this->returnValue(array(
                $this->getSessionInformation('bar'),
                $this->getSessionInformation('foo')
            )));

        $strategy = new ConcurrentSessionControlAuthenticationStrategy($registry, 2);
        $this->assertNull($strategy->onAuthentication($request, $this->getToken()));
    }

    public function testExpiresOldSessionsWhenNoExceptionIsThrownIfMaximunExceeded()
    {
        $request = $this->getRequest($this->getSession('foobar'));

        $registry = $this->getSessionRegistry();
        $registry->expects($this->once())
            ->method('getAllSessions')
            ->will($this->returnValue(array(
                $this->getSessionInformation('foo'),
                $this->getSessionInformation('bar'),
                $this->getSessionInformation('barfoo')
            )));

        $registry->expects($this->at(1))
            ->method('expireNow')
            ->with($this->equalTo('bar'))
        ;
        $registry->expects($this->at(2))
            ->method('expireNow')
            ->with($this->equalTo('barfoo'))
        ;

        $strategy = new ConcurrentSessionControlAuthenticationStrategy($registry, 2, false);
        $this->assertNull($strategy->onAuthentication($request, $this->getToken()));
    }

    private function getSession($sessionId = null)
    {
        $session = $this->getMock('Symfony\Component\HttpFoundation\Session\SessionInterface');
        if (null !== $sessionId) {
            $session->expects($this->any())->method('getId')->will($this->returnValue($sessionId));
        }

        return $session;
    }

    private function getRequest($session = null)
    {
        $request = $this->getMock('Symfony\Component\HttpFoundation\Request');

        if (null !== $session) {
            $request->expects($this->any())->method('getSession')->will($this->returnValue($session));
        }

        return $request;
    }

    private function getToken()
    {
        $token = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');
        $token->expects($this->any())->method('getUsername')->will($this->returnValue('foo'));

        return $token;
    }

    private function getSessionRegistry()
    {
        return $this->getMockBuilder('Symfony\Component\Security\Http\Session\SessionRegistry')
            ->disableOriginalConstructor()
            ->getMock();
    }

    private function getSessionInformation($sessionId = null, $username = null)
    {
        $sessionInfo = $this->getMockBuilder('Symfony\Component\Security\Http\Session\SessionInformation')
            ->disableOriginalConstructor()
            ->getMock();

        if (null !== $sessionId) {
            $sessionInfo->expects($this->any())->method('getSessionId')->will($this->returnValue($sessionId));
        }

        if (null !== $username) {
            $sessionInfo->expects($this->any())->method('getUsername')->will($this->returnValue($username));
        }

        return $sessionInfo;
    }
}
