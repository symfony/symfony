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
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\EventListener\SaveSessionListener;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @group legacy
 */
class SaveSessionListenerTest extends TestCase
{
    public function testOnlyTriggeredOnMasterRequest()
    {
        $session = $this->createMock(SessionInterface::class);
        $session->expects($this->never())->method('save');
        $session->expects($this->any())->method('isStarted')->willReturn(true);

        $request = new Request();
        $request->setSession($session);

        $listener = new SaveSessionListener();

        // sub request
        $listener->onKernelResponse(new ResponseEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::SUB_REQUEST, new Response()));
    }

    public function testSessionSaved()
    {
        $listener = new SaveSessionListener();
        $kernel = $this->createMock(HttpKernelInterface::class);

        $session = $this->createMock(SessionInterface::class);
        $session->expects($this->once())->method('isStarted')->willReturn(true);
        $session->expects($this->once())->method('save');

        $request = new Request();
        $request->setSession($session);
        $response = new Response();
        $listener->onKernelResponse(new ResponseEvent($kernel, $request, HttpKernelInterface::MASTER_REQUEST, $response));
    }
}
