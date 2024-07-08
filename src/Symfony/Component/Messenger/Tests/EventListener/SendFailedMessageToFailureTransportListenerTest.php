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
use Symfony\Component\Messenger\Retry\RetryStrategyInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
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

            $delayStamp = $envelope->last(DelayStamp::class);
            $this->assertNotNull($delayStamp);
            $this->assertSame(5000, $delayStamp->getDelay());

            /** @var SentToFailureTransportStamp $sentToFailureTransportStamp */
            $sentToFailureTransportStamp = $envelope->last(SentToFailureTransportStamp::class);
            $this->assertNotNull($sentToFailureTransportStamp);
            $this->assertSame($receiverName, $sentToFailureTransportStamp->getOriginalReceiverName());

            return true;
        }))->willReturnArgument(0);

        $serviceLocator = $this->createMock(ServiceLocator::class);
        $serviceLocator->expects($this->once())->method('has')->willReturn(true);
        $serviceLocator->expects($this->once())->method('get')->with($receiverName)->willReturn($sender);

        $retryStrategy = $this->createMock(RetryStrategyInterface::class);
        $retryStrategy->expects($this->once())->method('getWaitingTime')->willReturn(5000);

        $retryStrategyLocator = $this->createMock(ServiceLocator::class);
        $retryStrategyLocator->expects($this->once())->method('has')->with($receiverName)->willReturn(true);
        $retryStrategyLocator->expects($this->once())->method('get')->with($receiverName)->willReturn($retryStrategy);

        $listener = new SendFailedMessageToFailureTransportListener($serviceLocator, null, $retryStrategyLocator);

        $exception = new \Exception('no!');
        $envelope = new Envelope(new \stdClass());
        $event = new WorkerMessageFailedEvent($envelope, 'my_receiver', $exception);

        $listener->onMessageFailed($event);
    }

    public function testItSendsToTheFailureTransportWithoutRetryStrategyLocator()
    {
        $sender = $this->createMock(SenderInterface::class);
        $sender->expects($this->once())->method('send')->with($this->callback(function (Envelope $envelope) {
            $this->assertSame(0, $envelope->last(DelayStamp::class)->getDelay());

            return true;
        }))->willReturnArgument(0);

        $serviceLocator = $this->createStub(ServiceLocator::class);
        $serviceLocator->method('has')->willReturn(true);
        $serviceLocator->method('get')->willReturn($sender);

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
        $retryStrategyLocator = $this->createStub(ServiceLocator::class);
        $listener = new SendFailedMessageToFailureTransportListener($serviceLocator, null, $retryStrategyLocator);

        $envelope = new Envelope(new \stdClass());
        $event = new WorkerMessageFailedEvent($envelope, 'my_receiver', new \Exception());
        $event->setForRetry();

        $listener->onMessageFailed($event);
    }

    public function testDoRedeliverToFailedWithServiceLocator()
    {
        $receiverName = 'my_receiver';
        $failedReceiver = 'failed_receiver';

        $sender = $this->createMock(SenderInterface::class);
        $sender->expects($this->once())->method('send')->with($this->callback(function (Envelope $envelope) use ($receiverName) {
            $delayStamp = $envelope->last(DelayStamp::class);
            $this->assertNotNull($delayStamp);
            $this->assertSame(1000, $delayStamp->getDelay());

            $sentToFailureTransportStamp = $envelope->last(SentToFailureTransportStamp::class);
            $this->assertNotNull($sentToFailureTransportStamp);
            $this->assertSame($receiverName, $sentToFailureTransportStamp->getOriginalReceiverName());

            return true;
        }))->willReturnArgument(0);
        $serviceLocator = $this->createMock(ServiceLocator::class);
        $serviceLocator->expects($this->once())->method('has')->willReturn(true);
        $serviceLocator->expects($this->once())->method('get')->with($receiverName)->willReturn($sender);

        $retryStrategy = $this->createStub(RetryStrategyInterface::class);
        $retryStrategy->method('getWaitingTime')->willReturn(1000);
        $retryStrategyLocator = $this->createMock(ServiceLocator::class);
        $retryStrategyLocator->expects($this->once())->method('has')->willReturn(true);
        $retryStrategyLocator->expects($this->once())->method('get')->with($failedReceiver)->willReturn($retryStrategy);

        $listener = new SendFailedMessageToFailureTransportListener($serviceLocator, null, $retryStrategyLocator);
        $envelope = new Envelope(new \stdClass(), [
            // the received stamp is assumed to be added by the FailedMessageProcessingMiddleware
            new ReceivedStamp($receiverName),
            new SentToFailureTransportStamp($receiverName),
        ]);
        $event = new WorkerMessageFailedEvent($envelope, $failedReceiver, new \Exception());

        $listener->onMessageFailed($event);
    }

    public function testDoNothingIfFailureTransportIsNotDefined()
    {
        $sender = $this->createMock(SenderInterface::class);
        $sender->expects($this->never())->method('send');

        $serviceLocator = $this->createMock(ServiceLocator::class);
        $listener = new SendFailedMessageToFailureTransportListener($serviceLocator);

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
