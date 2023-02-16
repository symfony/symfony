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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\EventListener\AbstractTestSessionListener;
use Symfony\Component\HttpKernel\EventListener\TestSessionListener;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * SessionListenerTest.
 *
 * Tests SessionListener.
 *
 * @author Bulat Shakirzyanov <mallluhuct@gmail.com>
 *
 * @group legacy
 */
class TestSessionListenerTest extends TestCase
{
    /**
     * @var TestSessionListener
     */
    private $listener;

    /**
     * @var SessionInterface
     */
    private $session;

    protected function setUp(): void
    {
        $this->listener = $this->getMockForAbstractClass(AbstractTestSessionListener::class);
        $this->session = $this->getSession();
        $this->listener->expects($this->any())
             ->method('getSession')
             ->willReturn($this->session);
    }

    public function testShouldSaveMainRequestSession()
    {
        $this->sessionHasBeenStarted();
        $this->sessionMustBeSaved();

        $this->filterResponse(new Request());
    }

    public function testShouldNotSaveSubRequestSession()
    {
        $this->sessionMustNotBeSaved();

        $this->filterResponse(new Request(), HttpKernelInterface::SUB_REQUEST);
    }

    public function testDoesNotDeleteCookieIfUsingSessionLifetime()
    {
        $this->sessionHasBeenStarted();

        @ini_set('session.cookie_lifetime', 0);

        $response = $this->filterResponse(new Request(), HttpKernelInterface::MAIN_REQUEST);
        $cookies = $response->headers->getCookies();

        $this->assertEquals(0, reset($cookies)->getExpiresTime());
    }

    /**
     * @requires function \Symfony\Component\HttpFoundation\Session\Session::isEmpty
     */
    public function testEmptySessionDoesNotSendCookie()
    {
        $this->sessionHasBeenStarted();
        $this->sessionIsEmpty();

        $response = $this->filterResponse(new Request(), HttpKernelInterface::MAIN_REQUEST);

        $this->assertSame([], $response->headers->getCookies());
    }

    public function testEmptySessionWithNewSessionIdDoesSendCookie()
    {
        $this->sessionHasBeenStarted();
        $this->sessionIsEmpty();
        $this->fixSessionId('456');

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/', 'GET', [], ['MOCKSESSID' => '123']);
        $request->setSession($this->getSession());
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $this->listener->onKernelRequest($event);

        $response = $this->filterResponse(new Request(), HttpKernelInterface::MAIN_REQUEST);

        $this->assertNotEmpty($response->headers->getCookies());
    }

    /**
     * @dataProvider anotherCookieProvider
     */
    public function testSessionWithNewSessionIdAndNewCookieDoesNotSendAnotherCookie($existing, array $expected)
    {
        $this->sessionHasBeenStarted();
        $this->sessionIsEmpty();
        $this->fixSessionId('456');

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/', 'GET', [], ['MOCKSESSID' => '123']);
        $request->setSession($this->getSession());
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $this->listener->onKernelRequest($event);

        $response = new Response('', 200, ['Set-Cookie' => $existing]);

        $response = $this->filterResponse(new Request(), HttpKernelInterface::MAIN_REQUEST, $response);

        $this->assertSame($expected, $response->headers->all()['set-cookie']);
    }

    public static function anotherCookieProvider()
    {
        return [
            'same' => ['MOCKSESSID=789; path=/', ['MOCKSESSID=789; path=/']],
            'different domain' => ['MOCKSESSID=789; path=/; domain=example.com', ['MOCKSESSID=789; path=/; domain=example.com', 'MOCKSESSID=456; path=/']],
            'different path' => ['MOCKSESSID=789; path=/foo', ['MOCKSESSID=789; path=/foo', 'MOCKSESSID=456; path=/']],
        ];
    }

    public function testUnstartedSessionIsNotSave()
    {
        $this->sessionHasNotBeenStarted();
        $this->sessionMustNotBeSaved();

        $this->filterResponse(new Request());
    }

    public function testDoesNotThrowIfRequestDoesNotHaveASession()
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new ResponseEvent($kernel, new Request(), HttpKernelInterface::MAIN_REQUEST, new Response());

        $this->listener->onKernelResponse($event);

        $this->assertTrue(true);
    }

    private function filterResponse(Request $request, $type = HttpKernelInterface::MAIN_REQUEST, Response $response = null)
    {
        $request->setSession($this->session);
        $response = $response ?? new Response();
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new ResponseEvent($kernel, $request, $type, $response);

        $this->listener->onKernelResponse($event);

        $this->assertSame($response, $event->getResponse());

        return $response;
    }

    private function sessionMustNotBeSaved()
    {
        $this->session->expects($this->never())
            ->method('save');
    }

    private function sessionMustBeSaved()
    {
        $this->session->expects($this->once())
            ->method('save');
    }

    private function sessionHasBeenStarted()
    {
        $this->session->expects($this->once())
            ->method('isStarted')
            ->willReturn(true);
    }

    private function sessionHasNotBeenStarted()
    {
        $this->session->expects($this->once())
            ->method('isStarted')
            ->willReturn(false);
    }

    private function sessionIsEmpty()
    {
        $this->session->expects($this->once())
            ->method('isEmpty')
            ->willReturn(true);
    }

    private function fixSessionId($sessionId)
    {
        $this->session->expects($this->any())
            ->method('getId')
            ->willReturn($sessionId);
    }

    private function getSession()
    {
        $mock = $this->createMock(Session::class);
        $mock->expects($this->any())->method('getName')->willReturn('MOCKSESSID');

        return $mock;
    }
}
