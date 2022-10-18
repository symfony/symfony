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
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\EventListener\FragmentListener;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\UriSigner;

class FragmentListenerTest extends TestCase
{
    public function testOnlyTriggeredOnFragmentRoute()
    {
        $request = Request::create('http://example.com/foo?_path=foo%3Dbar%26_controller%3Dfoo');

        $listener = new FragmentListener(new UriSigner('foo'));
        $event = $this->createRequestEvent($request);

        $expected = $request->attributes->all();

        $listener->onKernelRequest($event);

        $this->assertEquals($expected, $request->attributes->all());
        $this->assertTrue($request->query->has('_path'));
    }

    public function testOnlyTriggeredIfControllerWasNotDefinedYet()
    {
        $request = Request::create('http://example.com/_fragment?_path=foo%3Dbar%26_controller%3Dfoo');
        $request->attributes->set('_controller', 'bar');

        $listener = new FragmentListener(new UriSigner('foo'));
        $event = $this->createRequestEvent($request, HttpKernelInterface::SUB_REQUEST);

        $expected = $request->attributes->all();

        $listener->onKernelRequest($event);

        $this->assertEquals($expected, $request->attributes->all());
    }

    public function testAccessDeniedWithNonSafeMethods()
    {
        $this->expectException(AccessDeniedHttpException::class);
        $request = Request::create('http://example.com/_fragment', 'POST');

        $listener = new FragmentListener(new UriSigner('foo'));
        $event = $this->createRequestEvent($request);

        $listener->onKernelRequest($event);
    }

    public function testAccessDeniedWithWrongSignature()
    {
        $this->expectException(AccessDeniedHttpException::class);
        $request = Request::create('http://example.com/_fragment', 'GET', [], [], [], ['REMOTE_ADDR' => '10.0.0.1']);

        $listener = new FragmentListener(new UriSigner('foo'));
        $event = $this->createRequestEvent($request);

        $listener->onKernelRequest($event);
    }

    public function testWithSignature()
    {
        $signer = new UriSigner('foo');
        $request = Request::create($signer->sign('http://example.com/_fragment?_path=foo%3Dbar%26_controller%3Dfoo'), 'GET', [], [], [], ['REMOTE_ADDR' => '10.0.0.1']);

        $listener = new FragmentListener($signer);
        $event = $this->createRequestEvent($request);

        $listener->onKernelRequest($event);

        $this->assertEquals(['foo' => 'bar', '_controller' => 'foo'], $request->attributes->get('_route_params'));
        $this->assertFalse($request->query->has('_path'));
    }

    public function testRemovesPathWithControllerDefined()
    {
        $request = Request::create('http://example.com/_fragment?_path=foo%3Dbar%26_controller%3Dfoo');

        $listener = new FragmentListener(new UriSigner('foo'));
        $event = $this->createRequestEvent($request, HttpKernelInterface::SUB_REQUEST);

        $listener->onKernelRequest($event);

        $this->assertFalse($request->query->has('_path'));
    }

    public function testRemovesPathWithControllerNotDefined()
    {
        $signer = new UriSigner('foo');
        $request = Request::create($signer->sign('http://example.com/_fragment?_path=foo%3Dbar'), 'GET', [], [], [], ['REMOTE_ADDR' => '10.0.0.1']);

        $listener = new FragmentListener($signer);
        $event = $this->createRequestEvent($request);

        $listener->onKernelRequest($event);

        $this->assertFalse($request->query->has('_path'));
    }

    private function createRequestEvent(Request $request, int $requestType = HttpKernelInterface::MAIN_REQUEST): RequestEvent
    {
        return new RequestEvent($this->createMock(HttpKernelInterface::class), $request, $requestType);
    }
}
