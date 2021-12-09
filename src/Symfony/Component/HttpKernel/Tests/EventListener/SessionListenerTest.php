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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionFactory;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;
use Symfony\Component\HttpKernel\DataCollector\RequestDataCollector;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\HttpKernel\EventListener\SessionListener;
use Symfony\Component\HttpKernel\Exception\UnexpectedSessionUsageException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class SessionListenerTest extends TestCase
{
    public function testOnlyTriggeredOnMainRequest()
    {
        $listener = $this->getMockForAbstractClass(AbstractSessionListener::class);
        $event = $this->createMock(RequestEvent::class);
        $event->expects($this->once())->method('isMainRequest')->willReturn(false);
        $event->expects($this->never())->method('getRequest');

        // sub request
        $listener->onKernelRequest($event);
    }

    public function testSessionIsSet()
    {
        $session = $this->createMock(Session::class);
        $session->expects($this->exactly(1))->method('getName')->willReturn('PHPSESSID');
        $sessionFactory = $this->createMock(SessionFactory::class);
        $sessionFactory->expects($this->once())->method('createSession')->willReturn($session);

        $requestStack = $this->createMock(RequestStack::class);

        $sessionStorage = $this->createMock(NativeSessionStorage::class);
        $sessionStorage->expects($this->never())->method('setOptions')->with(['cookie_secure' => true]);

        $container = new Container();
        $container->set('session_factory', $sessionFactory);
        $container->set('request_stack', $requestStack);

        $request = new Request();
        $listener = new SessionListener($container);

        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        $listener->onKernelRequest($event);

        $this->assertTrue($request->hasSession());
        $this->assertSame($session, $request->getSession());
    }

    public function testSessionUsesFactory()
    {
        $session = $this->createMock(Session::class);
        $session->expects($this->exactly(1))->method('getName')->willReturn('PHPSESSID');
        $sessionFactory = $this->createMock(SessionFactory::class);
        $sessionFactory->expects($this->once())->method('createSession')->willReturn($session);

        $container = new Container();
        $container->set('session_factory', $sessionFactory);

        $request = new Request();
        $listener = new SessionListener($container);

        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        $listener->onKernelRequest($event);

        $this->assertTrue($request->hasSession());
        $this->assertSame($session, $request->getSession());
    }

    public function testUsesFactoryWhenNeeded()
    {
        $session = $this->createMock(Session::class);
        $session->expects($this->once())->method('getName')->willReturn('foo');
        $sessionFactory = $this->createMock(SessionFactory::class);
        $sessionFactory->expects($this->once())->method('createSession')->willReturn($session);

        $container = new Container();
        $container->set('session_factory', $sessionFactory);

        $request = new Request();
        $listener = new SessionListener($container);

        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
        $listener->onKernelRequest($event);

        $request->getSession();

        $event = new ResponseEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, new Response());
        $listener->onKernelResponse($event);
    }

    public function testDontUsesFactoryWhenSessionIsNotUsed()
    {
        $sessionFactory = $this->createMock(SessionFactory::class);
        $sessionFactory->expects($this->never())->method('createSession');

        $container = new Container();
        $container->set('session_factory', $sessionFactory);

        $request = new Request();
        $listener = new SessionListener($container);

        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
        $listener->onKernelRequest($event);

        $event = new ResponseEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, new Response());
        $listener->onKernelResponse($event);
    }

    public function testResponseIsPrivateIfSessionStarted()
    {
        $session = $this->createMock(Session::class);
        $session->expects($this->once())->method('getUsageIndex')->willReturn(1);
        $session->expects($this->once())->method('getName')->willReturn('foo');
        $sessionFactory = $this->createMock(SessionFactory::class);
        $sessionFactory->expects($this->once())->method('createSession')->willReturn($session);

        $container = new Container();
        $container->set('session_factory', $sessionFactory);

        $listener = new SessionListener($container);
        $kernel = $this->createMock(HttpKernelInterface::class);

        $request = new Request();
        $listener->onKernelRequest(new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST));

        $request->getSession();

        $response = new Response();
        $listener->onKernelResponse(new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response));

        $this->assertTrue($response->headers->has('Expires'));
        $this->assertTrue($response->headers->hasCacheControlDirective('private'));
        $this->assertTrue($response->headers->hasCacheControlDirective('must-revalidate'));
        $this->assertSame('0', $response->headers->getCacheControlDirective('max-age'));
        $this->assertLessThanOrEqual((new \DateTime('now', new \DateTimeZone('UTC'))), (new \DateTime($response->headers->get('Expires'))));
        $this->assertFalse($response->headers->has(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER));
    }

    public function testResponseIsStillPublicIfSessionStartedAndHeaderPresent()
    {
        $session = $this->createMock(Session::class);
        $session->expects($this->once())->method('getUsageIndex')->willReturn(1);

        $container = new Container();

        $listener = new SessionListener($container);
        $kernel = $this->createMock(HttpKernelInterface::class);

        $request = new Request();
        $request->setSession($session);
        $listener->onKernelRequest(new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST));

        $response = new Response();
        $response->setSharedMaxAge(60);
        $response->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, 'true');

        $listener->onKernelResponse(new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response));

        $this->assertTrue($response->headers->hasCacheControlDirective('public'));
        $this->assertFalse($response->headers->has('Expires'));
        $this->assertFalse($response->headers->hasCacheControlDirective('private'));
        $this->assertFalse($response->headers->hasCacheControlDirective('must-revalidate'));
        $this->assertSame('60', $response->headers->getCacheControlDirective('s-maxage'));
        $this->assertFalse($response->headers->has(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER));
    }

    public function testSessionSaveAndResponseHasSessionCookie()
    {
        $session = $this->getMockBuilder(Session::class)->disableOriginalConstructor()->getMock();
        $session->expects($this->exactly(1))->method('getUsageIndex')->will($this->onConsecutiveCalls(0, 1));
        $session->expects($this->exactly(1))->method('getId')->willReturn('123456');
        $session->expects($this->exactly(1))->method('getName')->willReturn('PHPSESSID');
        $session->expects($this->exactly(1))->method('save');
        $session->expects($this->exactly(1))->method('isStarted')->willReturn(true);

        $container = new Container();

        $listener = new SessionListener($container);
        $kernel = $this->getMockBuilder(HttpKernelInterface::class)->disableOriginalConstructor()->getMock();

        $request = new Request();
        $request->setSession($session);
        $listener->onKernelRequest(new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST));

        $response = new Response();
        $listener->onKernelResponse(new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response));

        $cookies = $response->headers->getCookies();
        $this->assertCount(1, $cookies);
        $this->assertSame('PHPSESSID', $cookies[0]->getName());
        $this->assertSame('123456', $cookies[0]->getValue());
    }

    public function testUninitializedSession()
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $response = new Response();
        $response->setSharedMaxAge(60);
        $response->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, 'true');

        $container = new Container();

        $listener = new SessionListener($container);
        $listener->onKernelResponse(new ResponseEvent($kernel, new Request(), HttpKernelInterface::MAIN_REQUEST, $response));
        $this->assertFalse($response->headers->has('Expires'));
        $this->assertTrue($response->headers->hasCacheControlDirective('public'));
        $this->assertFalse($response->headers->hasCacheControlDirective('private'));
        $this->assertFalse($response->headers->hasCacheControlDirective('must-revalidate'));
        $this->assertSame('60', $response->headers->getCacheControlDirective('s-maxage'));
        $this->assertFalse($response->headers->has(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER));
    }

    public function testSurrogateMainRequestIsPublic()
    {
        $session = $this->createMock(Session::class);
        $session->expects($this->exactly(2))->method('getName')->willReturn('PHPSESSID');
        $session->expects($this->exactly(2))->method('getUsageIndex')->will($this->onConsecutiveCalls(0, 1));
        $sessionFactory = $this->createMock(SessionFactory::class);
        $sessionFactory->expects($this->once())->method('createSession')->willReturn($session);

        $container = new Container();
        $container->set('session_factory', $sessionFactory);

        $listener = new SessionListener($container);
        $kernel = $this->createMock(HttpKernelInterface::class);

        $request = new Request();
        $response = new Response();
        $response->setCache(['public' => true, 'max_age' => '30']);
        $listener->onKernelRequest(new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST));
        $this->assertTrue($request->hasSession());

        $subRequest = clone $request;
        $this->assertSame($request->getSession(), $subRequest->getSession());
        $listener->onKernelRequest(new RequestEvent($kernel, $subRequest, HttpKernelInterface::MAIN_REQUEST));
        $listener->onKernelResponse(new ResponseEvent($kernel, $subRequest, HttpKernelInterface::MAIN_REQUEST, $response));

        $this->assertFalse($response->headers->has('Expires'));
        $this->assertFalse($response->headers->hasCacheControlDirective('private'));
        $this->assertFalse($response->headers->hasCacheControlDirective('must-revalidate'));
        $this->assertSame('30', $response->headers->getCacheControlDirective('max-age'));

        $listener->onKernelResponse(new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response));

        $this->assertTrue($response->headers->hasCacheControlDirective('private'));
        $this->assertTrue($response->headers->hasCacheControlDirective('must-revalidate'));
        $this->assertSame('0', $response->headers->getCacheControlDirective('max-age'));

        $this->assertTrue($response->headers->has('Expires'));
        $this->assertLessThanOrEqual((new \DateTime('now', new \DateTimeZone('UTC'))), (new \DateTime($response->headers->get('Expires'))));
    }

    public function testGetSessionIsCalledOnce()
    {
        $session = $this->createMock(Session::class);
        $session->expects($this->exactly(2))->method('getName')->willReturn('PHPSESSID');
        $sessionFactory = $this->createMock(SessionFactory::class);
        $sessionFactory->expects($this->once())->method('createSession')->willReturn($session);
        $kernel = $this->createMock(KernelInterface::class);

        $requestStack = new RequestStack();
        $requestStack->push($mainRequest = new Request([], [], [], [], [], ['HTTPS' => 'on']));

        $container = new Container();
        $container->set('session_factory', $sessionFactory);
        $container->set('request_stack', $requestStack);

        $event = new RequestEvent($kernel, $mainRequest, HttpKernelInterface::MAIN_REQUEST);

        $listener = new SessionListener($container);
        $listener->onKernelRequest($event);

        // storage->setOptions() should have been called already
        $subRequest = $mainRequest->duplicate();
        // at this point both main and subrequest have a closure to build the session

        $mainRequest->getSession();

        // calling the factory on the subRequest should not trigger a second call to storage->setOptions()
        $subRequest->getSession();
    }

    public function testSessionUsageExceptionIfStatelessAndSessionUsed()
    {
        $session = $this->createMock(Session::class);
        $session->expects($this->once())->method('getUsageIndex')->willReturn(1);
        $session->expects($this->once())->method('getName')->willReturn('foo');
        $sessionFactory = $this->createMock(SessionFactory::class);
        $sessionFactory->expects($this->once())->method('createSession')->willReturn($session);

        $container = new Container();
        $container->set('session_factory', $sessionFactory);

        $listener = new SessionListener($container, true);
        $kernel = $this->createMock(HttpKernelInterface::class);

        $request = new Request();
        $request->attributes->set('_stateless', true);
        $listener->onKernelRequest(new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST));
        $request->getSession();

        $this->expectException(UnexpectedSessionUsageException::class);
        $listener->onKernelResponse(new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, new Response()));
    }

    public function testSessionUsageLogIfStatelessAndSessionUsed()
    {
        $session = $this->createMock(Session::class);
        $session->expects($this->once())->method('getName')->willReturn('foo');
        $session->expects($this->once())->method('getUsageIndex')->willReturn(1);
        $sessionFactory = $this->createMock(SessionFactory::class);
        $sessionFactory->expects($this->once())->method('createSession')->willReturn($session);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(1))->method('warning');

        $container = new Container();
        $container->set('session_factory', $sessionFactory);
        $container->set('logger', $logger);

        $listener = new SessionListener($container, false);
        $kernel = $this->createMock(HttpKernelInterface::class);

        $request = new Request();
        $request->attributes->set('_stateless', true);
        $listener->onKernelRequest(new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST));
        $request->getSession();

        $listener->onKernelResponse(new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, new Response()));
    }

    public function testSessionIsSavedWhenUnexpectedSessionExceptionThrown()
    {
        $session = $this->createMock(Session::class);
        $session->expects($this->exactly(1))->method('getId')->willReturn('123456');
        $session->expects($this->exactly(1))->method('getName')->willReturn('PHPSESSID');
        $session->method('isStarted')->willReturn(true);
        $session->expects($this->once())->method('getUsageIndex')->willReturn(1);
        $session->expects($this->exactly(1))->method('save');
        $sessionFactory = $this->createMock(SessionFactory::class);
        $sessionFactory->expects($this->once())->method('createSession')->willReturn($session);

        $container = new Container();
        $container->set('session_factory', $sessionFactory);

        $listener = new SessionListener($container, true);
        $kernel = $this->createMock(HttpKernelInterface::class);

        $request = new Request();
        $request->attributes->set('_stateless', true);

        $listener->onKernelRequest(new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST));
        $request->getSession();

        $response = new Response();
        $this->expectException(UnexpectedSessionUsageException::class);
        $listener->onKernelResponse(new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response));
    }

    public function testSessionUsageCallbackWhenDebugAndStateless()
    {
        $session = $this->createMock(Session::class);
        $session->method('isStarted')->willReturn(true);
        $session->expects($this->exactly(1))->method('save');

        $requestStack = new RequestStack();

        $request = new Request();
        $request->attributes->set('_stateless', true);

        $requestStack->push(new Request());
        $requestStack->push($request);
        $requestStack->push($subRequest = new Request());
        $subRequest->setSession($session);

        $collector = $this->createMock(RequestDataCollector::class);
        $collector->expects($this->once())->method('collectSessionUsage');

        $container = new Container();
        $container->set('request_stack', $requestStack);
        $container->set('session_collector', \Closure::fromCallable([$collector, 'collectSessionUsage']));

        $this->expectException(UnexpectedSessionUsageException::class);
        (new SessionListener($container, true))->onSessionUsage();
    }

    public function testSessionUsageCallbackWhenNoDebug()
    {
        $session = $this->createMock(Session::class);
        $session->method('isStarted')->willReturn(true);
        $session->expects($this->exactly(0))->method('save');

        $request = new Request();
        $request->attributes->set('_stateless', true);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $collector = $this->createMock(RequestDataCollector::class);
        $collector->expects($this->never())->method('collectSessionUsage');

        $container = new Container();
        $container->set('request_stack', $requestStack);
        $container->set('session_collector', $collector);

        (new SessionListener($container))->onSessionUsage();
    }

    public function testSessionUsageCallbackWhenNoStateless()
    {
        $session = $this->createMock(Session::class);
        $session->method('isStarted')->willReturn(true);
        $session->expects($this->never())->method('save');

        $requestStack = new RequestStack();
        $requestStack->push(new Request());
        $requestStack->push(new Request());

        $container = new Container();
        $container->set('request_stack', $requestStack);

        (new SessionListener($container, true))->onSessionUsage();
    }

    /**
     * @runInSeparateProcess
     */
    public function testReset()
    {
        session_start();
        $_SESSION['test'] = ['test'];
        session_write_close();

        $this->assertNotEmpty($_SESSION);
        $this->assertNotEmpty(session_id());

        $container = new Container();

        (new SessionListener($container, true))->reset();

        $this->assertEmpty($_SESSION);
        $this->assertEmpty(session_id());
        $this->assertSame(\PHP_SESSION_NONE, session_status());
    }

    /**
     * @runInSeparateProcess
     */
    public function testResetUnclosedSession()
    {
        session_start();
        $_SESSION['test'] = ['test'];

        $this->assertNotEmpty($_SESSION);
        $this->assertNotEmpty(session_id());
        $this->assertSame(\PHP_SESSION_ACTIVE, session_status());

        $container = new Container();

        (new SessionListener($container, true))->reset();

        $this->assertEmpty($_SESSION);
        $this->assertEmpty(session_id());
        $this->assertSame(\PHP_SESSION_NONE, session_status());
    }
}
