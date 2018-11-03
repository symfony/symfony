<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\RedirectController;
use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @author Marcin Sikon <marcin.sikon@gmail.com>
 */
class RedirectControllerTest extends TestCase
{
    public function testEmptyRoute()
    {
        $request = new Request();
        $controller = new RedirectController();

        try {
            $controller->redirectAction($request, '', true);
            $this->fail('Expected Symfony\Component\HttpKernel\Exception\HttpException to be thrown');
        } catch (HttpException $e) {
            $this->assertSame(410, $e->getStatusCode());
        }

        try {
            $controller->redirectAction($request, '', false);
            $this->fail('Expected Symfony\Component\HttpKernel\Exception\HttpException to be thrown');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
    }

    /**
     * @dataProvider provider
     */
    public function testRoute($permanent, $keepRequestMethod, $keepQueryParams, $ignoreAttributes, $expectedCode, $expectedAttributes)
    {
        $request = new Request();

        $route = 'new-route';
        $url = '/redirect-url';
        $attributes = array(
            'route' => $route,
            'permanent' => $permanent,
            '_route' => 'current-route',
            '_route_params' => array(
                'route' => $route,
                'permanent' => $permanent,
                'additional-parameter' => 'value',
                'ignoreAttributes' => $ignoreAttributes,
                'keepRequestMethod' => $keepRequestMethod,
                'keepQueryParams' => $keepQueryParams,
            ),
        );

        $request->attributes = new ParameterBag($attributes);

        $router = $this->getMockBuilder(UrlGeneratorInterface::class)->getMock();
        $router
            ->expects($this->once())
            ->method('generate')
            ->with($this->equalTo($route), $this->equalTo($expectedAttributes))
            ->will($this->returnValue($url));

        $controller = new RedirectController($router);

        $returnResponse = $controller->redirectAction($request, $route, $permanent, $ignoreAttributes, $keepRequestMethod, $keepQueryParams);

        $this->assertRedirectUrl($returnResponse, $url);
        $this->assertEquals($expectedCode, $returnResponse->getStatusCode());
    }

    public function provider()
    {
        return array(
            array(true, false, false, false, 301, array('additional-parameter' => 'value')),
            array(false, false, false, false, 302, array('additional-parameter' => 'value')),
            array(false, false, false, true, 302, array()),
            array(false, false, false, array('additional-parameter'), 302, array()),
            array(true, true, false, false, 308, array('additional-parameter' => 'value')),
            array(false, true, false, false, 307, array('additional-parameter' => 'value')),
            array(false, true, false, true, 307, array()),
            array(false, true, true, array('additional-parameter'), 307, array()),
        );
    }

    public function testEmptyPath()
    {
        $request = new Request();
        $controller = new RedirectController();

        try {
            $controller->urlRedirectAction($request, '', true);
            $this->fail('Expected Symfony\Component\HttpKernel\Exception\HttpException to be thrown');
        } catch (HttpException $e) {
            $this->assertSame(410, $e->getStatusCode());
        }

        try {
            $controller->urlRedirectAction($request, '', false);
            $this->fail('Expected Symfony\Component\HttpKernel\Exception\HttpException to be thrown');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
    }

    public function testFullURL()
    {
        $request = new Request();
        $controller = new RedirectController();
        $returnResponse = $controller->urlRedirectAction($request, 'http://foo.bar/');

        $this->assertRedirectUrl($returnResponse, 'http://foo.bar/');
        $this->assertEquals(302, $returnResponse->getStatusCode());
    }

    public function testFullURLWithMethodKeep()
    {
        $request = new Request();
        $controller = new RedirectController();
        $returnResponse = $controller->urlRedirectAction($request, 'http://foo.bar/', false, null, null, null, true);

        $this->assertRedirectUrl($returnResponse, 'http://foo.bar/');
        $this->assertEquals(307, $returnResponse->getStatusCode());
    }

    public function testUrlRedirectDefaultPorts()
    {
        $host = 'www.example.com';
        $baseUrl = '/base';
        $path = '/redirect-path';
        $httpPort = 1080;
        $httpsPort = 1443;

        $expectedUrl = "https://$host:$httpsPort$baseUrl$path";
        $request = $this->createRequestObject('http', $host, $httpPort, $baseUrl);
        $controller = $this->createRedirectController(null, $httpsPort);
        $returnValue = $controller->urlRedirectAction($request, $path, false, 'https');
        $this->assertRedirectUrl($returnValue, $expectedUrl);

        $expectedUrl = "http://$host:$httpPort$baseUrl$path";
        $request = $this->createRequestObject('https', $host, $httpPort, $baseUrl);
        $controller = $this->createRedirectController($httpPort);
        $returnValue = $controller->urlRedirectAction($request, $path, false, 'http');
        $this->assertRedirectUrl($returnValue, $expectedUrl);
    }

    public function urlRedirectProvider()
    {
        return array(
            // Standard ports
            array('http',  null, null,  'http',  80,   ''),
            array('http',  80,   null,  'http',  80,   ''),
            array('https', null, null,  'http',  80,   ''),
            array('https', 80,   null,  'http',  80,   ''),

            array('http',  null,  null, 'https', 443,  ''),
            array('http',  null,  443,  'https', 443,  ''),
            array('https', null,  null, 'https', 443,  ''),
            array('https', null,  443,  'https', 443,  ''),

            // Non-standard ports
            array('http',  null,  null, 'http',  8080, ':8080'),
            array('http',  4080,  null, 'http',  8080, ':4080'),
            array('http',  80,    null, 'http',  8080, ''),
            array('https', null,  null, 'http',  8080, ''),
            array('https', null,  8443, 'http',  8080, ':8443'),
            array('https', null,  443,  'http',  8080, ''),

            array('https', null,  null, 'https', 8443, ':8443'),
            array('https', null,  4443, 'https', 8443, ':4443'),
            array('https', null,  443,  'https', 8443, ''),
            array('http',  null,  null, 'https', 8443, ''),
            array('http',  8080,  4443, 'https', 8443, ':8080'),
            array('http',  80,    4443, 'https', 8443, ''),
        );
    }

    /**
     * @dataProvider urlRedirectProvider
     */
    public function testUrlRedirect($scheme, $httpPort, $httpsPort, $requestScheme, $requestPort, $expectedPort)
    {
        $host = 'www.example.com';
        $baseUrl = '/base';
        $path = '/redirect-path';
        $expectedUrl = "$scheme://$host$expectedPort$baseUrl$path";

        $request = $this->createRequestObject($requestScheme, $host, $requestPort, $baseUrl);
        $controller = $this->createRedirectController();

        $returnValue = $controller->urlRedirectAction($request, $path, false, $scheme, $httpPort, $httpsPort);
        $this->assertRedirectUrl($returnValue, $expectedUrl);
    }

    public function pathQueryParamsProvider()
    {
        return array(
            array('http://www.example.com/base/redirect-path', '/redirect-path',  ''),
            array('http://www.example.com/base/redirect-path?foo=bar', '/redirect-path?foo=bar',  ''),
            array('http://www.example.com/base/redirect-path?foo=bar', '/redirect-path', 'foo=bar'),
            array('http://www.example.com/base/redirect-path?foo=bar&abc=example', '/redirect-path?foo=bar', 'abc=example'),
            array('http://www.example.com/base/redirect-path?foo=bar&abc=example&baz=def', '/redirect-path?foo=bar', 'abc=example&baz=def'),
        );
    }

    /**
     * @dataProvider pathQueryParamsProvider
     */
    public function testPathQueryParams($expectedUrl, $path, $queryString)
    {
        $scheme = 'http';
        $host = 'www.example.com';
        $baseUrl = '/base';
        $port = 80;

        $request = $this->createRequestObject($scheme, $host, $port, $baseUrl, $queryString);

        $controller = $this->createRedirectController();

        $returnValue = $controller->urlRedirectAction($request, $path, false, $scheme, $port, null);
        $this->assertRedirectUrl($returnValue, $expectedUrl);
    }

    public function testRedirectWithQuery()
    {
        $scheme = 'http';
        $host = 'www.example.com';
        $baseUrl = '/base';
        $port = 80;

        $request = $this->createRequestObject($scheme, $host, $port, $baseUrl, 'base=zaza');
        $request->query = new ParameterBag(array('base' => 'zaza'));
        $request->attributes = new ParameterBag(array('_route_params' => array('base2' => 'zaza')));
        $urlGenerator = $this->getMockBuilder(UrlGeneratorInterface::class)->getMock();
        $urlGenerator->expects($this->once())->method('generate')->will($this->returnValue('/test?base=zaza&base2=zaza'))->with('/test', array('base' => 'zaza', 'base2' => 'zaza'), UrlGeneratorInterface::ABSOLUTE_URL);

        $controller = new RedirectController($urlGenerator);
        $this->assertRedirectUrl($controller->redirectAction($request, '/test', false, false, false, true), '/test?base=zaza&base2=zaza');
    }

    public function testRedirectWithQueryWithRouteParamsOveriding()
    {
        $scheme = 'http';
        $host = 'www.example.com';
        $baseUrl = '/base';
        $port = 80;

        $request = $this->createRequestObject($scheme, $host, $port, $baseUrl, 'base=zaza');
        $request->query = new ParameterBag(array('base' => 'zaza'));
        $request->attributes = new ParameterBag(array('_route_params' => array('base' => 'zouzou')));
        $urlGenerator = $this->getMockBuilder(UrlGeneratorInterface::class)->getMock();
        $urlGenerator->expects($this->once())->method('generate')->will($this->returnValue('/test?base=zouzou'))->with('/test', array('base' => 'zouzou'), UrlGeneratorInterface::ABSOLUTE_URL);

        $controller = new RedirectController($urlGenerator);
        $this->assertRedirectUrl($controller->redirectAction($request, '/test', false, false, false, true), '/test?base=zouzou');
    }

    private function createRequestObject($scheme, $host, $port, $baseUrl, $queryString = '')
    {
        $request = $this->getMockBuilder('Symfony\Component\HttpFoundation\Request')->getMock();
        $request
            ->expects($this->any())
            ->method('getScheme')
            ->will($this->returnValue($scheme));
        $request
            ->expects($this->any())
            ->method('getHost')
            ->will($this->returnValue($host));
        $request
            ->expects($this->any())
            ->method('getPort')
            ->will($this->returnValue($port));
        $request
            ->expects($this->any())
            ->method('getBaseUrl')
            ->will($this->returnValue($baseUrl));
        $request
            ->expects($this->any())
            ->method('getQueryString')
            ->will($this->returnValue($queryString));

        return $request;
    }

    private function createRedirectController($httpPort = null, $httpsPort = null)
    {
        return new RedirectController(null, $httpPort, $httpsPort);
    }

    private function assertRedirectUrl(Response $returnResponse, $expectedUrl)
    {
        $this->assertTrue($returnResponse->isRedirect($expectedUrl), "Expected: $expectedUrl\nGot:      ".$returnResponse->headers->get('Location'));
    }
}
