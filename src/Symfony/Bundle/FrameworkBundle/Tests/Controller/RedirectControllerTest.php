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

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\RedirectController;
use Symfony\Bundle\FrameworkBundle\Tests\TestCase;

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
    public function testRoute($permanent, $ignoreAttributes, $expectedCode, $expectedAttributes)
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

        $returnResponse = $controller->redirectAction($request, $route, $permanent, $ignoreAttributes);

        $this->assertRedirectUrl($returnResponse, $url);
        $this->assertEquals($expectedCode, $returnResponse->getStatusCode());
    }

    public function provider()
    {
        return array(
            array(true, false, 301, array('additional-parameter' => 'value')),
            array(false, false, 302, array('additional-parameter' => 'value')),
            array(false, true, 302, array()),
            array(false, array('additional-parameter'), 302, array()),
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

    /**
     * @group legacy
     */
    public function testUrlRedirectDefaultPortParameters()
    {
        $host = 'www.example.com';
        $baseUrl = '/base';
        $path = '/redirect-path';
        $httpPort = 1080;
        $httpsPort = 1443;

        $expectedUrl = "https://$host:$httpsPort$baseUrl$path";
        $request = $this->createRequestObject('http', $host, $httpPort, $baseUrl);
        $controller = $this->createLegacyRedirectController(null, $httpsPort);
        $returnValue = $controller->urlRedirectAction($request, $path, false, 'https');
        $this->assertRedirectUrl($returnValue, $expectedUrl);

        $expectedUrl = "http://$host:$httpPort$baseUrl$path";
        $request = $this->createRequestObject('https', $host, $httpPort, $baseUrl);
        $controller = $this->createLegacyRedirectController($httpPort);
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

    /**
     * @deprecated
     */
    private function createLegacyRedirectController($httpPort = null, $httpsPort = null)
    {
        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerInterface')->getMock();

        if (null !== $httpPort) {
            $container
                ->expects($this->once())
                ->method('hasParameter')
                ->with($this->equalTo('request_listener.http_port'))
                ->will($this->returnValue(true));
            $container
                ->expects($this->once())
                ->method('getParameter')
                ->with($this->equalTo('request_listener.http_port'))
                ->will($this->returnValue($httpPort));
        }
        if (null !== $httpsPort) {
            $container
                ->expects($this->once())
                ->method('hasParameter')
                ->with($this->equalTo('request_listener.https_port'))
                ->will($this->returnValue(true));
            $container
                ->expects($this->once())
                ->method('getParameter')
                ->with($this->equalTo('request_listener.https_port'))
                ->will($this->returnValue($httpsPort));
        }

        $controller = new RedirectController();
        $controller->setContainer($container);

        return $controller;
    }

    public function assertRedirectUrl(Response $returnResponse, $expectedUrl)
    {
        $this->assertTrue($returnResponse->isRedirect($expectedUrl), "Expected: $expectedUrl\nGot:      ".$returnResponse->headers->get('Location'));
    }
}
