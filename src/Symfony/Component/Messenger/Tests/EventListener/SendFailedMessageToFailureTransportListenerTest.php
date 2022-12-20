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
    /**
     * @group legacy
     */
    public function testItSendsToTheFailureTransport()
    {
        $sender = self::createMock(SenderInterface::class);
        $sender->expects(self::once())->method('send')->with(self::callback(function ($envelope) {
            /* @var Envelope $envelope */
            self::assertInstanceOf(Envelope::class, $envelope);

            /** @var SentToFailureTransportStamp $sentToFailureTransportStamp */
            $sentToFailureTransportStamp = $envelope->last(SentToFailureTransportStamp::class);
            self::assertNotNull($sentToFailureTransportStamp);
            self::assertSame('my_receiver', $sentToFailureTransportStamp->getOriginalReceiverName());

            return true;
        }))->willReturnArgument(0);
        $listener = new SendFailedMessageToFailureTransportListener($sender);

        $exception = new \Exception('no!');
        $envelope = new Envelope(new \stdClass());
        $event = new WorkerMessageFailedEvent($envelope, 'my_receiver', $exception);

        $listener->onMessageFailed($event);
    }

    public function testItSendsToTheFailureTransportWithSenderLocator()
    {
        $receiverName = 'my_receiver';
        $sender = self::createMock(SenderInterface::class);
        $sender->expects(self::once())->method('send')->with(self::callback(function ($envelope) use ($receiverName) {
            /* @var Envelope $envelope */
            self::assertInstanceOf(Envelope::class, $envelope);

            /** @var SentToFailureTransportStamp $sentToFailureTransportStamp */
            $sentToFailureTransportStamp = $envelope->last(SentToFailureTransportStamp::class);
            self::assertNotNull($sentToFailureTransportStamp);
            self::assertSame($receiverName, $sentToFailureTransportStamp->getOriginalReceiverName());

            return true;
        }))->willReturnArgument(0);

        $serviceLocator = self::createMock(ServiceLocator::class);
        $serviceLocator->expects(self::once())->method('has')->willReturn(true);
        $serviceLocator->expects(self::once())->method('get')->with($receiverName)->willReturn($sender);
        $listener = new SendFailedMessageToFailureTransportListener($serviceLocator);

        $exception = new \Exception('no!');
        $envelope = new Envelope(new \stdClass());
        $event = new WorkerMessageFailedEvent($envelope, 'my_receiver', $exception);

        $listener->onMessageFailed($event);
    }

    /**
     * @group legacy
     */
    public function testDoNothingOnRetry()
    {
        $sender = self::createMock(SenderInterface::class);
        $sender->expects(self::never())->method('send');
        $listener = new SendFailedMessageToFailureTransportListener($sender);
        $envelope = new Envelope(new \stdClass());
        $event = new WorkerMessageFailedEvent($envelope, 'my_receiver', new \Exception());
        $event->setForRetry();

        $listener->onMessageFailed($event);
    }

    public function testDoNothingOnRetryWithServiceLocator()
    {
        $sender = self::createMock(SenderInterface::class);
        $sender->expects(self::never())->method('send');

        $serviceLocator = self::createMock(ServiceLocator::class);
        $listener = new SendFailedMessageToFailureTransportListener($serviceLocator);

        $envelope = new Envelope(new \stdClass());
        $event = new WorkerMessageFailedEvent($envelope, 'my_receiver', new \Exception());
        $event->setForRetry();

        $listener->onMessageFailed($event);
    }

    /**
     * @group legacy
     */
    public function testDoNotRedeliverToFailed()
    {
        $sender = self::createMock(SenderInterface::class);
        $sender->expects(self::never())->method('send');
        $listener = new SendFailedMessageToFailureTransportListener($sender);

        $envelope = new Envelope(new \stdClass(), [
            new SentToFailureTransportStamp('my_receiver'),
        ]);
        $event = new WorkerMessageFailedEvent($envelope, 'my_receiver', new \Exception());

        $listener->onMessageFailed($event);
    }

    public function testDoNotRedeliverToFailedWithServiceLocator()
    {
        $receiverName = 'my_receiver';

        $sender = self::createMock(SenderInterface::class);
        $sender->expects(self::never())->method('send');
        $serviceLocator = self::createMock(ServiceLocator::class);

        $listener = new SendFailedMessageToFailureTransportListener($serviceLocator);
        $envelope = new Envelope(new \stdClass(), [
            new SentToFailureTransportStamp($receiverName),
        ]);
        $event = new WorkerMessageFailedEvent($envelope, $receiverName, new \Exception());

        $listener->onMessageFailed($event);
    }

    public function testDoNothingIfFailureTransportIsNotDefined()
    {
        $sender = self::createMock(SenderInterface::class);
        $sender->expects(self::never())->method('send');

        $serviceLocator = self::createMock(ServiceLocator::class);
        $listener = new SendFailedMessageToFailureTransportListener($serviceLocator, null);

        $exception = new \Exception('no!');
        $envelope = new Envelope(new \stdClass());
        $event = new WorkerMessageFailedEvent($envelope, 'my_receiver', $exception);

        $listener->onMessageFailed($event);
    }

    public function testItSendsToTheFailureTransportWithMultipleFailedTransports()
    {
        $receiverName = 'my_receiver';
        $sender = self::createMock(SenderInterface::class);
        $sender->expects(self::once())->method('send')->with(self::callback(function ($envelope) use ($receiverName) {
            /* @var Envelope $envelope */
            self::assertInstanceOf(Envelope::class, $envelope);

            /** @var SentToFailureTransportStamp $sentToFailureTransportStamp */
            $sentToFailureTransportStamp = $envelope->last(SentToFailureTransportStamp::class);
            self::assertNotNull($sentToFailureTransportStamp);
            self::assertSame($receiverName, $sentToFailureTransportStamp->getOriginalReceiverName());

            return true;
        }))->willReturnArgument(0);

        $serviceLocator = self::createMock(ServiceLocator::class);
        $serviceLocator->method('has')->with($receiverName)->willReturn(true);
        $serviceLocator->method('get')->with($receiverName)->willReturn($sender);

        $listener = new SendFailedMessageToFailureTransportListener($serviceLocator);

        $exception = new \Exception('no!');
        $envelope = new Envelope(new \stdClass());
        $event = new WorkerMessageFailedEvent($envelope, 'my_receiver', $exception);

        $listener->onMessageFailed($event);
    }
}
