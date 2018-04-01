<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\HttpKernel\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Symphony\Component\HttpKernel\HttpCache\Esi;
use Symphony\Component\HttpKernel\EventListener\SurrogateListener;
use Symphony\Component\HttpKernel\Event\FilterResponseEvent;
use Symphony\Component\HttpKernel\KernelEvents;
use Symphony\Component\HttpKernel\HttpKernelInterface;
use Symphony\Component\HttpFoundation\Response;
use Symphony\Component\HttpFoundation\Request;
use Symphony\Component\EventDispatcher\EventDispatcher;

class SurrogateListenerTest extends TestCase
{
    public function testFilterDoesNothingForSubRequests()
    {
        $dispatcher = new EventDispatcher();
        $kernel = $this->getMockBuilder('Symphony\Component\HttpKernel\HttpKernelInterface')->getMock();
        $response = new Response('foo <esi:include src="" />');
        $listener = new SurrogateListener(new Esi());

        $dispatcher->addListener(KernelEvents::RESPONSE, array($listener, 'onKernelResponse'));
        $event = new FilterResponseEvent($kernel, new Request(), HttpKernelInterface::SUB_REQUEST, $response);
        $dispatcher->dispatch(KernelEvents::RESPONSE, $event);

        $this->assertEquals('', $event->getResponse()->headers->get('Surrogate-Control'));
    }

    public function testFilterWhenThereIsSomeEsiIncludes()
    {
        $dispatcher = new EventDispatcher();
        $kernel = $this->getMockBuilder('Symphony\Component\HttpKernel\HttpKernelInterface')->getMock();
        $response = new Response('foo <esi:include src="" />');
        $listener = new SurrogateListener(new Esi());

        $dispatcher->addListener(KernelEvents::RESPONSE, array($listener, 'onKernelResponse'));
        $event = new FilterResponseEvent($kernel, new Request(), HttpKernelInterface::MASTER_REQUEST, $response);
        $dispatcher->dispatch(KernelEvents::RESPONSE, $event);

        $this->assertEquals('content="ESI/1.0"', $event->getResponse()->headers->get('Surrogate-Control'));
    }

    public function testFilterWhenThereIsNoEsiIncludes()
    {
        $dispatcher = new EventDispatcher();
        $kernel = $this->getMockBuilder('Symphony\Component\HttpKernel\HttpKernelInterface')->getMock();
        $response = new Response('foo');
        $listener = new SurrogateListener(new Esi());

        $dispatcher->addListener(KernelEvents::RESPONSE, array($listener, 'onKernelResponse'));
        $event = new FilterResponseEvent($kernel, new Request(), HttpKernelInterface::MASTER_REQUEST, $response);
        $dispatcher->dispatch(KernelEvents::RESPONSE, $event);

        $this->assertEquals('', $event->getResponse()->headers->get('Surrogate-Control'));
    }
}
