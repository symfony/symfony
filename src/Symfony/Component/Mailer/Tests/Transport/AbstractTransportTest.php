<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mailer\Event\MessageSentEvent;
use Symfony\Component\Mailer\Exception\LogicException;
use Symfony\Component\Mailer\Transport\NullTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\RawMessage;

/**
 * @group time-sensitive
 */
class AbstractTransportTest extends TestCase
{
    public function testThrottling()
    {
        $transport = new NullTransport();
        $transport->setMaxPerSecond(2 / 10);
        $message = new RawMessage('');
        $envelope = new Envelope(new Address('fabien@example.com'), [new Address('helene@example.com')]);

        $start = time();
        $transport->send($message, $envelope);
        $this->assertEqualsWithDelta(0, time() - $start, 1);
        $transport->send($message, $envelope);
        $this->assertEqualsWithDelta(5, time() - $start, 1);
        $transport->send($message, $envelope);
        $this->assertEqualsWithDelta(10, time() - $start, 1);
        $transport->send($message, $envelope);
        $this->assertEqualsWithDelta(15, time() - $start, 1);

        $start = time();
        $transport->setMaxPerSecond(-3);
        $transport->send($message, $envelope);
        $this->assertEqualsWithDelta(0, time() - $start, 1);
        $transport->send($message, $envelope);
        $this->assertEqualsWithDelta(0, time() - $start, 1);
    }

    public function testSendingRawMessages()
    {
        $this->expectException(LogicException::class);

        $transport = new NullTransport();
        $transport->send(new RawMessage('Some raw email message'));
    }

    public function testEventDispatching()
    {
        $message = new RawMessage('');
        $envelope = new Envelope(new Address('fabien@example.com'), [new Address('helene@example.com')]);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        // we expect to calls to the dispatcher, first with a MessageEvent, and then with a MessageSentEvent
        $expectedEvents = [MessageEvent::class, MessageSentEvent::class];
        $eventDispatcher
            ->expects($this->exactly(2))
            ->method('dispatch')
            ->with($this->callback(function($event) use (&$expectedEvents) {
                $currentEvent = array_shift($expectedEvents);
                return $event instanceof $currentEvent;
            }));

        $transport = new NullTransport($eventDispatcher, $this->createMock(LoggerInterface::class));
        $transport->send($message, $envelope);
    }
}
