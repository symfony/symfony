<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\HttpKernel\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Symphony\Component\EventDispatcher\EventDispatcher;
use Symphony\Component\HttpFoundation\Request;
use Symphony\Component\HttpFoundation\RequestStack;
use Symphony\Component\HttpFoundation\Response;
use Symphony\Component\HttpKernel\Controller\ArgumentResolver;
use Symphony\Component\HttpKernel\Controller\ControllerResolver;
use Symphony\Component\HttpKernel\EventListener\ExceptionListener;
use Symphony\Component\HttpKernel\EventListener\RouterListener;
use Symphony\Component\HttpKernel\EventListener\ValidateRequestListener;
use Symphony\Component\HttpKernel\HttpKernelInterface;
use Symphony\Component\HttpKernel\HttpKernel;
use Symphony\Component\HttpKernel\Event\GetResponseEvent;
use Symphony\Component\Routing\Exception\NoConfigurationException;
use Symphony\Component\Routing\RequestContext;

class RouterListenerTest extends TestCase
{
    private $requestStack;

    protected function setUp()
    {
        $this->requestStack = $this->getMockBuilder('Symphony\Component\HttpFoundation\RequestStack')->disableOriginalConstructor()->getMock();
    }

    /**
     * @dataProvider getPortData
     */
    public function testPort($defaultHttpPort, $defaultHttpsPort, $uri, $expectedHttpPort, $expectedHttpsPort)
    {
        $urlMatcher = $this->getMockBuilder('Symphony\Component\Routing\Matcher\UrlMatcherInterface')
            ->disableOriginalConstructor()
            ->getMock();
        $context = new RequestContext();
        $context->setHttpPort($defaultHttpPort);
        $context->setHttpsPort($defaultHttpsPort);
        $urlMatcher->expects($this->any())
            ->method('getContext')
            ->will($this->returnValue($context));

        $listener = new RouterListener($urlMatcher, $this->requestStack);
        $event = $this->createGetResponseEventForUri($uri);
        $listener->onKernelRequest($event);

        $this->assertEquals($expectedHttpPort, $context->getHttpPort());
        $this->assertEquals($expectedHttpsPort, $context->getHttpsPort());
        $this->assertEquals(0 === strpos($uri, 'https') ? 'https' : 'http', $context->getScheme());
    }

    public function getPortData()
    {
        return array(
            array(80, 443, 'http://localhost/', 80, 443),
            array(80, 443, 'http://localhost:90/', 90, 443),
            array(80, 443, 'https://localhost/', 80, 443),
            array(80, 443, 'https://localhost:90/', 80, 90),
        );
    }

    private function createGetResponseEventForUri(string $uri): GetResponseEvent
    {
        $kernel = $this->getMockBuilder('Symphony\Component\HttpKernel\HttpKernelInterface')->getMock();
        $request = Request::create($uri);
        $request->attributes->set('_controller', null); // Prevents going in to routing process

        return new GetResponseEvent($kernel, $request, HttpKernelInterface::MASTER_REQUEST);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testInvalidMatcher()
    {
        new RouterListener(new \stdClass(), $this->requestStack);
    }

    public function testRequestMatcher()
    {
        $kernel = $this->getMockBuilder('Symphony\Component\HttpKernel\HttpKernelInterface')->getMock();
        $request = Request::create('http://localhost/');
        $event = new GetResponseEvent($kernel, $request, HttpKernelInterface::MASTER_REQUEST);

        $requestMatcher = $this->getMockBuilder('Symphony\Component\Routing\Matcher\RequestMatcherInterface')->getMock();
        $requestMatcher->expects($this->once())
                       ->method('matchRequest')
                       ->with($this->isInstanceOf('Symphony\Component\HttpFoundation\Request'))
                       ->will($this->returnValue(array()));

        $listener = new RouterListener($requestMatcher, $this->requestStack, new RequestContext());
        $listener->onKernelRequest($event);
    }

    public function testSubRequestWithDifferentMethod()
    {
        $kernel = $this->getMockBuilder('Symphony\Component\HttpKernel\HttpKernelInterface')->getMock();
        $request = Request::create('http://localhost/', 'post');
        $event = new GetResponseEvent($kernel, $request, HttpKernelInterface::MASTER_REQUEST);

        $requestMatcher = $this->getMockBuilder('Symphony\Component\Routing\Matcher\RequestMatcherInterface')->getMock();
        $requestMatcher->expects($this->any())
                       ->method('matchRequest')
                       ->with($this->isInstanceOf('Symphony\Component\HttpFoundation\Request'))
                       ->will($this->returnValue(array()));

        $context = new RequestContext();

        $listener = new RouterListener($requestMatcher, $this->requestStack, new RequestContext());
        $listener->onKernelRequest($event);

        // sub-request with another HTTP method
        $kernel = $this->getMockBuilder('Symphony\Component\HttpKernel\HttpKernelInterface')->getMock();
        $request = Request::create('http://localhost/', 'get');
        $event = new GetResponseEvent($kernel, $request, HttpKernelInterface::SUB_REQUEST);

        $listener->onKernelRequest($event);

        $this->assertEquals('GET', $context->getMethod());
    }

    /**
     * @dataProvider getLoggingParameterData
     */
    public function testLoggingParameter($parameter, $log, $parameters)
    {
        $requestMatcher = $this->getMockBuilder('Symphony\Component\Routing\Matcher\RequestMatcherInterface')->getMock();
        $requestMatcher->expects($this->once())
            ->method('matchRequest')
            ->will($this->returnValue($parameter));

        $logger = $this->getMockBuilder('Psr\Log\LoggerInterface')->getMock();
        $logger->expects($this->once())
            ->method('info')
            ->with($this->equalTo($log), $this->equalTo($parameters));

        $kernel = $this->getMockBuilder('Symphony\Component\HttpKernel\HttpKernelInterface')->getMock();
        $request = Request::create('http://localhost/');

        $listener = new RouterListener($requestMatcher, $this->requestStack, new RequestContext(), $logger);
        $listener->onKernelRequest(new GetResponseEvent($kernel, $request, HttpKernelInterface::MASTER_REQUEST));
    }

    public function getLoggingParameterData()
    {
        return array(
            array(array('_route' => 'foo'), 'Matched route "{route}".', array('route' => 'foo', 'route_parameters' => array('_route' => 'foo'), 'request_uri' => 'http://localhost/', 'method' => 'GET')),
            array(array(), 'Matched route "{route}".', array('route' => 'n/a', 'route_parameters' => array(), 'request_uri' => 'http://localhost/', 'method' => 'GET')),
        );
    }

    public function testWithBadRequest()
    {
        $requestStack = new RequestStack();

        $requestMatcher = $this->getMockBuilder('Symphony\Component\Routing\Matcher\RequestMatcherInterface')->getMock();
        $requestMatcher->expects($this->never())->method('matchRequest');

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new ValidateRequestListener());
        $dispatcher->addSubscriber(new RouterListener($requestMatcher, $requestStack, new RequestContext()));
        $dispatcher->addSubscriber(new ExceptionListener(function () {
            return new Response('Exception handled', 400);
        }));

        $kernel = new HttpKernel($dispatcher, new ControllerResolver(), $requestStack, new ArgumentResolver());

        $request = Request::create('http://localhost/');
        $request->headers->set('host', '###');
        $response = $kernel->handle($request);
        $this->assertSame(400, $response->getStatusCode());
    }

    public function testNoRoutingConfigurationResponse()
    {
        $requestStack = new RequestStack();

        $requestMatcher = $this->getMockBuilder('Symphony\Component\Routing\Matcher\RequestMatcherInterface')->getMock();
        $requestMatcher
            ->expects($this->once())
            ->method('matchRequest')
            ->willThrowException(new NoConfigurationException())
        ;

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new RouterListener($requestMatcher, $requestStack, new RequestContext()));

        $kernel = new HttpKernel($dispatcher, new ControllerResolver(), $requestStack, new ArgumentResolver());

        $request = Request::create('http://localhost/');
        $response = $kernel->handle($request);
        $this->assertSame(404, $response->getStatusCode());
        $this->assertContains('Welcome', $response->getContent());
    }

    /**
     * @expectedException \Symphony\Component\HttpKernel\Exception\BadRequestHttpException
     */
    public function testRequestWithBadHost()
    {
        $kernel = $this->getMockBuilder('Symphony\Component\HttpKernel\HttpKernelInterface')->getMock();
        $request = Request::create('http://bad host %22/');
        $event = new GetResponseEvent($kernel, $request, HttpKernelInterface::MASTER_REQUEST);

        $requestMatcher = $this->getMockBuilder('Symphony\Component\Routing\Matcher\RequestMatcherInterface')->getMock();

        $listener = new RouterListener($requestMatcher, $this->requestStack, new RequestContext());
        $listener->onKernelRequest($event);
    }
}
