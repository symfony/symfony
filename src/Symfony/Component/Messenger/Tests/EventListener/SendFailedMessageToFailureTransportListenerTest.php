<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\EventListener\SendFailedMessageToFailureTransportListener;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;

class SendFailedMessageToFailureTransportListenerTest extends TestCase
{
    public function testItSendsToTheFailureTransportWithSenderLocator()
    {
        $receiverName = 'my_receiver';
        $sender = $this->createMock(SenderInterface::class);
        $sender->expects($this->once())->method('send')->with($this->callback(function ($envelope) use ($receiverName) {
            /* @var Envelope $envelope */
            $this->assertInstanceOf(Envelope::class, $envelope);

            /** @var SentToFailureTransportStamp $sentToFailureTransportStamp */
            $sentToFailureTransportStamp = $envelope->last(SentToFailureTransportStamp::class);
            $this->assertNotNull($sentToFailureTransportStamp);
            $this->assertSame($receiverName, $sentToFailureTransportStamp->getOriginalReceiverName());

            return true;
        }))->willReturnArgument(0);

        $serviceLocator = $this->createMock(ServiceLocator::class);
        $serviceLocator->expects($this->once())->method('has')->willReturn(true);
        $serviceLocator->expects($this->once())->method('get')->with($receiverName)->willReturn($sender);
        $listener = new SendFailedMessageToFailureTransportListener($serviceLocator);

        $exception = new \Exception('no!');
        $envelope = new Envelope(new \stdClass());
        $event = new WorkerMessageFailedEvent($envelope, 'my_receiver', $exception);

        $listener->onMessageFailed($event);
    }

    public function testDoNothingOnRetryWithServiceLocator()
    {
        $sender = $this->createMock(SenderInterface::class);
        $sender->expects($this->never())->method('send');

        $serviceLocator = $this->createMock(ServiceLocator::class);
        $listener = new SendFailedMessageToFailureTransportListener($serviceLocator);

        $envelope = new Envelope(new \stdClass());
        $event = new WorkerMessageFailedEvent($envelope, 'my_receiver', new \Exception());
        $event->setForRetry();

        $listener->onMessageFailed($event);
    }

    public function testDoNotRedeliverToFailedWithServiceLocator()
    {
        $receiverName = 'my_receiver';

        $sender = $this->createMock(SenderInterface::class);
        $sender->expects($this->never())->method('send');
        $serviceLocator = $this->createMock(ServiceLocator::class);

        $listener = new SendFailedMessageToFailureTransportListener($serviceLocator);
        $envelope = new Envelope(new \stdClass(), [
            new SentToFailureTransportStamp($receiverName),
        ]);
        $event = new WorkerMessageFailedEvent($envelope, $receiverName, new \Exception());

        $listener->onMessageFailed($event);
    }

    public function testDoNothingIfFailureTransportIsNotDefined()
    {
        $sender = $this->createMock(SenderInterface::class);
        $sender->expects($this->never())->method('send');

        $serviceLocator = $this->createMock(ServiceLocator::class);
        $listener = new SendFailedMessageToFailureTransportListener($serviceLocator, null);

        $exception = new \Exception('no!');
        $envelope = new Envelope(new \stdClass());
        $event = new WorkerMessageFailedEvent($envelope, 'my_receiver', $exception);

        $listener->onMessageFailed($event);
    }

    public function testItSendsToTheFailureTransportWithMultipleFailedTransports()
    {
        $receiverName = 'my_receiver';
        $sender = $this->createMock(SenderInterface::class);
        $sender->expects($this->once())->method('send')->with($this->callback(function ($envelope) use ($receiverName) {
            /* @var Envelope $envelope */
            $this->assertInstanceOf(Envelope::class, $envelope);

            /** @var SentToFailureTransportStamp $sentToFailureTransportStamp */
            $sentToFailureTransportStamp = $envelope->last(SentToFailureTransportStamp::class);
            $this->assertNotNull($sentToFailureTransportStamp);
            $this->assertSame($receiverName, $sentToFailureTransportStamp->getOriginalReceiverName());

            return true;
        }))->willReturnArgument(0);

        $serviceLocator = $this->createMock(ServiceLocator::class);
        $serviceLocator->method('has')->with($receiverName)->willReturn(true);
        $serviceLocator->method('get')->with($receiverName)->willReturn($sender);

        $listener = new SendFailedMessageToFailureTransportListener($serviceLocator);

        $exception = new \Exception('no!');
        $envelope = new Envelope(new \stdClass());
        $event = new WorkerMessageFailedEvent($envelope, 'my_receiver', $exception);

        $listener->onMessageFailed($event);
    }
}
