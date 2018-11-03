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
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\EventListener\SurrogateListener;
use Symfony\Component\HttpKernel\HttpCache\Esi;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class SurrogateListenerTest extends TestCase
{
    public function testFilterDoesNothingForSubRequests()
    {
        $dispatcher = new EventDispatcher();
        $kernel = $this->getMockBuilder('Symfony\Component\HttpKernel\HttpKernelInterface')->getMock();
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
        $kernel = $this->getMockBuilder('Symfony\Component\HttpKernel\HttpKernelInterface')->getMock();
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
        $kernel = $this->getMockBuilder('Symfony\Component\HttpKernel\HttpKernelInterface')->getMock();
        $response = new Response('foo');
        $listener = new SurrogateListener(new Esi());

        $dispatcher->addListener(KernelEvents::RESPONSE, array($listener, 'onKernelResponse'));
        $event = new FilterResponseEvent($kernel, new Request(), HttpKernelInterface::MASTER_REQUEST, $response);
        $dispatcher->dispatch(KernelEvents::RESPONSE, $event);

        $this->assertEquals('', $event->getResponse()->headers->get('Surrogate-Control'));
    }
}
