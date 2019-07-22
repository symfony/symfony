<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Handler;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\EventListener\SendFailedMessageToFailureTransportListener;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;

class SendFailedMessageToFailureTransportListenerTest extends TestCase
{
    public function testItDispatchesToTheFailureTransport()
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())->method('dispatch')->with($this->callback(function ($envelope) {
            /* @var Envelope $envelope */
            $this->assertInstanceOf(Envelope::class, $envelope);

            /** @var SentToFailureTransportStamp $sentToFailureTransportStamp */
            $sentToFailureTransportStamp = $envelope->last(SentToFailureTransportStamp::class);
            $this->assertNotNull($sentToFailureTransportStamp);
            $this->assertSame('my_receiver', $sentToFailureTransportStamp->getOriginalReceiverName());

            /** @var RedeliveryStamp $redeliveryStamp */
            $redeliveryStamp = $envelope->last(RedeliveryStamp::class);
            $this->assertSame('failure_sender', $redeliveryStamp->getSenderClassOrAlias());
            $this->assertSame('no!', $redeliveryStamp->getExceptionMessage());
            $this->assertSame('no!', $redeliveryStamp->getFlattenException()->getMessage());

            $this->assertNull($envelope->last(ReceivedStamp::class));
            $this->assertNull($envelope->last(TransportMessageIdStamp::class));

            return true;
        }))->willReturn(new Envelope(new \stdClass()));
        $listener = new SendFailedMessageToFailureTransportListener(
            $bus,
            'failure_sender'
        );

        $exception = new \Exception('no!');
        $envelope = new Envelope(new \stdClass());
        $event = new WorkerMessageFailedEvent($envelope, 'my_receiver', $exception, false);

        $listener->onMessageFailed($event);
    }

    public function testItGetsNestedHandlerFailedException()
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())->method('dispatch')->with($this->callback(function ($envelope) {
            /** @var Envelope $envelope */
            /** @var RedeliveryStamp $redeliveryStamp */
            $redeliveryStamp = $envelope->last(RedeliveryStamp::class);
            $this->assertNotNull($redeliveryStamp);
            $this->assertSame('I am inside!', $redeliveryStamp->getExceptionMessage());
            $this->assertSame('Exception', $redeliveryStamp->getFlattenException()->getClass());

            return true;
        }))->willReturn(new Envelope(new \stdClass()));

        $listener = new SendFailedMessageToFailureTransportListener(
            $bus,
            'failure_sender'
        );

        $envelope = new Envelope(new \stdClass());
        $exception = new \Exception('I am inside!');
        $exception = new HandlerFailedException($envelope, [$exception]);
        $event = new WorkerMessageFailedEvent($envelope, 'my_receiver', $exception, false);

        $listener->onMessageFailed($event);
    }

    public function testDoNothingOnRetry()
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');
        $listener = new SendFailedMessageToFailureTransportListener(
            $bus,
            'failure_sender'
        );

        $envelope = new Envelope(new \stdClass());
        $event = new WorkerMessageFailedEvent($envelope, 'my_receiver', new \Exception(''), true);

        $listener->onMessageFailed($event);
    }

    public function testDoNotRedeliverToFailed()
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');
        $listener = new SendFailedMessageToFailureTransportListener(
            $bus,
            'failure_sender'
        );

        $envelope = new Envelope(new \stdClass(), [
            new SentToFailureTransportStamp('my_receiver'),
        ]);
        $event = new WorkerMessageFailedEvent($envelope, 'my_receiver', new \Exception(''), false);

        $listener->onMessageFailed($event);
    }
}
