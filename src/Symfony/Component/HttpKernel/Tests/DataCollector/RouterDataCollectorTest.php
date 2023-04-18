<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\DataCollector;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\RouterDataCollector;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelInterface;

class RouterDataCollectorTest extends TestCase
{
    public function testRouteRedirectResponse()
    {
        $collector = new RouterDataCollector();

        $request = Request::create('http://test.com/foo?bar=baz');
        $response = new RedirectResponse('http://test.com/redirect');

        $event = $this->createControllerEvent($request);

        $collector->onKernelController($event);
        $collector->collect($request, $response);

        $this->assertTrue($collector->getRedirect());
        $this->assertEquals('http://test.com/redirect', $collector->getTargetUrl());
        $this->assertEquals('n/a', $collector->getTargetRoute());
    }

    public function testRouteNotRedirectResponse()
    {
        $collector = new RouterDataCollector();

        $request = Request::create('http://test.com/foo?bar=baz');
        $response = new Response('test');

        $event = $this->createControllerEvent($request);

        $collector->onKernelController($event);
        $collector->collect($request, $response);

        $this->assertFalse($collector->getRedirect());
        $this->assertNull($collector->getTargetUrl());
        $this->assertNull($collector->getTargetRoute());
    }

    public function testReset()
    {
        $collector = new RouterDataCollector();

        // Fill Collector
        $request = Request::create('http://test.com/foo?bar=baz');
        $response = new RedirectResponse('http://test.com/redirect');
        $event = $this->createControllerEvent($request);
        $collector->onKernelController($event);
        $collector->collect($request, $response);

        $collector->reset();

        $this->assertFalse($collector->getRedirect());
        $this->assertNull($collector->getTargetUrl());
        $this->assertNull($collector->getTargetRoute());
    }

    public function testGetName()
    {
        $collector = new RouterDataCollector();

        $this->assertEquals('router', $collector->getName());
    }

    protected function createControllerEvent(Request $request): ControllerEvent
    {
        $kernel = $this->createMock(KernelInterface::class);

        return new ControllerEvent($kernel, function () {}, $request, null);
    }
}
