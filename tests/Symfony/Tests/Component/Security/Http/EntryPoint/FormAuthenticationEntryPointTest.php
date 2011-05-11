<?php

namespace Symfony\Tests\Component\Security\Http\EntryPoint;

use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\EntryPoint\FormAuthenticationEntryPoint;

class FormAuthenticationEntryPointTest extends \PHPUnit_Framework_TestCase
{
    public function testNormalRedirect()
    {
        $ep = new FormAuthenticationEntryPoint($this->createKernelMock(), '/login_foo', false, false);

        $request = $this->createRequestMock();
        $request->expects($this->any())
            ->method('getUriForPath')
            ->will($this->returnValue('/login_foo'));

        $response = $ep->start($request);
        $this->assertInstanceOf('Symfony\Component\HttpFoundation\Response', $response);
        $this->assertEquals('/login_foo', $response->headers->get('location'));
    }

    public function testRedirectLoopWithoutDisallowRedirect()
    {
        $ep = new FormAuthenticationEntryPoint($this->createKernelMock(), '/login_foo', false, false);

        $request = $this->createRequestMock();
        $request->expects($this->any())
            ->method('getUri')
            ->will($this->returnValue('/redirect-loop-url'));
        $request->expects($this->any())
            ->method('getUriForPath')
            ->will($this->returnValue('/redirect-loop-url'));

        $response = $ep->start($request);
        // redirect loops are allowed because $disallowRedirectLoop is false
        $this->assertInstanceOf('Symfony\Component\HttpFoundation\Response', $response);
        $this->assertEquals('/redirect-loop-url', $response->headers->get('location'));
    }

    public function testRedirectLoopWithDisallowRedirectThrowsException()
    {
        $ep = new FormAuthenticationEntryPoint($this->createKernelMock(), '/login_foo', false, true);

        $request = $this->createRequestMock();
        $request->expects($this->any())
            ->method('getUri')
            ->will($this->returnValue('/redirect-loop-url'));
        $request->expects($this->any())
            ->method('getUriForPath')
            ->will($this->returnValue('/redirect-loop-url'));

        $this->setExpectedException('LogicException');
        $ep->start($request);
    }

    private function createKernelMock()
    {
        return $this->getMock('Symfony\Component\HttpKernel\HttpKernelInterface');
    }

    private function createRequestMock()
    {
        return $this->getMock('Symfony\Component\HttpFoundation\Request');
    }
}