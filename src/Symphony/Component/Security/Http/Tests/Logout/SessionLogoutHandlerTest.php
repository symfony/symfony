<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Security\Http\Tests\Logout;

use PHPUnit\Framework\TestCase;
use Symphony\Component\HttpFoundation\Response;
use Symphony\Component\Security\Http\Logout\SessionLogoutHandler;

class SessionLogoutHandlerTest extends TestCase
{
    public function testLogout()
    {
        $handler = new SessionLogoutHandler();

        $request = $this->getMockBuilder('Symphony\Component\HttpFoundation\Request')->getMock();
        $response = new Response();
        $session = $this->getMockBuilder('Symphony\Component\HttpFoundation\Session\Session')->disableOriginalConstructor()->getMock();

        $request
            ->expects($this->once())
            ->method('getSession')
            ->will($this->returnValue($session))
        ;

        $session
            ->expects($this->once())
            ->method('invalidate')
        ;

        $handler->logout($request, $response, $this->getMockBuilder('Symphony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock());
    }
}
