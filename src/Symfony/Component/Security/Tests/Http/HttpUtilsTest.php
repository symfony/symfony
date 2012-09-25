<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Tests\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class HttpUtilsTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        if (!class_exists('Symfony\Component\HttpFoundation\Request')) {
            $this->markTestSkipped('The "HttpFoundation" component is not available');
        }

        if (!class_exists('Symfony\Component\Routing\Router')) {
            $this->markTestSkipped('The "Routing" component is not available');
        }
    }

    public function testCreateRedirectResponse()
    {
        $utils = new HttpUtils($this->getUrlGenerator());

        // absolute path
        $response = $utils->createRedirectResponse($this->getRequest(), '/foobar');
        $this->assertTrue($response->isRedirect('http://localhost/foobar'));
        $this->assertEquals(302, $response->getStatusCode());

        // absolute URL
        $response = $utils->createRedirectResponse($this->getRequest(), 'http://symfony.com/');
        $this->assertTrue($response->isRedirect('http://symfony.com/'));

        // route name
        $utils = new HttpUtils($urlGenerator = $this->getMock('Symfony\Component\Routing\Generator\UrlGeneratorInterface'));
        $urlGenerator
            ->expects($this->any())
            ->method('generate')
            ->with('foobar', array(), true)
            ->will($this->returnValue('http://localhost/foo/bar'))
        ;
        $urlGenerator
            ->expects($this->any())
            ->method('getContext')
            ->will($this->returnValue($this->getMock('Symfony\Component\Routing\RequestContext')))
        ;
        $response = $utils->createRedirectResponse($this->getRequest(), 'foobar');
        $this->assertTrue($response->isRedirect('http://localhost/foo/bar'));
    }

    public function testCreateRequest()
    {
        $utils = new HttpUtils($this->getUrlGenerator());

        // absolute path
        $request = $this->getRequest();
        $request->server->set('Foo', 'bar');
        $subRequest = $utils->createRequest($request, '/foobar');

        $this->assertEquals('GET', $subRequest->getMethod());
        $this->assertEquals('/foobar', $subRequest->getPathInfo());
        $this->assertEquals('bar', $subRequest->server->get('Foo'));

        // route name
        $utils = new HttpUtils($urlGenerator = $this->getMock('Symfony\Component\Routing\Generator\UrlGeneratorInterface'));
        $urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->will($this->returnValue('/foo/bar'))
        ;
        $urlGenerator
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
        $utils = new HttpUtils($this->getUrlGenerator());

        $this->assertTrue($utils->checkRequestPath($this->getRequest(), '/'));
        $this->assertFalse($utils->checkRequestPath($this->getRequest(), '/foo'));

        $urlMatcher = $this->getMock('Symfony\Component\Routing\Matcher\UrlMatcherInterface');
        $urlMatcher
            ->expects($this->any())
            ->method('match')
            ->will($this->throwException(new ResourceNotFoundException()))
        ;
        $utils = new HttpUtils(null, $urlMatcher);
        $this->assertFalse($utils->checkRequestPath($this->getRequest(), 'foobar'));

        $urlMatcher = $this->getMock('Symfony\Component\Routing\Matcher\UrlMatcherInterface');
        $urlMatcher
            ->expects($this->any())
            ->method('match')
            ->will($this->returnValue(array('_route' => 'foobar')))
        ;
        $utils = new HttpUtils(null, $urlMatcher);
        $this->assertTrue($utils->checkRequestPath($this->getRequest('/foo/bar'), 'foobar'));
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testCheckRequestPathWithUrlMatcherLoadingException()
    {
        $urlMatcher = $this->getMock('Symfony\Component\Routing\Matcher\UrlMatcherInterface');
        $urlMatcher
            ->expects($this->any())
            ->method('match')
            ->will($this->throwException(new \RuntimeException()))
        ;
        $utils = new HttpUtils(null, $urlMatcher);
        $utils->checkRequestPath($this->getRequest(), 'foobar');
    }

    private function getUrlGenerator()
    {
        $urlGenerator = $this->getMock('Symfony\Component\Routing\Generator\UrlGeneratorInterface');
        $urlGenerator
            ->expects($this->any())
            ->method('generate')
            ->will($this->returnValue('/foo/bar'))
        ;

        return $urlGenerator;
    }

    private function getRequest($path = '/')
    {
        return Request::create($path, 'get');
    }
}
