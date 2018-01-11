<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\EventListener\RouterListener;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Routing\RequestContext;

class RouterListenerTest extends TestCase
{
    private $requestStack;

    protected function setUp()
    {
        $this->requestStack = $this->getMockBuilder('Symfony\Component\HttpFoundation\RequestStack')->disableOriginalConstructor()->getMock();
    }

    /**
     * @dataProvider getPortData
     */
    public function testPort($defaultHttpPort, $defaultHttpsPort, $uri, $expectedHttpPort, $expectedHttpsPort)
    {
        $urlMatcher = $this->getMockBuilder('Symfony\Component\Routing\Matcher\UrlMatcherInterface')
            ->disableOriginalConstructor()
            ->getMock();
        $context = new RequestContext();
        $context->setHttpPort($defaultHttpPort);
        $context->setHttpsPort($defaultHttpsPort);
        $urlMatcher->expects($this->any())
            ->method('getContext')
            ->will($this->returnValue($context));

        $listener = new RouterListener($urlMatcher, null, null, $this->requestStack);
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

    /**
     * @param string $uri
     *
     * @return GetResponseEvent
     */
    private function createGetResponseEventForUri($uri)
    {
        $kernel = $this->getMockBuilder('Symfony\Component\HttpKernel\HttpKernelInterface')->getMock();
        $request = Request::create($uri);
        $request->attributes->set('_controller', null); // Prevents going in to routing process

        return new GetResponseEvent($kernel, $request, HttpKernelInterface::MASTER_REQUEST);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testInvalidMatcher()
    {
        new RouterListener(new \stdClass(), null, null, $this->requestStack);
    }

    public function testRequestMatcher()
    {
        $kernel = $this->getMockBuilder('Symfony\Component\HttpKernel\HttpKernelInterface')->getMock();
        $request = Request::create('http://localhost/');
        $event = new GetResponseEvent($kernel, $request, HttpKernelInterface::MASTER_REQUEST);

        $requestMatcher = $this->getMockBuilder('Symfony\Component\Routing\Matcher\RequestMatcherInterface')->getMock();
        $requestMatcher->expects($this->once())
                       ->method('matchRequest')
                       ->with($this->isInstanceOf('Symfony\Component\HttpFoundation\Request'))
                       ->will($this->returnValue(array()));

        $listener = new RouterListener($requestMatcher, new RequestContext(), null, $this->requestStack);
        $listener->onKernelRequest($event);
    }

    public function testSubRequestWithDifferentMethod()
    {
        $kernel = $this->getMockBuilder('Symfony\Component\HttpKernel\HttpKernelInterface')->getMock();
        $request = Request::create('http://localhost/', 'post');
        $event = new GetResponseEvent($kernel, $request, HttpKernelInterface::MASTER_REQUEST);

        $requestMatcher = $this->getMockBuilder('Symfony\Component\Routing\Matcher\RequestMatcherInterface')->getMock();
        $requestMatcher->expects($this->any())
                       ->method('matchRequest')
                       ->with($this->isInstanceOf('Symfony\Component\HttpFoundation\Request'))
                       ->will($this->returnValue(array()));

        $context = new RequestContext();

        $listener = new RouterListener($requestMatcher, new RequestContext(), null, $this->requestStack);
        $listener->onKernelRequest($event);

        // sub-request with another HTTP method
        $kernel = $this->getMockBuilder('Symfony\Component\HttpKernel\HttpKernelInterface')->getMock();
        $request = Request::create('http://localhost/', 'get');
        $event = new GetResponseEvent($kernel, $request, HttpKernelInterface::SUB_REQUEST);

        $listener->onKernelRequest($event);

        $this->assertEquals('GET', $context->getMethod());
    }

    /**
     * @dataProvider getLoggingParameterData
     */
    public function testLoggingParameter($parameter, $log)
    {
        $requestMatcher = $this->getMockBuilder('Symfony\Component\Routing\Matcher\RequestMatcherInterface')->getMock();
        $requestMatcher->expects($this->once())
            ->method('matchRequest')
            ->will($this->returnValue($parameter));

        $logger = $this->getMockBuilder('Psr\Log\LoggerInterface')->getMock();
        $logger->expects($this->once())
            ->method('info')
            ->with($this->equalTo($log));

        $kernel = $this->getMockBuilder('Symfony\Component\HttpKernel\HttpKernelInterface')->getMock();
        $request = Request::create('http://localhost/');

        $listener = new RouterListener($requestMatcher, new RequestContext(), $logger, $this->requestStack);
        $listener->onKernelRequest(new GetResponseEvent($kernel, $request, HttpKernelInterface::MASTER_REQUEST));
    }

    public function getLoggingParameterData()
    {
        return array(
            array(array('_route' => 'foo'), 'Matched route "foo".'),
            array(array(), 'Matched route "n/a".'),
        );
    }

    /**
     * @expectedException \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
     */
    public function testRequestWithBadHost()
    {
        $kernel = $this->getMockBuilder('Symfony\Component\HttpKernel\HttpKernelInterface')->getMock();
        $request = Request::create('http://bad host %22/');
        $event = new GetResponseEvent($kernel, $request, HttpKernelInterface::MASTER_REQUEST);

        $requestMatcher = $this->getMockBuilder('Symfony\Component\Routing\Matcher\RequestMatcherInterface')->getMock();

        $listener = new RouterListener($requestMatcher, new RequestContext(), null, $this->requestStack);
        $listener->onKernelRequest($event);
    }
}
