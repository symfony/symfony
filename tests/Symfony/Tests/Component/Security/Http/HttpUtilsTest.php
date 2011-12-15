<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Tests\Component\Security\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class HttpUtilsTest extends \PHPUnit_Framework_TestCase
{
    public function testCreateRedirectResponse()
    {
        $utils = new HttpUtils($this->getRouter());

        // absolute path
        $response = $utils->createRedirectResponse($this->getRequest(), '/foobar');
        $this->assertTrue($response->isRedirect('http://localhost/foobar'));
        $this->assertEquals(302, $response->getStatusCode());

        // absolute URL
        $response = $utils->createRedirectResponse($this->getRequest(), 'http://symfony.com/');
        $this->assertTrue($response->isRedirect('http://symfony.com/'));

        // route name
        $utils = new HttpUtils($router = $this->getMockBuilder('Symfony\Component\Routing\Router')->disableOriginalConstructor()->getMock());
        $router
            ->expects($this->any())
            ->method('generate')
            ->with('foobar', array(), true)
            ->will($this->returnValue('http://localhost/foo/bar'))
        ;
        $router
            ->expects($this->any())
            ->method('getContext')
            ->will($this->returnValue($this->getMock('Symfony\Component\Routing\RequestContext')))
        ;
        $response = $utils->createRedirectResponse($this->getRequest(), 'foobar');
        $this->assertTrue($response->isRedirect('http://localhost/foo/bar'));
    }

    public function testCreateRequest()
    {
        $utils = new HttpUtils($this->getRouter());

        // absolute path
        $request = $this->getRequest();
        $request->server->set('Foo', 'bar');
        $subRequest = $utils->createRequest($request, '/foobar');

        $this->assertEquals('GET', $subRequest->getMethod());
        $this->assertEquals('/foobar', $subRequest->getPathInfo());
        $this->assertEquals('bar', $subRequest->server->get('Foo'));

        // route name
        $utils = new HttpUtils($router = $this->getMockBuilder('Symfony\Component\Routing\Router')->disableOriginalConstructor()->getMock());
        $router
            ->expects($this->once())
            ->method('generate')
            ->will($this->returnValue('/foo/bar'))
        ;
        $router
            ->expects($this->any())
            ->method('getContext')
            ->will($this->returnValue($this->getMock('Symfony\Component\Routing\RequestContext')))
        ;
        $subRequest = $utils->createRequest($this->getRequest(), 'foobar');
        $this->assertEquals('/foo/bar', $subRequest->getPathInfo());

        // absolute URL
        $subRequest = $utils->createRequest($this->getRequest(), 'http://symfony.com/');
        $this->assertEquals('/', $subRequest->getPathInfo());
    }

    public function testCheckRequestPath()
    {
        $utils = new HttpUtils($this->getRouter());

        $this->assertTrue($utils->checkRequestPath($this->getRequest(), '/'));
        $this->assertFalse($utils->checkRequestPath($this->getRequest(), '/foo'));

        $router = $this->getMock('Symfony\Component\Routing\RouterInterface');
        $router
            ->expects($this->any())
            ->method('match')
            ->will($this->throwException(new ResourceNotFoundException()))
        ;
        $utils = new HttpUtils($router);
        $this->assertFalse($utils->checkRequestPath($this->getRequest(), 'foobar'));

        $router = $this->getMock('Symfony\Component\Routing\RouterInterface');
        $router
            ->expects($this->any())
            ->method('match')
            ->will($this->returnValue(array('_route' => 'foobar')))
        ;
        $utils = new HttpUtils($router);
        $this->assertTrue($utils->checkRequestPath($this->getRequest('/foo/bar'), 'foobar'));
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testCheckRequestPathWithRouterLoadingException()
    {
        $router = $this->getMock('Symfony\Component\Routing\RouterInterface');
        $router
            ->expects($this->any())
            ->method('match')
            ->will($this->throwException(new \RuntimeException()))
        ;
        $utils = new HttpUtils($router);
        $utils->checkRequestPath($this->getRequest(), 'foobar');
    }

    private function getRouter()
    {
        $router = $this->getMock('Symfony\Component\Routing\RouterInterface');
        $router
            ->expects($this->any())
            ->method('generate')
            ->will($this->returnValue('/foo/bar'))
        ;

        return $router;
    }

    private function getRequest($path = '/')
    {
        return Request::create($path, 'get');
    }
}
