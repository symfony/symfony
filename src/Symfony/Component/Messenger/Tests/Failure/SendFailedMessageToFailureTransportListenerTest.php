<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Failure;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Failure\FailedMessage;
use Symfony\Component\Messenger\Failure\SendFailedMessageToFailureTransportListener;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;

class SendFailedMessageToFailureTransportListenerTest extends TestCase
{
    public function testItDispatchesFailedMessage()
    {
        $originalEnvelope = $envelope = new Envelope(new \stdClass());
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())->method('dispatch')->with($this->callback(function ($envelope) use ($originalEnvelope) {
            /* @var Envelope $envelope */
            $this->assertInstanceOf(Envelope::class, $envelope);
            /** @var FailedMessage $message */
            $message = $envelope->getMessage();
            $this->assertInstanceOf(FailedMessage::class, $message);

            $this->assertSame($originalEnvelope, $message->getFailedEnvelope());
            $this->assertSame('no!', $message->getExceptionMessage());
            $this->assertSame('no!', $message->getFlattenException()->getMessage());

            $this->assertNull($envelope->last(ReceivedStamp::class));
            $this->assertNull($envelope->last(TransportMessageIdStamp::class));

            return true;
        }))->willReturn(new Envelope(new \stdClass()));
        $listener = new SendFailedMessageToFailureTransportListener($bus);

        $exception = new \Exception('no!');
        $event = new WorkerMessageFailedEvent($originalEnvelope, 'my_receiver', $exception, false);

        $listener->onMessageFailed($event);
    }

    public function testItGetsNestedHandlerFailedException()
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())->method('dispatch')->with($this->callback(function ($envelope) {
            /** @var Envelope $envelope */
            /** @var FailedMessage $message */
            $message = $envelope->getMessage();
            $this->assertSame('I am inside!', $message->getExceptionMessage());
            $this->assertSame('Exception', $message->getFlattenException()->getClass());

            return true;
        }))->willReturn(new Envelope(new \stdClass()));

        $listener = new SendFailedMessageToFailureTransportListener($bus);

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
        $listener = new SendFailedMessageToFailureTransportListener($bus);

        $envelope = new Envelope(new \stdClass());
        $event = new WorkerMessageFailedEvent($envelope, 'my_receiver', new \Exception(''), true);

        $listener->onMessageFailed($event);
    }
}
