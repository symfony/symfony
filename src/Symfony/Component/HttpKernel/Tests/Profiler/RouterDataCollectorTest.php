<?php

namespace Symfony\Component\HttpKernel\Tests\Profiler;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Profiler\RouterDataCollector;

class RouterDataCollectorTest extends \PHPUnit_Framework_TestCase
{
    public function testCollect()
    {
        $requestStack = new RequestStack();
        $c = new RouterDataCollector($requestStack);

        $requestStack->push($this->createRequest());

        $c->onKernelResponse(
            new FilterResponseEvent(
                $this->getKernel(), $requestStack->getMasterRequest(), HttpKernelInterface::MASTER_REQUEST, $this->createResponse()
            )
        );

        $data = $c->getCollectedData();

        $this->assertInstanceof('Symfony\Component\HttpKernel\Profiler\RouterData', $data);
        $this->assertFalse($data->getRedirect());
    }

    public function testCollectRedirectResponse()
    {
        $requestStack = new RequestStack();
        $c = new RouterDataCollector($requestStack);

        $request = $this->createRequest();
        $requestStack->push($request);

        $c->onKernelResponse(
            new FilterResponseEvent(
                $this->getKernel(), $requestStack->getMasterRequest(), HttpKernelInterface::MASTER_REQUEST, new RedirectResponse('dummy')
            )
        );

        $this->injectController($c, array($this, 'testCollectRedirectResponse'), $request);

        $data = $c->getCollectedData();
        $this->assertInstanceof('Symfony\Component\HttpKernel\Profiler\RouterData', $data);
        $this->assertSame('n/a', $data->getTargetRoute());
        $this->assertSame('dummy', $data->getTargetUrl());
        $this->assertTrue($data->getRedirect());
    }

    public function testCollectNoResponseForRequest()
    {
        $requestStack = new RequestStack();
        $c = new RouterDataCollector($requestStack);

        $requestStack->push($this->createRequest());

        $this->assertNull($c->getCollectedData());
    }

    public function testSubscribedEvents()
    {
        $events = RouterDataCollector::getSubscribedEvents();
        $this->assertArrayHasKey(KernelEvents::CONTROLLER, $events);
        $this->assertArrayHasKey(KernelEvents::RESPONSE, $events);
    }

    protected function createRequest()
    {
        $request = Request::create('http://test.com/foo?bar=baz');
        $request->attributes->set('foo', 'bar');
        $request->attributes->set('_route', 'foobar');
        $request->attributes->set('_route_params', array('name' => 'foo'));
        $request->attributes->set('resource', fopen(__FILE__, 'r'));
        $request->attributes->set('object', new \stdClass());

        return $request;
    }

    protected function createResponse()
    {
        $response = new Response();
        $response->setStatusCode(200);
        $response->headers->set('Content-Type', 'application/json');
        $response->headers->setCookie(new Cookie('foo', 'bar', 1, '/foo', 'localhost', true, true));
        $response->headers->setCookie(new Cookie('bar', 'foo', new \DateTime('@946684800')));
        $response->headers->setCookie(new Cookie('bazz', 'foo', '2000-12-12'));

        return $response;
    }

    /**
     * Inject the given controller callable into the data collector.
     */
    protected function injectController($collector, $controller, $request)
    {
        $resolver = $this->getMock('Symfony\\Component\\HttpKernel\\Controller\\ControllerResolverInterface');
        $httpKernel = new HttpKernel(new EventDispatcher(), $resolver);
        $event = new FilterControllerEvent($httpKernel, $controller, $request, HttpKernelInterface::MASTER_REQUEST);
        $collector->onKernelController($event);
    }

    protected function getKernel()
    {
        return $this->getMock('Symfony\Component\HttpKernel\KernelInterface');
    }
}
