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

use Symfony\Component\Security\Http\Session\RegisterSessionAuthenticationStrategy;

/**
 * @author Antonio J. García Lagar <aj@garcialagar.es>
 */
class RegisterSessionAuthenticationStrategyTest extends \PHPUnit_Framework_TestCase
{
    public function testRegisterSession()
    {
        $session = $this->getMock('Symfony\Component\HttpFoundation\Session\SessionInterface');
        $session->expects($this->any())->method('getId')->will($this->returnValue('bar'));
        $request = $this->getRequest($session);

        $registry = $this->getSessionRegistry();
        $registry->expects($this->once())->method('registerNewSession')->with($this->equalTo('bar'), $this->equalTo('foo'));

        $strategy = new RegisterSessionAuthenticationStrategy($registry);
        $strategy->onAuthentication($request, $this->getToken());
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
}
