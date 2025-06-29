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
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionFactory;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorageFactory;
use Symfony\Component\HttpFoundation\Session\Storage\PhpBridgeSessionStorageFactory;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageFactoryInterface;
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
    /**
     * @dataProvider provideSessionOptions
     *
     * @runInSeparateProcess
     */
    public function testSessionCookieOptions(array $phpSessionOptions, array $sessionOptions, array $expectedSessionOptions)
    {
        $session = $this->createMock(Session::class);
        $session->method('getUsageIndex')->willReturn(0, 1);
        $session->method('getId')->willReturn('123456');
        $session->method('getName')->willReturn('PHPSESSID');
        $session->method('save');
        $session->method('isStarted')->willReturn(true);

        if (isset($phpSessionOptions['samesite'])) {
            ini_set('session.cookie_samesite', $phpSessionOptions['samesite']);
        }
        session_set_cookie_params(0, $phpSessionOptions['path'] ?? null, $phpSessionOptions['domain'] ?? null, $phpSessionOptions['secure'] ?? null, $phpSessionOptions['httponly'] ?? null);

        $listener = new SessionListener(new Container(), false, $sessionOptions);
        $kernel = $this->createMock(HttpKernelInterface::class);

        $request = new Request();
        $listener->onKernelRequest(new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST));

        $request->setSession($session);
        $response = new Response();
        $listener->onKernelResponse(new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response));

        $cookies = $response->headers->getCookies();

        if ($sessionOptions['use_cookies'] ?? true) {
            $this->assertCount(1, $cookies);
            $this->assertSame('PHPSESSID', $cookies[0]->getName());
            $this->assertSame('123456', $cookies[0]->getValue());
            $this->assertSame($expectedSessionOptions['cookie_path'], $cookies[0]->getPath());
            $this->assertSame($expectedSessionOptions['cookie_domain'], $cookies[0]->getDomain());
            $this->assertSame($expectedSessionOptions['cookie_secure'], $cookies[0]->isSecure());
            $this->assertSame($expectedSessionOptions['cookie_httponly'], $cookies[0]->isHttpOnly());
            $this->assertSame($expectedSessionOptions['cookie_samesite'], $cookies[0]->getSameSite());
        } else {
            $this->assertCount(0, $cookies);
        }
    }

    public static function provideSessionOptions(): \Generator
    {
        yield 'set_samesite_by_php' => [
            'phpSessionOptions' => ['samesite' => Cookie::SAMESITE_STRICT],
            'sessionOptions' => ['cookie_path' => '/test/', 'cookie_domain' => '', 'cookie_secure' => true, 'cookie_httponly' => true],
            'expectedSessionOptions' => ['cookie_path' => '/test/', 'cookie_domain' => '', 'cookie_secure' => true, 'cookie_httponly' => true, 'cookie_samesite' => Cookie::SAMESITE_STRICT],
        ];

        yield 'set_cookie_path_by_php' => [
            'phpSessionOptions' => ['path' => '/prod/'],
            'sessionOptions' => ['cookie_domain' => '', 'cookie_secure' => true, 'cookie_httponly' => true, 'cookie_samesite' => Cookie::SAMESITE_LAX],
            'expectedSessionOptions' => ['cookie_path' => '/prod/', 'cookie_domain' => '', 'cookie_secure' => true, 'cookie_httponly' => true, 'cookie_samesite' => Cookie::SAMESITE_LAX],
        ];

        yield 'set_cookie_secure_by_php' => [
            'phpSessionOptions' => ['secure' => true],
            'sessionOptions' => ['cookie_path' => '/test/', 'cookie_domain' => '', 'cookie_httponly' => true, 'cookie_samesite' => Cookie::SAMESITE_LAX],
            'expectedSessionOptions' => ['cookie_path' => '/test/', 'cookie_domain' => '', 'cookie_secure' => true, 'cookie_httponly' => true, 'cookie_samesite' => Cookie::SAMESITE_LAX],
        ];

        yield 'set_cookiesecure_auto_by_symfony_false_by_php' => [
            'phpSessionOptions' => ['secure' => false],
            'sessionOptions' => ['cookie_path' => '/test/', 'cookie_httponly' => true, 'cookie_secure' => 'auto', 'cookie_samesite' => Cookie::SAMESITE_LAX],
            'expectedSessionOptions' => ['cookie_path' => '/test/', 'cookie_domain' => '', 'cookie_secure' => false, 'cookie_httponly' => true, 'cookie_samesite' => Cookie::SAMESITE_LAX],
        ];

        yield 'set_cookiesecure_auto_by_symfony_true_by_php' => [
            'phpSessionOptions' => ['secure' => true],
            'sessionOptions' => ['cookie_path' => '/test/', 'cookie_httponly' => true, 'cookie_secure' => 'auto', 'cookie_samesite' => Cookie::SAMESITE_LAX],
            'expectedSessionOptions' => ['cookie_path' => '/test/', 'cookie_domain' => '', 'cookie_secure' => true, 'cookie_httponly' => true, 'cookie_samesite' => Cookie::SAMESITE_LAX],
        ];

        yield 'set_cookie_httponly_by_php' => [
            'phpSessionOptions' => ['httponly' => true],
            'sessionOptions' => ['cookie_path' => '/test/', 'cookie_domain' => '', 'cookie_secure' => true, 'cookie_samesite' => Cookie::SAMESITE_LAX],
            'expectedSessionOptions' => ['cookie_path' => '/test/', 'cookie_domain' => '', 'cookie_secure' => true, 'cookie_httponly' => true, 'cookie_samesite' => Cookie::SAMESITE_LAX],
        ];

        yield 'set_cookie_domain_by_php' => [
            'phpSessionOptions' => ['domain' => 'test.symfony'],
            'sessionOptions' => ['cookie_path' => '/test/', 'cookie_httponly' => true, 'cookie_secure' => true, 'cookie_samesite' => Cookie::SAMESITE_LAX],
            'expectedSessionOptions' => ['cookie_path' => '/test/', 'cookie_domain' => 'test.symfony', 'cookie_secure' => true, 'cookie_httponly' => true, 'cookie_samesite' => Cookie::SAMESITE_LAX],
        ];

        yield 'set_samesite_by_symfony' => [
            'phpSessionOptions' => ['samesite' => Cookie::SAMESITE_STRICT],
            'sessionOptions' => ['cookie_path' => '/test/', 'cookie_httponly' => true, 'cookie_secure' => true, 'cookie_samesite' => Cookie::SAMESITE_LAX],
            'expectedSessionOptions' => ['cookie_path' => '/test/', 'cookie_domain' => '', 'cookie_secure' => true, 'cookie_httponly' => true, 'cookie_samesite' => Cookie::SAMESITE_LAX],
        ];

        yield 'set_use_cookies_false_by_symfony' => [
            'phpSessionOptions' => [],
            'sessionOptions' => ['use_cookies' => false, 'cookie_domain' => '', 'cookie_secure' => true, 'cookie_httponly' => true, 'cookie_samesite' => Cookie::SAMESITE_LAX],
            'expectedSessionOptions' => [],
        ];
    }

    /**
     * @runInSeparateProcess
     */
    public function testPhpBridgeAlreadyStartedSession()
    {
        session_start();
        $sessionId = session_id();

        $request = new Request();
        $listener = $this->createListener($request, new PhpBridgeSessionStorageFactory());

        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        $listener->onKernelRequest($event);

        $this->assertTrue($request->hasSession());
        $this->assertSame($sessionId, $request->getSession()->getId());
    }

    /**
     * @runInSeparateProcess
     */
    public function testSessionCookieWrittenNoCookieGiven()
    {
        $request = new Request();
        $listener = $this->createListener($request, new NativeSessionStorageFactory());

        $kernel = $this->createMock(HttpKernelInterface::class);

        $listener->onKernelRequest(new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST));
        $session = $request->getSession();
        $session->set('hello', 'world');

        $response = new Response();
        $listener->onKernelResponse(new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response));

        $cookies = $response->headers->getCookies();
        $this->assertCount(1, $cookies);
        $sessionCookie = $cookies[0];

        $this->assertSame('PHPSESSID', $sessionCookie->getName());
        $this->assertNotEmpty($sessionCookie->getValue());
        $this->assertFalse($sessionCookie->isCleared());
    }

    /**
     * @runInSeparateProcess
     */
    public function testSessionCookieNotWrittenCookieGiven()
    {
        $sessionId = $this->createValidSessionId();

        $this->assertNotEmpty($sessionId);

        $request = new Request();
        $request->cookies->set('PHPSESSID', $sessionId);

        $listener = $this->createListener($request, new NativeSessionStorageFactory());

        $kernel = $this->createMock(HttpKernelInterface::class);
        $listener->onKernelRequest(new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST));

        $session = $request->getSession();
        $this->assertSame($sessionId, $session->getId());
        $session->set('hello', 'world');

        $response = new Response();
        $listener->onKernelResponse(new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response));
        $this->assertSame($sessionId, $session->getId());

        $cookies = $response->headers->getCookies();
        $this->assertCount(0, $cookies);
    }

    /**
     * @runInSeparateProcess
     */
    public function testNewSessionIdIsNotOverwritten()
    {
        $newSessionId = $this->createValidSessionId();

        $this->assertNotEmpty($newSessionId);

        $request = new Request();
        $request->cookies->set('PHPSESSID', 'OLD-SESSION-ID');

        $listener = $this->createListener($request, new NativeSessionStorageFactory());

        $kernel = $this->createMock(HttpKernelInterface::class);
        $listener->onKernelRequest(new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST));

        $session = $request->getSession();
        $this->assertSame($newSessionId, $session->getId());
        $session->set('hello', 'world');

        $response = new Response();
        $listener->onKernelResponse(new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response));
        $this->assertSame($newSessionId, $session->getId());

        $cookies = $response->headers->getCookies();

        $this->assertCount(1, $cookies);
        $sessionCookie = $cookies[0];

        $this->assertSame('PHPSESSID', $sessionCookie->getName());
        $this->assertSame($newSessionId, $sessionCookie->getValue());
    }

    /**
     * @runInSeparateProcess
     */
    public function testSessionCookieClearedWhenInvalidated()
    {
        $sessionId = $this->createValidSessionId();
        $request = new Request();
        $request->cookies->set('PHPSESSID', $sessionId);
        $listener = $this->createListener($request, new NativeSessionStorageFactory());
        $kernel = $this->createMock(HttpKernelInterface::class);

        $listener->onKernelRequest(new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST));

        $session = $request->getSession();
        $session->start();
        $sessionId = $session->getId();
        $this->assertNotEmpty($sessionId);
        $_SESSION['hello'] = 'world'; // check compatibility to php session bridge

        $session->invalidate();

        $response = new Response();
        $listener->onKernelResponse(new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response));

        $cookies = $response->headers->getCookies();
        $this->assertCount(1, $cookies);
        $sessionCookie = $cookies[0];

        $this->assertSame('PHPSESSID', $sessionCookie->getName());
        $this->assertTrue($sessionCookie->isCleared());
    }

    /**
     * @runInSeparateProcess
     */
    public function testSessionCookieNotClearedWhenOtherVariablesSet()
    {
        $sessionId = $this->createValidSessionId();
        $request = new Request();
        $request->cookies->set('PHPSESSID', $sessionId);
        $listener = $this->createListener($request, new NativeSessionStorageFactory());
        $kernel = $this->createMock(HttpKernelInterface::class);

        $listener->onKernelRequest(new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST));

        $session = $request->getSession();
        $session->start();
        $sessionId = $session->getId();
        $this->assertNotEmpty($sessionId);
        $_SESSION['hello'] = 'world';

        $response = new Response();
        $listener->onKernelResponse(new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response));

        $cookies = $response->headers->getCookies();
        $this->assertCount(0, $cookies);
    }

    /**
     * @runInSeparateProcess
     */
    public function testSessionCookieSetWhenOtherNativeVariablesSet()
    {
        $request = new Request();
        $listener = $this->createListener($request, new NativeSessionStorageFactory());
        $kernel = $this->createMock(HttpKernelInterface::class);

        $listener->onKernelRequest(new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST));

        $session = $request->getSession();
        $session->start();
        $sessionId = $session->getId();
        $this->assertNotEmpty($sessionId);
        $_SESSION['hello'] = 'world';

        $response = new Response();
        $listener->onKernelResponse(new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response));

        $cookies = $response->headers->getCookies();
        $this->assertCount(1, $cookies);
        $sessionCookie = $cookies[0];

        $this->assertSame('PHPSESSID', $sessionCookie->getName());
        $this->assertNotEmpty($sessionCookie->getValue());
        $this->assertFalse($sessionCookie->isCleared());
    }

    public function testOnlyTriggeredOnMainRequest()
    {
        $listener = new class extends AbstractSessionListener {
            protected function getSession(): ?SessionInterface
            {
                return null;
            }
        };

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

        $sessionStorage = $this->createMock(NativeSessionStorage::class);
        $sessionStorage->expects($this->never())->method('setOptions')->with(['cookie_secure' => true]);

        $container = new Container();
        $container->set('session_factory', $sessionFactory);
        $container->set('request_stack', new RequestStack());

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
        $this->assertLessThanOrEqual(new \DateTimeImmutable('now', new \DateTimeZone('UTC')), new \DateTimeImmutable($response->headers->get('Expires')));
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
        $session->expects($this->exactly(1))->method('getUsageIndex')->willReturn(0);
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

    public function testUninitializedSessionUsingSessionFromRequest()
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $response = new Response();
        $response->setSharedMaxAge(60);
        $response->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, 'true');

        $request = new Request();
        $request->setSession(new Session());

        $listener = new SessionListener(new Container());
        $listener->onKernelResponse(new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response));
        $this->assertFalse($response->headers->has('Expires'));
        $this->assertTrue($response->headers->hasCacheControlDirective('public'));
        $this->assertFalse($response->headers->hasCacheControlDirective('private'));
        $this->assertFalse($response->headers->hasCacheControlDirective('must-revalidate'));
        $this->assertSame('60', $response->headers->getCacheControlDirective('s-maxage'));
        $this->assertFalse($response->headers->has(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER));
    }

    public function testUninitializedSessionWithoutInitializedSession()
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $response = new Response();
        $response->setSharedMaxAge(60);
        $response->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, 'true');

        $container = new ServiceLocator([]);

        $listener = new SessionListener($container);
        $listener->onKernelResponse(new ResponseEvent($kernel, new Request(), HttpKernelInterface::MAIN_REQUEST, $response));
        $this->assertFalse($response->headers->has('Expires'));
        $this->assertTrue($response->headers->hasCacheControlDirective('public'));
        $this->assertFalse($response->headers->hasCacheControlDirective('private'));
        $this->assertFalse($response->headers->hasCacheControlDirective('must-revalidate'));
        $this->assertSame('60', $response->headers->getCacheControlDirective('s-maxage'));
    }

    public function testResponseHeadersMaxAgeAndExpiresNotBeOverriddenIfSessionStarted()
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
        $response->setPrivate();
        $expiresHeader = gmdate('D, d M Y H:i:s', time() + 600).' GMT';
        $response->setMaxAge(600);
        $listener->onKernelResponse(new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response));

        $this->assertTrue($response->headers->has('expires'));
        $this->assertSame($expiresHeader, $response->headers->get('expires'));
        $this->assertFalse($response->headers->has('max-age'));
        $this->assertSame('600', $response->headers->getCacheControlDirective('max-age'));
        $this->assertFalse($response->headers->hasCacheControlDirective('public'));
        $this->assertTrue($response->headers->hasCacheControlDirective('private'));
        $this->assertTrue($response->headers->hasCacheControlDirective('must-revalidate'));
        $this->assertFalse($response->headers->has(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER));
    }

    public function testResponseHeadersMaxAgeAndExpiresDefaultValuesIfSessionStarted()
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
        $expiresHeader = gmdate('D, d M Y H:i:s', time()).' GMT';
        $listener->onKernelResponse(new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response));

        $this->assertTrue($response->headers->has('expires'));
        $this->assertSame($expiresHeader, $response->headers->get('expires'));
        $this->assertFalse($response->headers->has('max-age'));
        $this->assertSame('0', $response->headers->getCacheControlDirective('max-age'));
        $this->assertFalse($response->headers->hasCacheControlDirective('public'));
        $this->assertTrue($response->headers->hasCacheControlDirective('private'));
        $this->assertTrue($response->headers->hasCacheControlDirective('must-revalidate'));
        $this->assertFalse($response->headers->has(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER));
    }

    public function testPrivateResponseMaxAgeIsRespectedIfSessionStarted()
    {
        $kernel = $this->createMock(HttpKernelInterface::class);

        $session = $this->createMock(Session::class);
        $session->expects($this->once())->method('getUsageIndex')->willReturn(1);
        $request = new Request([], [], [], [], [], ['SERVER_PROTOCOL' => 'HTTP/1.0']);
        $request->setSession($session);

        $response = new Response();
        $response->headers->set('Cache-Control', 'no-cache');
        $response->prepare($request);

        $listener = new SessionListener(new Container());
        $listener->onKernelResponse(new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response));

        $this->assertSame(0, $response->getMaxAge());
        $this->assertFalse($response->headers->hasCacheControlDirective('public'));
        $this->assertTrue($response->headers->hasCacheControlDirective('private'));
        $this->assertTrue($response->headers->hasCacheControlDirective('must-revalidate'));
        $this->assertLessThanOrEqual(new \DateTimeImmutable('now', new \DateTimeZone('UTC')), new \DateTimeImmutable($response->headers->get('Expires')));
        $this->assertFalse($response->headers->has(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER));
    }

    public function testSurrogateMainRequestIsPublic()
    {
        $session = $this->createMock(Session::class);
        $session->expects($this->exactly(1))->method('getName')->willReturn('PHPSESSID');
        $session->expects($this->exactly(2))->method('getUsageIndex')->willReturn(0, 1);
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
        $this->assertLessThanOrEqual(new \DateTimeImmutable('now', new \DateTimeZone('UTC')), new \DateTimeImmutable($response->headers->get('Expires')));
    }

    public function testGetSessionIsCalledOnce()
    {
        $session = $this->createMock(Session::class);
        $session->expects($this->exactly(1))->method('getName')->willReturn('PHPSESSID');
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

    public function testGetSessionSetsSessionOnMainRequest()
    {
        $mainRequest = new Request();
        $listener = $this->createListener($mainRequest, new NativeSessionStorageFactory());

        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $mainRequest, HttpKernelInterface::MAIN_REQUEST);
        $listener->onKernelRequest($event);

        $this->assertFalse($mainRequest->hasSession(true));

        $subRequest = $mainRequest->duplicate();

        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $subRequest, HttpKernelInterface::SUB_REQUEST);
        $listener->onKernelRequest($event);

        $session = $subRequest->getSession();

        $this->assertTrue($mainRequest->hasSession(true));
        $this->assertSame($session, $mainRequest->getSession());
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
        $container->set('session_collector', $collector->collectSessionUsage(...));

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

        $this->assertSame([], $_SESSION);
        $this->assertSame('', session_id());
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

        $this->assertSame([], $_SESSION);
        $this->assertSame('', session_id());
        $this->assertSame(\PHP_SESSION_NONE, session_status());
    }

    private function createListener(Request $request, SessionStorageFactoryInterface $sessionFactory)
    {
        $requestStack = new RequestStack();
        $request = new Request();
        $requestStack->push($request);

        $sessionFactory = new SessionFactory($requestStack, $sessionFactory);

        $container = new Container();
        $container->set('request_stack', $requestStack);
        $container->set('session_factory', $sessionFactory);

        $listener = new SessionListener($container);

        return new SessionListener($container);
    }

    private function createValidSessionId(): string
    {
        session_start();
        $sessionId = session_id();
        $_SESSION['some'] = 'value';
        session_write_close();
        $_SESSION = [];
        session_abort();

        return $sessionId;
    }
}
