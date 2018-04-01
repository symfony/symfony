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
use Symphony\Component\HttpFoundation\Request;
use Symphony\Component\HttpFoundation\Response;
use Symphony\Component\HttpFoundation\Session\SessionInterface;
use Symphony\Component\HttpKernel\Event\FilterResponseEvent;
use Symphony\Component\HttpKernel\EventListener\SaveSessionListener;
use Symphony\Component\HttpKernel\HttpKernelInterface;

/**
 * @group legacy
 */
class SaveSessionListenerTest extends TestCase
{
    public function testOnlyTriggeredOnMasterRequest()
    {
        $listener = new SaveSessionListener();
        $event = $this->getMockBuilder(FilterResponseEvent::class)->disableOriginalConstructor()->getMock();
        $event->expects($this->once())->method('isMasterRequest')->willReturn(false);
        $event->expects($this->never())->method('getRequest');

        // sub request
        $listener->onKernelResponse($event);
    }

    public function testSessionSaved()
    {
        $listener = new SaveSessionListener();
        $kernel = $this->getMockBuilder(HttpKernelInterface::class)->disableOriginalConstructor()->getMock();

        $session = $this->getMockBuilder(SessionInterface::class)->disableOriginalConstructor()->getMock();
        $session->expects($this->once())->method('isStarted')->willReturn(true);
        $session->expects($this->once())->method('save');

        $request = new Request();
        $request->setSession($session);
        $response = new Response();
        $listener->onKernelResponse(new FilterResponseEvent($kernel, $request, HttpKernelInterface::MASTER_REQUEST, $response));
    }
}
