<?php

namespace Symfony\Bridge\PsrHttpMessage\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PsrHttpMessage\EventListener\PsrResponseListener;
use Symfony\Bridge\PsrHttpMessage\Tests\Fixtures\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class PsrResponseListenerTest extends TestCase
{
    public function testConvertsControllerResult()
    {
        $listener = new PsrResponseListener();
        $event = $this->createEventMock(new Response());
        $listener->onKernelView($event);

        self::assertTrue($event->hasResponse());
    }

    public function testDoesNotConvertControllerResult()
    {
        $listener = new PsrResponseListener();
        $event = $this->createEventMock([]);

        $listener->onKernelView($event);
        self::assertFalse($event->hasResponse());

        $event = $this->createEventMock(null);

        $listener->onKernelView($event);
        self::assertFalse($event->hasResponse());
    }

    private function createEventMock($controllerResult): ViewEvent
    {
        return new ViewEvent($this->createMock(HttpKernelInterface::class), new Request(), HttpKernelInterface::MASTER_REQUEST, $controllerResult);
    }
}
