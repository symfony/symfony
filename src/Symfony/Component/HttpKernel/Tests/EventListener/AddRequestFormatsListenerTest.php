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
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\EventListener\AddRequestFormatsListener;
use Symfony\Component\HttpKernel\KernelEvents;

/**

 */
class AddRequestFormatsListenerTest extends TestCase
{
    /**
     * @var AddRequestFormatsListener
     */
    private $listener;

    protected function setUp(): void
    {
        $this->listener = new AddRequestFormatsListener(['csv' => ['text/csv', 'text/plain']]);
    }

    protected function tearDown(): void
    {
        $this->listener = null;
    }

    public function testIsAnEventSubscriber()
    {
        $this->assertInstanceOf(EventSubscriberInterface::class, $this->listener);
    }

    public function testRegisteredEvent()
    {
        $this->assertSame(
            [KernelEvents::REQUEST => ['onKernelRequest', 100]],
            AddRequestFormatsListener::getSubscribedEvents()
        );
    }

    public function testSetAdditionalFormats()
    {
        $request = $this->createMock(Request::class);
        $event = $this->getRequestEventMock($request);

        $request->expects($this->once())
            ->method('setFormat')
            ->with('csv', ['text/csv', 'text/plain']);

        $this->listener->onKernelRequest($event);
    }

    protected function getRequestEventMock(Request $request)
    {
        $event = $this->createMock(RequestEvent::class);
        $event->expects($this->any())
            ->method('getRequest')
            ->willReturn($request);

        return $event;
    }
}
