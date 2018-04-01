<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Security\Http\Tests\EntryPoint;

use PHPUnit\Framework\TestCase;
use Symphony\Component\HttpFoundation\Response;
use Symphony\Component\Security\Http\EntryPoint\FormAuthenticationEntryPoint;
use Symphony\Component\HttpKernel\HttpKernelInterface;

class FormAuthenticationEntryPointTest extends TestCase
{
    public function testStart()
    {
        $request = $this->getMockBuilder('Symphony\Component\HttpFoundation\Request')->disableOriginalConstructor()->disableOriginalClone()->getMock();
        $response = new Response();

        $httpKernel = $this->getMockBuilder('Symphony\Component\HttpKernel\HttpKernelInterface')->getMock();
        $httpUtils = $this->getMockBuilder('Symphony\Component\Security\Http\HttpUtils')->getMock();
        $httpUtils
            ->expects($this->once())
            ->method('createRedirectResponse')
            ->with($this->equalTo($request), $this->equalTo('/the/login/path'))
            ->will($this->returnValue($response))
        ;

        $entryPoint = new FormAuthenticationEntryPoint($httpKernel, $httpUtils, '/the/login/path', false);

        $this->assertEquals($response, $entryPoint->start($request));
    }

    public function testStartWithUseForward()
    {
        $request = $this->getMockBuilder('Symphony\Component\HttpFoundation\Request')->disableOriginalConstructor()->disableOriginalClone()->getMock();
        $subRequest = $this->getMockBuilder('Symphony\Component\HttpFoundation\Request')->disableOriginalConstructor()->disableOriginalClone()->getMock();
        $response = new Response('', 200);

        $httpUtils = $this->getMockBuilder('Symphony\Component\Security\Http\HttpUtils')->getMock();
        $httpUtils
            ->expects($this->once())
            ->method('createRequest')
            ->with($this->equalTo($request), $this->equalTo('/the/login/path'))
            ->will($this->returnValue($subRequest))
        ;

        $httpKernel = $this->getMockBuilder('Symphony\Component\HttpKernel\HttpKernelInterface')->getMock();
        $httpKernel
            ->expects($this->once())
            ->method('handle')
            ->with($this->equalTo($subRequest), $this->equalTo(HttpKernelInterface::SUB_REQUEST))
            ->will($this->returnValue($response))
        ;

        $entryPoint = new FormAuthenticationEntryPoint($httpKernel, $httpUtils, '/the/login/path', true);

        $entryPointResponse = $entryPoint->start($request);

        $this->assertEquals($response, $entryPointResponse);
        $this->assertEquals(401, $entryPointResponse->getStatusCode());
    }
}
