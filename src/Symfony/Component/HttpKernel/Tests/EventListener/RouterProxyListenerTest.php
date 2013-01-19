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

use Symfony\Component\HttpKernel\EventListener\RouterProxyListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\UriSigner;

class RouterProxyListenerTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        if (!class_exists('Symfony\Component\EventDispatcher\EventDispatcher')) {
            $this->markTestSkipped('The "EventDispatcher" component is not available');
        }
    }

    public function testOnlyTriggeredOnProxyRoute()
    {
        $request = Request::create('http://example.com/foo?path=foo%3D=bar');

        $listener = new RouterProxyListener(new UriSigner('foo'));
        $event = $this->createGetResponseEvent($request, 'foobar');

        $expected = $request->attributes->all();

        $listener->onKernelRequest($event);

        $this->assertEquals($expected, $request->attributes->all());
        $this->assertTrue($request->query->has('path'));
    }

    /**
     * @expectedException \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
     */
    public function testAccessDeniedWithNonSafeMethods()
    {
        $request = Request::create('http://example.com/foo', 'POST');

        $listener = new RouterProxyListener(new UriSigner('foo'));
        $event = $this->createGetResponseEvent($request);

        $listener->onKernelRequest($event);
    }

    /**
     * @expectedException \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
     */
    public function testAccessDeniedWithNonLocalIps()
    {
        $request = Request::create('http://example.com/foo', 'GET', array(), array(), array(), array('REMOTE_ADDR' => '10.0.0.1'));

        $listener = new RouterProxyListener(new UriSigner('foo'));
        $event = $this->createGetResponseEvent($request);

        $listener->onKernelRequest($event);
    }

    /**
     * @expectedException \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
     */
    public function testAccessDeniedWithWrongSignature()
    {
        $request = Request::create('http://example.com/foo', 'GET', array(), array(), array(), array('REMOTE_ADDR' => '10.0.0.1'));

        $listener = new RouterProxyListener(new UriSigner('foo'));
        $event = $this->createGetResponseEvent($request);

        $listener->onKernelRequest($event);
    }

    public function testWithSignatureAndNoPath()
    {
        $signer = new UriSigner('foo');
        $request = Request::create($signer->sign('http://example.com/foo'), 'GET', array(), array(), array(), array('REMOTE_ADDR' => '10.0.0.1'));

        $listener = new RouterProxyListener($signer);
        $event = $this->createGetResponseEvent($request);

        $listener->onKernelRequest($event);

        $this->assertEquals(array('foo' => 'foo'), $request->attributes->get('_route_params'));
        $this->assertFalse($request->query->has('path'));
    }

    public function testWithSignatureAndPath()
    {
        $signer = new UriSigner('foo');
        $request = Request::create($signer->sign('http://example.com/foo?path=bar%3Dbar'), 'GET', array(), array(), array(), array('REMOTE_ADDR' => '10.0.0.1'));

        $listener = new RouterProxyListener($signer);
        $event = $this->createGetResponseEvent($request);

        $listener->onKernelRequest($event);

        $this->assertEquals(array('foo' => 'foo', 'bar' => 'bar'), $request->attributes->get('_route_params'));
        $this->assertFalse($request->query->has('path'));
    }

    private function createGetResponseEvent(Request $request, $route = '_proxy')
    {
        $kernel = $this->getMock('Symfony\Component\HttpKernel\HttpKernelInterface');
        $request->attributes->set('_route', $route);
        $request->attributes->set('_route_params', array('foo' => 'foo'));

        return new GetResponseEvent($kernel, $request, HttpKernelInterface::MASTER_REQUEST);
    }
}
