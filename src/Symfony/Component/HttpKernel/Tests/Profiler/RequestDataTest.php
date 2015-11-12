<?php

namespace Symfony\Component\HttpKernel\Tests\Profiler;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\HttpFoundation\Session\Storage\MetadataBag;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Profiler\RequestDataCollector;

class RequestDataTest extends \PHPUnit_Framework_TestCase
{
    public function testMaskPasswords()
    {
        $requestStack = new RequestStack();
        $c = new RequestDataCollector($requestStack);

        $request = new Request(
            array(),
            array('_password' => 'test'),
            array(),
            array(),
            array(),
            array(
                'PHP_AUTH_PW' => 'test',
                'HTTP_php-auth-pw' => 'test',
            )
        );
        $requestStack->push($request);

        $c->onKernelResponse(
            new FilterResponseEvent(
                $this->getKernel(), $requestStack->getMasterRequest(), HttpKernelInterface::MASTER_REQUEST, $this->createResponse()
            )
        );
        $data = $c->getCollectedData();
        $this->assertNotSame('test', $data->getRequestHeaders()->get('php-auth-pw'));
        $this->assertNotSame('test', $data->getRequestServer()->get('PHP_AUTH_PW'));
        $this->assertNotSame('test', $data->getRequestRequest()->get('_password'));
    }

    public function testSessions()
    {
        $requestStack = new RequestStack();
        $c = new RequestDataCollector($requestStack);

        /** @var \Symfony\Component\HttpFoundation\Session\Session $session */
        $session = $this->getMockBuilder('Symfony\Component\HttpFoundation\Session\Session')
            ->disableOriginalConstructor()
            ->getMock();

        $session->expects($this->once())->method('isStarted')->willReturn(true);
        $sessionMetadataBag = new MetadataBag();
        $session->expects($this->any())->method('getMetadataBag')->willReturn($sessionMetadataBag);
        $flashBag = new FlashBag();
        $session->expects($this->any())->method('getBag')->with('flashes')->willReturn($flashBag);
        $flashBag->add('test', 'Testing');

        $session->start();
        $request = new Request();
        $request->setSession($session);
        $requestStack->push($request);

        $c->onKernelResponse(
            new FilterResponseEvent(
                $this->getKernel(), $requestStack->getMasterRequest(), HttpKernelInterface::MASTER_REQUEST, $this->createResponse()
            )
        );
        $data = $c->getCollectedData();
        $this->assertCount(1, $data->getFlashes());
    }

    public function testRouteAttribute()
    {
        $requestStack = new RequestStack();
        $c = new RequestDataCollector($requestStack);

        /** @var \Symfony\Component\Routing\Route $route */
        $route = $this->getMockBuilder('Symfony\Component\Routing\Route')
            ->disableOriginalConstructor()
            ->getMock();
        $route->expects($this->once())->method('getPath')->willReturn('/test');

        $request = new Request(
            array(),
            array(),
            array('_route' => $route)
        );
        $requestStack->push($request);

        $c->onKernelResponse(
            new FilterResponseEvent(
                $this->getKernel(), $requestStack->getMasterRequest(), HttpKernelInterface::MASTER_REQUEST, $this->createResponse()
            )
        );
        $data = $c->getCollectedData();
    }

    /**
     * @expectsException \LogicException
     */
    public function testInvalidContent()
    {
        $requestStack = new RequestStack();
        $c = new RequestDataCollector($requestStack);

        $request = new Request(array(), array(), array(), array(), array(), array(), false);
        $requestStack->push($request);

        $c->onKernelResponse(
            new FilterResponseEvent(
                $this->getKernel(), $requestStack->getMasterRequest(), HttpKernelInterface::MASTER_REQUEST, $this->createResponse()
            )
        );
        $data = $c->getCollectedData();
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

    protected function getKernel()
    {
        return $this->getMock('Symfony\Component\HttpKernel\KernelInterface');
    }
}

interface DummyRouteInterface
{
    public function getPath();
}
