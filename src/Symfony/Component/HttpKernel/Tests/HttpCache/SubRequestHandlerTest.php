<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\HttpCache;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpCache\SubRequestHandler;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class SubRequestHandlerTest extends TestCase
{
    private static $globalState;

    protected function setUp(): void
    {
        self::$globalState = $this->getGlobalState();
    }

    protected function tearDown(): void
    {
        Request::setTrustedProxies(self::$globalState[0], self::$globalState[1]);
    }

    public function testTrustedHeadersAreKept()
    {
        Request::setTrustedProxies(['10.0.0.1'], -1);
        $globalState = $this->getGlobalState();

        $request = Request::create('/');
        $request->server->set('REMOTE_ADDR', '10.0.0.1');
        $request->headers->set('X-Forwarded-For', '10.0.0.2');
        $request->headers->set('X-Forwarded-Host', 'Good');
        $request->headers->set('X-Forwarded-Port', '1234');
        $request->headers->set('X-Forwarded-Proto', 'https');
        $request->headers->set('X-Forwarded-Prefix', '/admin');

        $kernel = new TestSubRequestHandlerKernel(function ($request, $type, $catch) {
            self::assertSame('127.0.0.1', $request->server->get('REMOTE_ADDR'));
            self::assertSame('10.0.0.2', $request->getClientIp());
            self::assertSame('Good', $request->headers->get('X-Forwarded-Host'));
            self::assertSame('1234', $request->headers->get('X-Forwarded-Port'));
            self::assertSame('https', $request->headers->get('X-Forwarded-Proto'));
            self::assertSame('/admin', $request->headers->get('X-Forwarded-Prefix'));
        });

        SubRequestHandler::handle($kernel, $request, HttpKernelInterface::MAIN_REQUEST, true);

        self::assertSame($globalState, $this->getGlobalState());
    }

    public function testUntrustedHeadersAreRemoved()
    {
        $request = Request::create('/');
        $request->server->set('REMOTE_ADDR', '10.0.0.1');
        $request->headers->set('X-Forwarded-For', '10.0.0.2');
        $request->headers->set('X-Forwarded-Host', 'Evil');
        $request->headers->set('X-Forwarded-Port', '1234');
        $request->headers->set('X-Forwarded-Proto', 'http');
        $request->headers->set('X-Forwarded-Prefix', '/admin');
        $request->headers->set('Forwarded', 'Evil2');

        $kernel = new TestSubRequestHandlerKernel(function ($request, $type, $catch) {
            self::assertSame('127.0.0.1', $request->server->get('REMOTE_ADDR'));
            self::assertSame('10.0.0.1', $request->getClientIp());
            self::assertFalse($request->headers->has('X-Forwarded-Host'));
            self::assertFalse($request->headers->has('X-Forwarded-Port'));
            self::assertFalse($request->headers->has('X-Forwarded-Proto'));
            self::assertFalse($request->headers->has('X-Forwarded-Prefix'));
            self::assertSame('for="10.0.0.1";host="localhost";proto=http', $request->headers->get('Forwarded'));
        });

        SubRequestHandler::handle($kernel, $request, HttpKernelInterface::MAIN_REQUEST, true);

        self::assertSame(self::$globalState, $this->getGlobalState());
    }

    public function testTrustedForwardedHeader()
    {
        Request::setTrustedProxies(['10.0.0.1'], -1);
        $globalState = $this->getGlobalState();

        $request = Request::create('/');
        $request->server->set('REMOTE_ADDR', '10.0.0.1');
        $request->headers->set('Forwarded', 'for="10.0.0.2";host="foo.bar:1234";proto=https');

        $kernel = new TestSubRequestHandlerKernel(function ($request, $type, $catch) {
            self::assertSame('127.0.0.1', $request->server->get('REMOTE_ADDR'));
            self::assertSame('10.0.0.2', $request->getClientIp());
            self::assertSame('foo.bar:1234', $request->getHttpHost());
            self::assertSame('https', $request->getScheme());
            self::assertSame(1234, $request->getPort());
        });

        SubRequestHandler::handle($kernel, $request, HttpKernelInterface::MAIN_REQUEST, true);

        self::assertSame($globalState, $this->getGlobalState());
    }

    public function testTrustedXForwardedForHeader()
    {
        Request::setTrustedProxies(['10.0.0.1'], -1);
        $globalState = $this->getGlobalState();

        $request = Request::create('/');
        $request->server->set('REMOTE_ADDR', '10.0.0.1');
        $request->headers->set('X-Forwarded-For', '10.0.0.2');
        $request->headers->set('X-Forwarded-Host', 'foo.bar');
        $request->headers->set('X-Forwarded-Proto', 'https');
        $request->headers->set('X-Forwarded-Prefix', '/admin');

        $kernel = new TestSubRequestHandlerKernel(function ($request, $type, $catch) {
            self::assertSame('127.0.0.1', $request->server->get('REMOTE_ADDR'));
            self::assertSame('10.0.0.2', $request->getClientIp());
            self::assertSame('foo.bar', $request->getHttpHost());
            self::assertSame('https', $request->getScheme());
            self::assertSame('/admin', $request->getBaseUrl());
        });

        SubRequestHandler::handle($kernel, $request, HttpKernelInterface::MAIN_REQUEST, true);

        self::assertSame($globalState, $this->getGlobalState());
    }

    private function getGlobalState()
    {
        return [
            Request::getTrustedProxies(),
            Request::getTrustedHeaderSet(),
        ];
    }
}

class TestSubRequestHandlerKernel implements HttpKernelInterface
{
    private $assertCallback;

    public function __construct(\Closure $assertCallback)
    {
        $this->assertCallback = $assertCallback;
    }

    public function handle(Request $request, $type = self::MAIN_REQUEST, $catch = true): Response
    {
        $assertCallback = $this->assertCallback;
        $assertCallback($request, $type, $catch);

        return new Response();
    }
}
