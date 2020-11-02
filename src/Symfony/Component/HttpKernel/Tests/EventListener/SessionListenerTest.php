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
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;
use Symfony\Component\HttpKernel\DataCollector\RequestDataCollector;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\HttpKernel\EventListener\SessionListener;
use Symfony\Component\HttpKernel\Exception\UnexpectedSessionUsageException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class SessionListenerTest extends TestCase
{
    public function testOnlyTriggeredOnMasterRequest()
    {
        $listener = $this->getMockForAbstractClass(AbstractSessionListener::class);
        $event = $this->getMockBuilder(RequestEvent::class)->disableOriginalConstructor()->getMock();
        $event->expects($this->once())->method('isMasterRequest')->willReturn(false);
        $event->expects($this->never())->method('getRequest');

        // sub request
        $listener->onKernelRequest($event);
    }

    public function testSessionIsSet()
    {
        $session = $this->getMockBuilder(Session::class)->disableOriginalConstructor()->getMock();

        $requestStack = $this->getMockBuilder(RequestStack::class)->getMock();
        $requestStack->expects($this->once())->method('getMasterRequest')->willReturn(null);

        $sessionStorage = $this->getMockBuilder(NativeSessionStorage::class)->getMock();
        $sessionStorage->expects($this->never())->method('setOptions')->with(['cookie_secure' => true]);

        $container = new Container();
        $container->set('session', $session);
        $container->set('request_stack', $requestStack);
        $container->set('session_storage', $sessionStorage);

        $request = new Request();
        $listener = new SessionListener($container);

        $event = $this->getMockBuilder(RequestEvent::class)->disableOriginalConstructor()->getMock();
        $event->expects($this->once())->method('isMasterRequest')->willReturn(true);
        $event->expects($this->once())->method('getRequest')->willReturn($request);

        $listener->onKernelRequest($event);

        $this->assertTrue($request->hasSession());
        $this->assertSame($session, $request->getSession());
    }

    public function testResponseIsPrivateIfSessionStarted()
    {
        $session = $this->getMockBuilder(Session::class)->disableOriginalConstructor()->getMock();
        $session->expects($this->exactly(2))->method('getUsageIndex')->will($this->onConsecutiveCalls(0, 1));

        $container = new Container();
        $container->set('initialized_session', $session);

        $listener = new SessionListener($container);
        $kernel = $this->getMockBuilder(HttpKernelInterface::class)->disableOriginalConstructor()->getMock();

        $request = new Request();
        $listener->onKernelRequest(new RequestEvent($kernel, $request, HttpKernelInterface::MASTER_REQUEST));

        $response = new Response();
        $listener->onKernelResponse(new ResponseEvent($kernel, new Request(), HttpKernelInterface::MASTER_REQUEST, $response));

        $this->assertTrue($response->headers->has('Expires'));
        $this->assertTrue($response->headers->hasCacheControlDirective('private'));
        $this->assertTrue($response->headers->hasCacheControlDirective('must-revalidate'));
        $this->assertSame('0', $response->headers->getCacheControlDirective('max-age'));
        $this->assertLessThanOrEqual((new \DateTime('now', new \DateTimeZone('UTC'))), (new \DateTime($response->headers->get('Expires'))));
        $this->assertFalse($response->headers->has(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER));
    }

    public function testResponseIsStillPublicIfSessionStartedAndHeaderPresent()
    {
        $session = $this->getMockBuilder(Session::class)->disableOriginalConstructor()->getMock();
        $session->expects($this->exactly(2))->method('getUsageIndex')->will($this->onConsecutiveCalls(0, 1));

        $container = new Container();
        $container->set('initialized_session', $session);

        $listener = new SessionListener($container);
        $kernel = $this->getMockBuilder(HttpKernelInterface::class)->disableOriginalConstructor()->getMock();

        $request = new Request();
        $listener->onKernelRequest(new RequestEvent($kernel, $request, HttpKernelInterface::MASTER_REQUEST));

        $response = new Response();
        $response->setSharedMaxAge(60);
        $response->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, 'true');
        $listener->onKernelResponse(new ResponseEvent($kernel, new Request(), HttpKernelInterface::MASTER_REQUEST, $response));

        $this->assertTrue($response->headers->hasCacheControlDirective('public'));
        $this->assertFalse($response->headers->has('Expires'));
        $this->assertFalse($response->headers->hasCacheControlDirective('private'));
        $this->assertFalse($response->headers->hasCacheControlDirective('must-revalidate'));
        $this->assertSame('60', $response->headers->getCacheControlDirective('s-maxage'));
        $this->assertFalse($response->headers->has(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER));
    }

    public function testUninitializedSession()
    {
        $kernel = $this->getMockBuilder(HttpKernelInterface::class)->disableOriginalConstructor()->getMock();
        $response = new Response();
        $response->setSharedMaxAge(60);
        $response->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, 'true');

        $container = new ServiceLocator([
            'initialized_session' => function () {},
        ]);

        $listener = new SessionListener($container);
        $listener->onKernelResponse(new ResponseEvent($kernel, new Request(), HttpKernelInterface::MASTER_REQUEST, $response));
        $this->assertFalse($response->headers->has('Expires'));
        $this->assertTrue($response->headers->hasCacheControlDirective('public'));
        $this->assertFalse($response->headers->hasCacheControlDirective('private'));
        $this->assertFalse($response->headers->hasCacheControlDirective('must-revalidate'));
        $this->assertSame('60', $response->headers->getCacheControlDirective('s-maxage'));
        $this->assertFalse($response->headers->has(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER));
    }

    public function testSurrogateMasterRequestIsPublic()
    {
        $session = $this->getMockBuilder(Session::class)->disableOriginalConstructor()->getMock();
        $session->expects($this->exactly(4))->method('getUsageIndex')->will($this->onConsecutiveCalls(0, 1, 1, 1));

        $container = new Container();
        $container->set('initialized_session', $session);
        $container->set('session', $session);

        $listener = new SessionListener($container);
        $kernel = $this->getMockBuilder(HttpKernelInterface::class)->disableOriginalConstructor()->getMock();

        $request = new Request();
        $response = new Response();
        $response->setCache(['public' => true, 'max_age' => '30']);
        $listener->onKernelRequest(new RequestEvent($kernel, $request, HttpKernelInterface::MASTER_REQUEST));
        $this->assertTrue($request->hasSession());

        $subRequest = clone $request;
        $this->assertSame($request->getSession(), $subRequest->getSession());
        $listener->onKernelRequest(new RequestEvent($kernel, $subRequest, HttpKernelInterface::MASTER_REQUEST));
        $listener->onKernelResponse(new ResponseEvent($kernel, $subRequest, HttpKernelInterface::MASTER_REQUEST, $response));
        $listener->onFinishRequest(new FinishRequestEvent($kernel, $subRequest, HttpKernelInterface::MASTER_REQUEST));

        $this->assertFalse($response->headers->has('Expires'));
        $this->assertFalse($response->headers->hasCacheControlDirective('private'));
        $this->assertFalse($response->headers->hasCacheControlDirective('must-revalidate'));
        $this->assertSame('30', $response->headers->getCacheControlDirective('max-age'));

        $listener->onKernelResponse(new ResponseEvent($kernel, $request, HttpKernelInterface::MASTER_REQUEST, $response));

        $this->assertTrue($response->headers->hasCacheControlDirective('private'));
        $this->assertTrue($response->headers->hasCacheControlDirective('must-revalidate'));
        $this->assertSame('0', $response->headers->getCacheControlDirective('max-age'));

        $this->assertTrue($response->headers->has('Expires'));
        $this->assertLessThanOrEqual((new \DateTime('now', new \DateTimeZone('UTC'))), (new \DateTime($response->headers->get('Expires'))));
    }

    public function testGetSessionIsCalledOnce()
    {
        $session = $this->getMockBuilder(Session::class)->disableOriginalConstructor()->getMock();
        $sessionStorage = $this->getMockBuilder(NativeSessionStorage::class)->getMock();
        $kernel = $this->getMockBuilder(KernelInterface::class)->getMock();

        $sessionStorage->expects($this->once())
            ->method('setOptions')
            ->with(['cookie_secure' => true]);

        $requestStack = new RequestStack();
        $requestStack->push($masterRequest = new Request([], [], [], [], [], ['HTTPS' => 'on']));

        $container = new Container();
        $container->set('session_storage', $sessionStorage);
        $container->set('session', $session);
        $container->set('request_stack', $requestStack);

        $event = new RequestEvent($kernel, $masterRequest, HttpKernelInterface::MASTER_REQUEST);

        $listener = new SessionListener($container);
        $listener->onKernelRequest($event);

        $subRequest = $masterRequest->duplicate();
        // at this point both master and subrequest have a closure to build the session

        $masterRequest->getSession();

        // calling the factory on the subRequest should not trigger a second call to storage->sesOptions()
        $subRequest->getSession();
    }

    public function testSessionUsageExceptionIfStatelessAndSessionUsed()
    {
        $session = $this->getMockBuilder(Session::class)->disableOriginalConstructor()->getMock();
        $session->expects($this->exactly(2))->method('getUsageIndex')->will($this->onConsecutiveCalls(0, 1));

        $container = new Container();
        $container->set('initialized_session', $session);

        $listener = new SessionListener($container, true);
        $kernel = $this->getMockBuilder(HttpKernelInterface::class)->disableOriginalConstructor()->getMock();

        $request = new Request();
        $request->attributes->set('_stateless', true);
        $listener->onKernelRequest(new RequestEvent($kernel, $request, HttpKernelInterface::MASTER_REQUEST));

        $this->expectException(UnexpectedSessionUsageException::class);
        $listener->onKernelResponse(new ResponseEvent($kernel, $request, HttpKernelInterface::MASTER_REQUEST, new Response()));
    }

    public function testSessionUsageLogIfStatelessAndSessionUsed()
    {
        $session = $this->getMockBuilder(Session::class)->disableOriginalConstructor()->getMock();
        $session->expects($this->exactly(2))->method('getUsageIndex')->will($this->onConsecutiveCalls(0, 1));

        $logger = $this->getMockBuilder(LoggerInterface::class)->disableOriginalConstructor()->getMock();
        $logger->expects($this->exactly(1))->method('warning');

        $container = new Container();
        $container->set('initialized_session', $session);
        $container->set('logger', $logger);

        $listener = new SessionListener($container, false);
        $kernel = $this->getMockBuilder(HttpKernelInterface::class)->disableOriginalConstructor()->getMock();

        $request = new Request();
        $request->attributes->set('_stateless', true);
        $listener->onKernelRequest(new RequestEvent($kernel, $request, HttpKernelInterface::MASTER_REQUEST));

        $listener->onKernelResponse(new ResponseEvent($kernel, $request, HttpKernelInterface::MASTER_REQUEST, new Response()));
    }

    public function testSessionIsSavedWhenUnexpectedSessionExceptionThrown()
    {
        $session = $this->getMockBuilder(Session::class)->disableOriginalConstructor()->getMock();
        $session->method('isStarted')->willReturn(true);
        $session->expects($this->exactly(2))->method('getUsageIndex')->will($this->onConsecutiveCalls(0, 1));
        $session->expects($this->exactly(1))->method('save');

        $container = new Container();
        $container->set('initialized_session', $session);

        $listener = new SessionListener($container, true);
        $kernel = $this->getMockBuilder(HttpKernelInterface::class)->disableOriginalConstructor()->getMock();

        $request = new Request();
        $request->attributes->set('_stateless', true);

        $listener->onKernelRequest(new RequestEvent($kernel, $request, HttpKernelInterface::MASTER_REQUEST));

        $response = new Response();
        $this->expectException(UnexpectedSessionUsageException::class);
        $listener->onKernelResponse(new ResponseEvent($kernel, $request, HttpKernelInterface::MASTER_REQUEST, $response));
    }

    public function testSessionUsageCallbackWhenDebugAndStateless()
    {
        $session = $this->getMockBuilder(Session::class)->disableOriginalConstructor()->getMock();
        $session->method('isStarted')->willReturn(true);
        $session->expects($this->exactly(1))->method('save');

        $requestStack = new RequestStack();

        $request = new Request();
        $request->attributes->set('_stateless', true);

        $requestStack->push(new Request());
        $requestStack->push($request);
        $requestStack->push(new Request());

        $collector = $this->createMock(RequestDataCollector::class);
        $collector->expects($this->once())->method('collectSessionUsage');

        $container = new Container();
        $container->set('initialized_session', $session);
        $container->set('request_stack', $requestStack);
        $container->set('session_collector', \Closure::fromCallable([$collector, 'collectSessionUsage']));

        $this->expectException(UnexpectedSessionUsageException::class);
        (new SessionListener($container, true))->onSessionUsage();
    }

    public function testSessionUsageCallbackWhenNoDebug()
    {
        $session = $this->getMockBuilder(Session::class)->disableOriginalConstructor()->getMock();
        $session->method('isStarted')->willReturn(true);
        $session->expects($this->exactly(0))->method('save');

        $request = new Request();
        $request->attributes->set('_stateless', true);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $collector = $this->createMock(RequestDataCollector::class);
        $collector->expects($this->never())->method('collectSessionUsage');

        $container = new Container();
        $container->set('initialized_session', $session);
        $container->set('request_stack', $requestStack);
        $container->set('session_collector', $collector);

        (new SessionListener($container))->onSessionUsage();
    }

    public function testSessionUsageCallbackWhenNoStateless()
    {
        $session = $this->getMockBuilder(Session::class)->disableOriginalConstructor()->getMock();
        $session->method('isStarted')->willReturn(true);
        $session->expects($this->never())->method('save');

        $requestStack = new RequestStack();
        $requestStack->push(new Request());
        $requestStack->push(new Request());

        $container = new Container();
        $container->set('initialized_session', $session);
        $container->set('request_stack', $requestStack);

        (new SessionListener($container, true))->onSessionUsage();
    }
}
