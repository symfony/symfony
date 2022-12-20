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
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorageFactory;
use Symfony\Component\HttpFoundation\Session\Storage\PhpBridgeSessionStorageFactory;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageFactoryInterface;
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
    /**
     * @dataProvider provideSessionOptions
     *
     * @runInSeparateProcess
     */
    public function testSessionCookieOptions(array $phpSessionOptions, array $sessionOptions, array $expectedSessionOptions)
    {
        $session = self::createMock(Session::class);
        $session->method('getUsageIndex')->will(self::onConsecutiveCalls(0, 1));
        $session->method('getId')->willReturn('123456');
        $session->method('getName')->willReturn('PHPSESSID');
        $session->method('save');
        $session->method('isStarted')->willReturn(true);

        if (isset($phpSessionOptions['samesite'])) {
            ini_set('session.cookie_samesite', $phpSessionOptions['samesite']);
        }
        session_set_cookie_params(0, $phpSessionOptions['path'] ?? null, $phpSessionOptions['domain'] ?? null, $phpSessionOptions['secure'] ?? null, $phpSessionOptions['httponly'] ?? null);

        $listener = new SessionListener(new Container(), false, $sessionOptions);
        $kernel = self::createMock(HttpKernelInterface::class);

        $request = new Request();
        $listener->onKernelRequest(new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST));

        $request->setSession($session);
        $response = new Response();
        $listener->onKernelResponse(new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response));

        $cookies = $response->headers->getCookies();

        if ($sessionOptions['use_cookies'] ?? true) {
            self::assertCount(1, $cookies);
            self::assertSame('PHPSESSID', $cookies[0]->getName());
            self::assertSame('123456', $cookies[0]->getValue());
            self::assertSame($expectedSessionOptions['cookie_path'], $cookies[0]->getPath());
            self::assertSame($expectedSessionOptions['cookie_domain'], $cookies[0]->getDomain());
            self::assertSame($expectedSessionOptions['cookie_secure'], $cookies[0]->isSecure());
            self::assertSame($expectedSessionOptions['cookie_httponly'], $cookies[0]->isHttpOnly());
            self::assertSame($expectedSessionOptions['cookie_samesite'], $cookies[0]->getSameSite());
        } else {
            self::assertCount(0, $cookies);
        }
    }

    public function provideSessionOptions(): \Generator
    {
        if (\PHP_VERSION_ID > 70300) {
            yield 'set_samesite_by_php' => [
                'phpSessionOptions' => ['samesite' => Cookie::SAMESITE_STRICT],
                'sessionOptions' => ['cookie_path' => '/test/', 'cookie_domain' => '', 'cookie_secure' => true, 'cookie_httponly' => true],
                'expectedSessionOptions' => ['cookie_path' => '/test/', 'cookie_domain' => '', 'cookie_secure' => true, 'cookie_httponly' => true, 'cookie_samesite' => Cookie::SAMESITE_STRICT],
            ];
        }

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
            'sessionOptions' => ['cookie_path' => '/test/', 'cookie_httponly' => 'auto', 'cookie_secure' => 'auto', 'cookie_samesite' => Cookie::SAMESITE_LAX],
            'expectedSessionOptions' => ['cookie_path' => '/test/', 'cookie_domain' => '', 'cookie_secure' => false, 'cookie_httponly' => true, 'cookie_samesite' => Cookie::SAMESITE_LAX],
        ];

        yield 'set_cookiesecure_auto_by_symfony_true_by_php' => [
            'phpSessionOptions' => ['secure' => true],
            'sessionOptions' => ['cookie_path' => '/test/', 'cookie_httponly' => 'auto', 'cookie_secure' => 'auto', 'cookie_samesite' => Cookie::SAMESITE_LAX],
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

        $event = new RequestEvent(self::createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        $listener->onKernelRequest($event);

        self::assertTrue($request->hasSession());
        self::assertSame($sessionId, $request->getSession()->getId());
    }

    /**
     * @runInSeparateProcess
     */
    public function testSessionCookieWrittenNoCookieGiven()
    {
        $request = new Request();
        $listener = $this->createListener($request, new NativeSessionStorageFactory());

        $kernel = self::createMock(HttpKernelInterface::class);

        $listener->onKernelRequest(new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST));
        $session = $request->getSession();
        $session->set('hello', 'world');

        $response = new Response();
        $listener->onKernelResponse(new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response));

        $cookies = $response->headers->getCookies();
        self::assertCount(1, $cookies);
        $sessionCookie = $cookies[0];

        self::assertSame('PHPSESSID', $sessionCookie->getName());
        self::assertNotEmpty($sessionCookie->getValue());
        self::assertFalse($sessionCookie->isCleared());
    }

    /**
     * @runInSeparateProcess
     */
    public function testSessionCookieNotWrittenCookieGiven()
    {
        $sessionId = $this->createValidSessionId();

        self::assertNotEmpty($sessionId);

        $request = new Request();
        $request->cookies->set('PHPSESSID', $sessionId);

        $listener = $this->createListener($request, new NativeSessionStorageFactory());

        $kernel = self::createMock(HttpKernelInterface::class);
        $listener->onKernelRequest(new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST));

        $session = $request->getSession();
        self::assertSame($sessionId, $session->getId());
        $session->set('hello', 'world');

        $response = new Response();
        $listener->onKernelResponse(new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response));
        self::assertSame($sessionId, $session->getId());

        $cookies = $response->headers->getCookies();
        self::assertCount(0, $cookies);
    }

    /**
     * @runInSeparateProcess
     */
    public function testNewSessionIdIsNotOverwritten()
    {
        $newSessionId = $this->createValidSessionId();

        self::assertNotEmpty($newSessionId);

        $request = new Request();
        $request->cookies->set('PHPSESSID', 'OLD-SESSION-ID');

        $listener = $this->createListener($request, new NativeSessionStorageFactory());

        $kernel = self::createMock(HttpKernelInterface::class);
        $listener->onKernelRequest(new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST));

        $session = $request->getSession();
        self::assertSame($newSessionId, $session->getId());
        $session->set('hello', 'world');

        $response = new Response();
        $listener->onKernelResponse(new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response));
        self::assertSame($newSessionId, $session->getId());

        $cookies = $response->headers->getCookies();

        self::assertCount(1, $cookies);
        $sessionCookie = $cookies[0];

        self::assertSame('PHPSESSID', $sessionCookie->getName());
        self::assertSame($newSessionId, $sessionCookie->getValue());
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
        $kernel = self::createMock(HttpKernelInterface::class);

        $listener->onKernelRequest(new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST));

        $session = $request->getSession();
        $session->start();
        $sessionId = $session->getId();
        self::assertNotEmpty($sessionId);
        $_SESSION['hello'] = 'world'; // check compatibility to php session bridge

        $session->invalidate();

        $response = new Response();
        $listener->onKernelResponse(new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response));

        $cookies = $response->headers->getCookies();
        self::assertCount(1, $cookies);
        $sessionCookie = $cookies[0];

        self::assertSame('PHPSESSID', $sessionCookie->getName());
        self::assertTrue($sessionCookie->isCleared());
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
        $kernel = self::createMock(HttpKernelInterface::class);

        $listener->onKernelRequest(new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST));

        $session = $request->getSession();
        $session->start();
        $sessionId = $session->getId();
        self::assertNotEmpty($sessionId);
        $_SESSION['hello'] = 'world';

        $response = new Response();
        $listener->onKernelResponse(new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response));

        $cookies = $response->headers->getCookies();
        self::assertCount(0, $cookies);
    }

    /**
     * @runInSeparateProcess
     */
    public function testSessionCookieSetWhenOtherNativeVariablesSet()
    {
        $request = new Request();
        $listener = $this->createListener($request, new NativeSessionStorageFactory());
        $kernel = self::createMock(HttpKernelInterface::class);

        $listener->onKernelRequest(new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST));

        $session = $request->getSession();
        $session->start();
        $sessionId = $session->getId();
        self::assertNotEmpty($sessionId);
        $_SESSION['hello'] = 'world';

        $response = new Response();
        $listener->onKernelResponse(new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response));

        $cookies = $response->headers->getCookies();
        self::assertCount(1, $cookies);
        $sessionCookie = $cookies[0];

        self::assertSame('PHPSESSID', $sessionCookie->getName());
        self::assertNotEmpty($sessionCookie->getValue());
        self::assertFalse($sessionCookie->isCleared());
    }

    public function testOnlyTriggeredOnMainRequest()
    {
        $listener = self::getMockForAbstractClass(AbstractSessionListener::class);
        $event = self::createMock(RequestEvent::class);
        $event->expects(self::once())->method('isMainRequest')->willReturn(false);
        $event->expects(self::never())->method('getRequest');

        // sub request
        $listener->onKernelRequest($event);
    }

    public function testSessionIsSet()
    {
        $session = self::createMock(Session::class);
        $session->expects(self::exactly(1))->method('getName')->willReturn('PHPSESSID');

        $requestStack = self::createMock(RequestStack::class);
        $requestStack->expects(self::once())->method('getMainRequest')->willReturn(null);

        $sessionStorage = self::createMock(NativeSessionStorage::class);
        $sessionStorage->expects(self::never())->method('setOptions')->with(['cookie_secure' => true]);

        $container = new Container();
        $container->set('session', $session);
        $container->set('request_stack', $requestStack);
        $container->set('session_storage', $sessionStorage);

        $request = new Request();
        $listener = new SessionListener($container);

        $event = new RequestEvent(self::createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        $listener->onKernelRequest($event);

        self::assertTrue($request->hasSession());
        self::assertSame($session, $request->getSession());
    }

    public function testSessionUsesFactory()
    {
        $session = self::createMock(Session::class);
        $session->expects(self::exactly(1))->method('getName')->willReturn('PHPSESSID');
        $sessionFactory = self::createMock(SessionFactory::class);
        $sessionFactory->expects(self::once())->method('createSession')->willReturn($session);

        $container = new Container();
        $container->set('session_factory', $sessionFactory);

        $request = new Request();
        $listener = new SessionListener($container);

        $event = new RequestEvent(self::createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        $listener->onKernelRequest($event);

        self::assertTrue($request->hasSession());
        self::assertSame($session, $request->getSession());
    }

    public function testResponseIsPrivateIfSessionStarted()
    {
        $session = self::createMock(Session::class);
        $session->expects(self::exactly(2))->method('getUsageIndex')->will(self::onConsecutiveCalls(0, 1));

        $container = new Container();
        $container->set('initialized_session', $session);

        $listener = new SessionListener($container);
        $kernel = self::createMock(HttpKernelInterface::class);

        $request = new Request();
        $listener->onKernelRequest(new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST));

        $response = new Response();
        $listener->onKernelResponse(new ResponseEvent($kernel, new Request(), HttpKernelInterface::MAIN_REQUEST, $response));

        self::assertTrue($response->headers->has('Expires'));
        self::assertTrue($response->headers->hasCacheControlDirective('private'));
        self::assertTrue($response->headers->hasCacheControlDirective('must-revalidate'));
        self::assertSame('0', $response->headers->getCacheControlDirective('max-age'));
        self::assertLessThanOrEqual(new \DateTime('now', new \DateTimeZone('UTC')), new \DateTime($response->headers->get('Expires')));
        self::assertFalse($response->headers->has(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER));
    }

    public function testResponseIsStillPublicIfSessionStartedAndHeaderPresent()
    {
        $session = self::createMock(Session::class);
        $session->expects(self::exactly(2))->method('getUsageIndex')->will(self::onConsecutiveCalls(0, 1));

        $container = new Container();
        $container->set('initialized_session', $session);

        $listener = new SessionListener($container);
        $kernel = self::createMock(HttpKernelInterface::class);

        $request = new Request();
        $listener->onKernelRequest(new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST));

        $response = new Response();
        $response->setSharedMaxAge(60);
        $response->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, 'true');
        $listener->onKernelResponse(new ResponseEvent($kernel, new Request(), HttpKernelInterface::MAIN_REQUEST, $response));

        self::assertTrue($response->headers->hasCacheControlDirective('public'));
        self::assertFalse($response->headers->has('Expires'));
        self::assertFalse($response->headers->hasCacheControlDirective('private'));
        self::assertFalse($response->headers->hasCacheControlDirective('must-revalidate'));
        self::assertSame('60', $response->headers->getCacheControlDirective('s-maxage'));
        self::assertFalse($response->headers->has(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER));
    }

    public function testSessionSaveAndResponseHasSessionCookie()
    {
        $session = self::getMockBuilder(Session::class)->disableOriginalConstructor()->getMock();
        $session->expects(self::exactly(2))->method('getUsageIndex')->will(self::onConsecutiveCalls(0, 1));
        $session->expects(self::exactly(1))->method('getId')->willReturn('123456');
        $session->expects(self::exactly(1))->method('getName')->willReturn('PHPSESSID');
        $session->expects(self::exactly(1))->method('save');
        $session->expects(self::exactly(1))->method('isStarted')->willReturn(true);

        $container = new Container();
        $container->set('initialized_session', $session);

        $listener = new SessionListener($container);
        $kernel = self::getMockBuilder(HttpKernelInterface::class)->disableOriginalConstructor()->getMock();

        $request = new Request();
        $listener->onKernelRequest(new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST));

        $response = new Response();
        $listener->onKernelResponse(new ResponseEvent($kernel, new Request(), HttpKernelInterface::MAIN_REQUEST, $response));

        $cookies = $response->headers->getCookies();
        self::assertSame('PHPSESSID', $cookies[0]->getName());
        self::assertSame('123456', $cookies[0]->getValue());
    }

    public function testUninitializedSessionUsingInitializedSessionService()
    {
        $kernel = self::createMock(HttpKernelInterface::class);
        $response = new Response();
        $response->setSharedMaxAge(60);
        $response->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, 'true');

        $container = new ServiceLocator([
            'initialized_session' => function () {},
        ]);

        $listener = new SessionListener($container);
        $listener->onKernelResponse(new ResponseEvent($kernel, new Request(), HttpKernelInterface::MAIN_REQUEST, $response));
        self::assertFalse($response->headers->has('Expires'));
        self::assertTrue($response->headers->hasCacheControlDirective('public'));
        self::assertFalse($response->headers->hasCacheControlDirective('private'));
        self::assertFalse($response->headers->hasCacheControlDirective('must-revalidate'));
        self::assertSame('60', $response->headers->getCacheControlDirective('s-maxage'));
        self::assertFalse($response->headers->has(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER));
    }

    public function testUninitializedSessionUsingSessionFromRequest()
    {
        $kernel = self::createMock(HttpKernelInterface::class);
        $response = new Response();
        $response->setSharedMaxAge(60);
        $response->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, 'true');

        $request = new Request();
        $request->setSession(new Session());

        $listener = new SessionListener(new Container());
        $listener->onKernelResponse(new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response));
        self::assertFalse($response->headers->has('Expires'));
        self::assertTrue($response->headers->hasCacheControlDirective('public'));
        self::assertFalse($response->headers->hasCacheControlDirective('private'));
        self::assertFalse($response->headers->hasCacheControlDirective('must-revalidate'));
        self::assertSame('60', $response->headers->getCacheControlDirective('s-maxage'));
        self::assertFalse($response->headers->has(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER));
    }

    public function testUninitializedSessionWithoutInitializedSession()
    {
        $kernel = self::createMock(HttpKernelInterface::class);
        $response = new Response();
        $response->setSharedMaxAge(60);
        $response->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, 'true');

        $container = new ServiceLocator([]);

        $listener = new SessionListener($container);
        $listener->onKernelResponse(new ResponseEvent($kernel, new Request(), HttpKernelInterface::MAIN_REQUEST, $response));
        self::assertFalse($response->headers->has('Expires'));
        self::assertTrue($response->headers->hasCacheControlDirective('public'));
        self::assertFalse($response->headers->hasCacheControlDirective('private'));
        self::assertFalse($response->headers->hasCacheControlDirective('must-revalidate'));
        self::assertSame('60', $response->headers->getCacheControlDirective('s-maxage'));
    }

    public function testResponseHeadersMaxAgeAndExpiresNotBeOverridenIfSessionStarted()
    {
        $session = self::createMock(Session::class);
        $session->expects(self::exactly(2))->method('getUsageIndex')->will(self::onConsecutiveCalls(0, 1));

        $container = new Container();
        $container->set('initialized_session', $session);

        $listener = new SessionListener($container);
        $kernel = self::createMock(HttpKernelInterface::class);

        $request = new Request();
        $listener->onKernelRequest(new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST));

        $response = new Response();
        $response->setPrivate();
        $expiresHeader = gmdate('D, d M Y H:i:s', time() + 600).' GMT';
        $response->setMaxAge(600);
        $listener->onKernelResponse(new ResponseEvent($kernel, new Request(), HttpKernelInterface::MAIN_REQUEST, $response));

        self::assertTrue($response->headers->has('expires'));
        self::assertSame($expiresHeader, $response->headers->get('expires'));
        self::assertFalse($response->headers->has('max-age'));
        self::assertSame('600', $response->headers->getCacheControlDirective('max-age'));
        self::assertFalse($response->headers->hasCacheControlDirective('public'));
        self::assertTrue($response->headers->hasCacheControlDirective('private'));
        self::assertTrue($response->headers->hasCacheControlDirective('must-revalidate'));
        self::assertFalse($response->headers->has(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER));
    }

    public function testResponseHeadersMaxAgeAndExpiresDefaultValuesIfSessionStarted()
    {
        $session = self::createMock(Session::class);
        $session->expects(self::exactly(2))->method('getUsageIndex')->will(self::onConsecutiveCalls(0, 1));

        $container = new Container();
        $container->set('initialized_session', $session);

        $listener = new SessionListener($container);
        $kernel = self::createMock(HttpKernelInterface::class);

        $request = new Request();
        $listener->onKernelRequest(new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST));

        $response = new Response();
        $expiresHeader = gmdate('D, d M Y H:i:s', time()).' GMT';
        $listener->onKernelResponse(new ResponseEvent($kernel, new Request(), HttpKernelInterface::MAIN_REQUEST, $response));

        self::assertTrue($response->headers->has('expires'));
        self::assertSame($expiresHeader, $response->headers->get('expires'));
        self::assertFalse($response->headers->has('max-age'));
        self::assertSame('0', $response->headers->getCacheControlDirective('max-age'));
        self::assertFalse($response->headers->hasCacheControlDirective('public'));
        self::assertTrue($response->headers->hasCacheControlDirective('private'));
        self::assertTrue($response->headers->hasCacheControlDirective('must-revalidate'));
        self::assertFalse($response->headers->has(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER));
    }

    public function testSurrogateMainRequestIsPublic()
    {
        $session = self::createMock(Session::class);
        $session->expects(self::exactly(1))->method('getName')->willReturn('PHPSESSID');
        $session->expects(self::exactly(4))->method('getUsageIndex')->will(self::onConsecutiveCalls(0, 1, 1, 1));

        $container = new Container();
        $container->set('initialized_session', $session);
        $container->set('session', $session);

        $listener = new SessionListener($container);
        $kernel = self::createMock(HttpKernelInterface::class);

        $request = new Request();
        $response = new Response();
        $response->setCache(['public' => true, 'max_age' => '30']);
        $listener->onKernelRequest(new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST));
        self::assertTrue($request->hasSession());

        $subRequest = clone $request;
        self::assertSame($request->getSession(), $subRequest->getSession());
        $listener->onKernelRequest(new RequestEvent($kernel, $subRequest, HttpKernelInterface::MAIN_REQUEST));
        $listener->onKernelResponse(new ResponseEvent($kernel, $subRequest, HttpKernelInterface::MAIN_REQUEST, $response));
        $listener->onFinishRequest(new FinishRequestEvent($kernel, $subRequest, HttpKernelInterface::MAIN_REQUEST));

        self::assertFalse($response->headers->has('Expires'));
        self::assertFalse($response->headers->hasCacheControlDirective('private'));
        self::assertFalse($response->headers->hasCacheControlDirective('must-revalidate'));
        self::assertSame('30', $response->headers->getCacheControlDirective('max-age'));

        $listener->onKernelResponse(new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response));

        self::assertTrue($response->headers->hasCacheControlDirective('private'));
        self::assertTrue($response->headers->hasCacheControlDirective('must-revalidate'));
        self::assertSame('0', $response->headers->getCacheControlDirective('max-age'));

        self::assertTrue($response->headers->has('Expires'));
        self::assertLessThanOrEqual(new \DateTime('now', new \DateTimeZone('UTC')), new \DateTime($response->headers->get('Expires')));
    }

    public function testGetSessionIsCalledOnce()
    {
        $session = self::createMock(Session::class);
        $session->expects(self::exactly(1))->method('getName')->willReturn('PHPSESSID');
        $sessionStorage = self::createMock(NativeSessionStorage::class);
        $kernel = self::createMock(KernelInterface::class);

        $sessionStorage->expects(self::once())
            ->method('setOptions')
            ->with(['cookie_secure' => true]);

        $requestStack = new RequestStack();
        $requestStack->push($mainRequest = new Request([], [], [], [], [], ['HTTPS' => 'on']));

        $container = new Container();
        $container->set('session_storage', $sessionStorage);
        $container->set('session', $session);
        $container->set('request_stack', $requestStack);

        $event = new RequestEvent($kernel, $mainRequest, HttpKernelInterface::MAIN_REQUEST);

        $listener = new SessionListener($container);
        $listener->onKernelRequest($event);

        // storage->setOptions() should have been called already
        $container->set('session_storage', null);
        $sessionStorage = null;

        $subRequest = $mainRequest->duplicate();
        // at this point both main and subrequest have a closure to build the session

        $mainRequest->getSession();

        // calling the factory on the subRequest should not trigger a second call to storage->setOptions()
        $subRequest->getSession();
    }

    public function testSessionUsageExceptionIfStatelessAndSessionUsed()
    {
        $session = self::createMock(Session::class);
        $session->expects(self::exactly(2))->method('getUsageIndex')->will(self::onConsecutiveCalls(0, 1));

        $container = new Container();
        $container->set('initialized_session', $session);

        $listener = new SessionListener($container, true);
        $kernel = self::createMock(HttpKernelInterface::class);

        $request = new Request();
        $request->attributes->set('_stateless', true);
        $listener->onKernelRequest(new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST));

        self::expectException(UnexpectedSessionUsageException::class);
        $listener->onKernelResponse(new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, new Response()));
    }

    public function testSessionUsageLogIfStatelessAndSessionUsed()
    {
        $session = self::createMock(Session::class);
        $session->expects(self::exactly(2))->method('getUsageIndex')->will(self::onConsecutiveCalls(0, 1));

        $logger = self::createMock(LoggerInterface::class);
        $logger->expects(self::exactly(1))->method('warning');

        $container = new Container();
        $container->set('initialized_session', $session);
        $container->set('logger', $logger);

        $listener = new SessionListener($container, false);
        $kernel = self::createMock(HttpKernelInterface::class);

        $request = new Request();
        $request->attributes->set('_stateless', true);
        $listener->onKernelRequest(new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST));

        $listener->onKernelResponse(new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, new Response()));
    }

    public function testSessionIsSavedWhenUnexpectedSessionExceptionThrown()
    {
        $session = self::createMock(Session::class);
        $session->expects(self::exactly(1))->method('getId')->willReturn('123456');
        $session->expects(self::exactly(1))->method('getName')->willReturn('PHPSESSID');
        $session->method('isStarted')->willReturn(true);
        $session->expects(self::exactly(2))->method('getUsageIndex')->will(self::onConsecutiveCalls(0, 1));
        $session->expects(self::exactly(1))->method('save');

        $container = new Container();
        $container->set('initialized_session', $session);

        $listener = new SessionListener($container, true);
        $kernel = self::createMock(HttpKernelInterface::class);

        $request = new Request();
        $request->attributes->set('_stateless', true);

        $listener->onKernelRequest(new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST));

        $response = new Response();
        self::expectException(UnexpectedSessionUsageException::class);
        $listener->onKernelResponse(new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response));
    }

    public function testSessionUsageCallbackWhenDebugAndStateless()
    {
        $session = self::createMock(Session::class);
        $session->method('isStarted')->willReturn(true);
        $session->expects(self::exactly(1))->method('save');

        $requestStack = new RequestStack();

        $request = new Request();
        $request->attributes->set('_stateless', true);

        $requestStack->push(new Request());
        $requestStack->push($request);
        $requestStack->push(new Request());

        $collector = self::createMock(RequestDataCollector::class);
        $collector->expects(self::once())->method('collectSessionUsage');

        $container = new Container();
        $container->set('initialized_session', $session);
        $container->set('request_stack', $requestStack);
        $container->set('session_collector', \Closure::fromCallable([$collector, 'collectSessionUsage']));

        self::expectException(UnexpectedSessionUsageException::class);
        (new SessionListener($container, true))->onSessionUsage();
    }

    public function testSessionUsageCallbackWhenNoDebug()
    {
        $session = self::createMock(Session::class);
        $session->method('isStarted')->willReturn(true);
        $session->expects(self::exactly(0))->method('save');

        $request = new Request();
        $request->attributes->set('_stateless', true);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $collector = self::createMock(RequestDataCollector::class);
        $collector->expects(self::never())->method('collectSessionUsage');

        $container = new Container();
        $container->set('initialized_session', $session);
        $container->set('request_stack', $requestStack);
        $container->set('session_collector', $collector);

        (new SessionListener($container))->onSessionUsage();
    }

    public function testSessionUsageCallbackWhenNoStateless()
    {
        $session = self::createMock(Session::class);
        $session->method('isStarted')->willReturn(true);
        $session->expects(self::never())->method('save');

        $requestStack = new RequestStack();
        $requestStack->push(new Request());
        $requestStack->push(new Request());

        $container = new Container();
        $container->set('initialized_session', $session);
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

        self::assertNotEmpty($_SESSION);
        self::assertNotEmpty(session_id());

        $container = new Container();

        (new SessionListener($container, true))->reset();

        self::assertEmpty($_SESSION);
        self::assertEmpty(session_id());
        self::assertSame(\PHP_SESSION_NONE, session_status());
    }

    /**
     * @runInSeparateProcess
     */
    public function testResetUnclosedSession()
    {
        session_start();
        $_SESSION['test'] = ['test'];

        self::assertNotEmpty($_SESSION);
        self::assertNotEmpty(session_id());
        self::assertSame(\PHP_SESSION_ACTIVE, session_status());

        $container = new Container();

        (new SessionListener($container, true))->reset();

        self::assertEmpty($_SESSION);
        self::assertEmpty(session_id());
        self::assertSame(\PHP_SESSION_NONE, session_status());
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
