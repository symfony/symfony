<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\DataCollector;

use Symfony\Bundle\FrameworkBundle\Controller\RedirectController;
use Symfony\Bundle\FrameworkBundle\DataCollector\RouterDataCollector;
use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelInterface;

class RouterDataCollectorTest extends TestCase
{
    public function testRouteRedirectControllerNoRouteAtrribute()
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

    public function testRouteRedirectControllerWithRouteAttribute()
    {
        $collector = new RouterDataCollector();

        $request = Request::create('http://test.com/foo?bar=baz');
        $request->attributes->set('_route', 'current-route');

        $response = new RedirectResponse('http://test.com/redirect');

        $event = $this->createControllerEvent($request);

        $collector->onKernelController($event);
        $collector->collect($request, $response);

        $this->assertTrue($collector->getRedirect());
        $this->assertEquals('http://test.com/redirect', $collector->getTargetUrl());
        $this->assertEquals('current-route', $collector->getTargetRoute());
    }

    protected function createControllerEvent(Request $request): ControllerEvent
    {
        $kernel = $this->createMock(KernelInterface::class);

        return new ControllerEvent($kernel, new RedirectController(), $request, null);
    }
}
