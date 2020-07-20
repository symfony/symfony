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

        $kernel = new TestSubRequestHandlerKernel(function ($request, $type, $catch) {
            $this->assertSame('127.0.0.1', $request->server->get('REMOTE_ADDR'));
            $this->assertSame('10.0.0.2', $request->getClientIp());
            $this->assertSame('Good', $request->headers->get('X-Forwarded-Host'));
            $this->assertSame('1234', $request->headers->get('X-Forwarded-Port'));
            $this->assertSame('https', $request->headers->get('X-Forwarded-Proto'));
        });

        SubRequestHandler::handle($kernel, $request, HttpKernelInterface::MASTER_REQUEST, true);

        $this->assertSame($globalState, $this->getGlobalState());
    }

    public function testUntrustedHeadersAreRemoved()
    {
        $request = Request::create('/');
        $request->server->set('REMOTE_ADDR', '10.0.0.1');
        $request->headers->set('X-Forwarded-For', '10.0.0.2');
        $request->headers->set('X-Forwarded-Host', 'Evil');
        $request->headers->set('X-Forwarded-Port', '1234');
        $request->headers->set('X-Forwarded-Proto', 'http');
        $request->headers->set('Forwarded', 'Evil2');

        $kernel = new TestSubRequestHandlerKernel(function ($request, $type, $catch) {
            $this->assertSame('127.0.0.1', $request->server->get('REMOTE_ADDR'));
            $this->assertSame('10.0.0.1', $request->getClientIp());
            $this->assertFalse($request->headers->has('X-Forwarded-Host'));
            $this->assertFalse($request->headers->has('X-Forwarded-Port'));
            $this->assertFalse($request->headers->has('X-Forwarded-Proto'));
            $this->assertSame('for="10.0.0.1";host="localhost";proto=http', $request->headers->get('Forwarded'));
        });

        SubRequestHandler::handle($kernel, $request, HttpKernelInterface::MASTER_REQUEST, true);

        $this->assertSame(self::$globalState, $this->getGlobalState());
    }

    public function testTrustedForwardedHeader()
    {
        Request::setTrustedProxies(['10.0.0.1'], -1);
        $globalState = $this->getGlobalState();

        $request = Request::create('/');
        $request->server->set('REMOTE_ADDR', '10.0.0.1');
        $request->headers->set('Forwarded', 'for="10.0.0.2";host="foo.bar:1234";proto=https');

        $kernel = new TestSubRequestHandlerKernel(function ($request, $type, $catch) {
            $this->assertSame('127.0.0.1', $request->server->get('REMOTE_ADDR'));
            $this->assertSame('10.0.0.2', $request->getClientIp());
            $this->assertSame('foo.bar:1234', $request->getHttpHost());
            $this->assertSame('https', $request->getScheme());
            $this->assertSame(1234, $request->getPort());
        });

        SubRequestHandler::handle($kernel, $request, HttpKernelInterface::MASTER_REQUEST, true);

        $this->assertSame($globalState, $this->getGlobalState());
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

        $kernel = new TestSubRequestHandlerKernel(function ($request, $type, $catch) {
            $this->assertSame('127.0.0.1', $request->server->get('REMOTE_ADDR'));
            $this->assertSame('10.0.0.2', $request->getClientIp());
            $this->assertSame('foo.bar', $request->getHttpHost());
            $this->assertSame('https', $request->getScheme());
        });

        SubRequestHandler::handle($kernel, $request, HttpKernelInterface::MASTER_REQUEST, true);

        $this->assertSame($globalState, $this->getGlobalState());
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

    public function handle(Request $request, $type = self::MASTER_REQUEST, $catch = true): Response
    {
        $assertCallback = $this->assertCallback;
        $assertCallback($request, $type, $catch);

        return new Response();
    }
}
