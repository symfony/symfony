<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\HttpUtils;

class HttpUtilsTest extends TestCase
{
    public function testCreateRedirectResponseWithPath(): void
    {
        $utils = new HttpUtils($this->getUrlGenerator());
        $response = $utils->createRedirectResponse($this->getRequest(), '/foobar');

        $this->assertTrue($response->isRedirect('http://localhost/foobar'));
        $this->assertEquals(302, $response->getStatusCode());
    }

    public function testCreateRedirectResponseWithAbsoluteUrl(): void
    {
        $utils = new HttpUtils($this->getUrlGenerator());
        $response = $utils->createRedirectResponse($this->getRequest(), 'http://symfony.com/');

        $this->assertTrue($response->isRedirect('http://symfony.com/'));
    }

    public function testCreateRedirectResponseWithRouteName(): void
    {
        $utils = new HttpUtils($urlGenerator = $this->getMockBuilder('Symfony\Component\Routing\Generator\UrlGeneratorInterface')->getMock());

        $urlGenerator
            ->expects($this->any())
            ->method('generate')
            ->with('foobar', array(), UrlGeneratorInterface::ABSOLUTE_URL)
            ->will($this->returnValue('http://localhost/foo/bar'))
        ;
        $urlGenerator
            ->expects($this->any())
            ->method('getContext')
            ->will($this->returnValue($this->getMockBuilder('Symfony\Component\Routing\RequestContext')->getMock()))
        ;

        $response = $utils->createRedirectResponse($this->getRequest(), 'foobar');

        $this->assertTrue($response->isRedirect('http://localhost/foo/bar'));
    }

    public function testCreateRequestWithPath(): void
    {
        $request = $this->getRequest();
        $request->server->set('Foo', 'bar');

        $utils = new HttpUtils($this->getUrlGenerator());
        $subRequest = $utils->createRequest($request, '/foobar');

        $this->assertEquals('GET', $subRequest->getMethod());
        $this->assertEquals('/foobar', $subRequest->getPathInfo());
        $this->assertEquals('bar', $subRequest->server->get('Foo'));
    }

    public function testCreateRequestWithRouteName(): void
    {
        $utils = new HttpUtils($urlGenerator = $this->getMockBuilder('Symfony\Component\Routing\Generator\UrlGeneratorInterface')->getMock());

        $urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->will($this->returnValue('/foo/bar'))
        ;
        $urlGenerator
            ->expects($this->any())
            ->method('getContext')
            ->will($this->returnValue($this->getMockBuilder('Symfony\Component\Routing\RequestContext')->getMock()))
        ;

        $subRequest = $utils->createRequest($this->getRequest(), 'foobar');

        $this->assertEquals('/foo/bar', $subRequest->getPathInfo());
    }

    public function testCreateRequestWithAbsoluteUrl(): void
    {
        $utils = new HttpUtils($this->getMockBuilder('Symfony\Component\Routing\Generator\UrlGeneratorInterface')->getMock());
        $subRequest = $utils->createRequest($this->getRequest(), 'http://symfony.com/');

        $this->assertEquals('/', $subRequest->getPathInfo());
    }

    public function testCreateRequestPassesSessionToTheNewRequest(): void
    {
        $request = $this->getRequest();
        $request->setSession($session = $this->getMockBuilder('Symfony\Component\HttpFoundation\Session\SessionInterface')->getMock());

        $utils = new HttpUtils($this->getUrlGenerator());
        $subRequest = $utils->createRequest($request, '/foobar');

        $this->assertSame($session, $subRequest->getSession());
    }

    /**
     * @dataProvider provideSecurityContextAttributes
     */
    public function testCreateRequestPassesSecurityContextAttributesToTheNewRequest($attribute): void
    {
        $request = $this->getRequest();
        $request->attributes->set($attribute, 'foo');

        $utils = new HttpUtils($this->getUrlGenerator());
        $subRequest = $utils->createRequest($request, '/foobar');

        $this->assertSame('foo', $subRequest->attributes->get($attribute));
    }

    public function provideSecurityContextAttributes()
    {
        return array(
            array(Security::AUTHENTICATION_ERROR),
            array(Security::ACCESS_DENIED_ERROR),
            array(Security::LAST_USERNAME),
        );
    }

    public function testCheckRequestPath(): void
    {
        $utils = new HttpUtils($this->getUrlGenerator());

        $this->assertTrue($utils->checkRequestPath($this->getRequest(), '/'));
        $this->assertFalse($utils->checkRequestPath($this->getRequest(), '/foo'));
        $this->assertTrue($utils->checkRequestPath($this->getRequest('/foo%20bar'), '/foo bar'));
        // Plus must not decoded to space
        $this->assertTrue($utils->checkRequestPath($this->getRequest('/foo+bar'), '/foo+bar'));
        // Checking unicode
        $this->assertTrue($utils->checkRequestPath($this->getRequest('/'.urlencode('вход')), '/вход'));
    }

    public function testCheckRequestPathWithUrlMatcherAndResourceNotFound(): void
    {
        $urlMatcher = $this->getMockBuilder('Symfony\Component\Routing\Matcher\UrlMatcherInterface')->getMock();
        $urlMatcher
            ->expects($this->any())
            ->method('match')
            ->with('/')
            ->will($this->throwException(new ResourceNotFoundException()))
        ;

        $utils = new HttpUtils(null, $urlMatcher);
        $this->assertFalse($utils->checkRequestPath($this->getRequest(), 'foobar'));
    }

    public function testCheckRequestPathWithUrlMatcherAndMethodNotAllowed(): void
    {
        $request = $this->getRequest();
        $urlMatcher = $this->getMockBuilder('Symfony\Component\Routing\Matcher\RequestMatcherInterface')->getMock();
        $urlMatcher
            ->expects($this->any())
            ->method('matchRequest')
            ->with($request)
            ->will($this->throwException(new MethodNotAllowedException(array())))
        ;

        $utils = new HttpUtils(null, $urlMatcher);
        $this->assertFalse($utils->checkRequestPath($request, 'foobar'));
    }

    public function testCheckRequestPathWithUrlMatcherAndResourceFoundByUrl(): void
    {
        $urlMatcher = $this->getMockBuilder('Symfony\Component\Routing\Matcher\UrlMatcherInterface')->getMock();
        $urlMatcher
            ->expects($this->any())
            ->method('match')
            ->with('/foo/bar')
            ->will($this->returnValue(array('_route' => 'foobar')))
        ;

        $utils = new HttpUtils(null, $urlMatcher);
        $this->assertTrue($utils->checkRequestPath($this->getRequest('/foo/bar'), 'foobar'));
    }

    public function testCheckRequestPathWithUrlMatcherAndResourceFoundByRequest(): void
    {
        $request = $this->getRequest();
        $urlMatcher = $this->getMockBuilder('Symfony\Component\Routing\Matcher\RequestMatcherInterface')->getMock();
        $urlMatcher
            ->expects($this->any())
            ->method('matchRequest')
            ->with($request)
            ->will($this->returnValue(array('_route' => 'foobar')))
        ;

        $utils = new HttpUtils(null, $urlMatcher);
        $this->assertTrue($utils->checkRequestPath($request, 'foobar'));
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testCheckRequestPathWithUrlMatcherLoadingException(): void
    {
        $urlMatcher = $this->getMockBuilder('Symfony\Component\Routing\Matcher\UrlMatcherInterface')->getMock();
        $urlMatcher
            ->expects($this->any())
            ->method('match')
            ->will($this->throwException(new \RuntimeException()))
        ;

        $utils = new HttpUtils(null, $urlMatcher);
        $utils->checkRequestPath($this->getRequest(), 'foobar');
    }

    public function testCheckPathWithoutRouteParam(): void
    {
        $urlMatcher = $this->getMockBuilder('Symfony\Component\Routing\Matcher\UrlMatcherInterface')->getMock();
        $urlMatcher
            ->expects($this->any())
            ->method('match')
            ->willReturn(array('_controller' => 'PathController'))
        ;

        $utils = new HttpUtils(null, $urlMatcher);
        $this->assertFalse($utils->checkRequestPath($this->getRequest(), 'path/index.html'));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Matcher must either implement UrlMatcherInterface or RequestMatcherInterface
     */
    public function testUrlMatcher(): void
    {
        new HttpUtils($this->getUrlGenerator(), new \stdClass());
    }

    public function testGenerateUriRemovesQueryString(): void
    {
        $utils = new HttpUtils($this->getUrlGenerator('/foo/bar'));
        $this->assertEquals('/foo/bar', $utils->generateUri(new Request(), 'route_name'));

        $utils = new HttpUtils($this->getUrlGenerator('/foo/bar?param=value'));
        $this->assertEquals('/foo/bar', $utils->generateUri(new Request(), 'route_name'));
    }

    public function testGenerateUriPreservesFragment(): void
    {
        $utils = new HttpUtils($this->getUrlGenerator('/foo/bar?param=value#fragment'));
        $this->assertEquals('/foo/bar#fragment', $utils->generateUri(new Request(), 'route_name'));

        $utils = new HttpUtils($this->getUrlGenerator('/foo/bar#fragment'));
        $this->assertEquals('/foo/bar#fragment', $utils->generateUri(new Request(), 'route_name'));
    }

    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage You must provide a UrlGeneratorInterface instance to be able to use routes.
     */
    public function testUrlGeneratorIsRequiredToGenerateUrl(): void
    {
        $utils = new HttpUtils();
        $utils->generateUri(new Request(), 'route_name');
    }

    private function getUrlGenerator($generatedUrl = '/foo/bar')
    {
        $urlGenerator = $this->getMockBuilder('Symfony\Component\Routing\Generator\UrlGeneratorInterface')->getMock();
        $urlGenerator
            ->expects($this->any())
            ->method('generate')
            ->will($this->returnValue($generatedUrl))
        ;

        return $urlGenerator;
    }

    private function getRequest($path = '/')
    {
        return Request::create($path, 'get');
    }
}
