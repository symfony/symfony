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
use Symphony\Component\Security\Http\Logout\DefaultLogoutSuccessHandler;

class DefaultLogoutSuccessHandlerTest extends TestCase
{
    public function testLogout()
    {
        $request = $this->getMockBuilder('Symphony\Component\HttpFoundation\Request')->getMock();
        $response = new Response();

        $httpUtils = $this->getMockBuilder('Symphony\Component\Security\Http\HttpUtils')->getMock();
        $httpUtils->expects($this->once())
            ->method('createRedirectResponse')
            ->with($request, '/dashboard')
            ->will($this->returnValue($response));

        $handler = new DefaultLogoutSuccessHandler($httpUtils, '/dashboard');
        $result = $handler->onLogoutSuccess($request);

        $this->assertSame($response, $result);
    }
}
