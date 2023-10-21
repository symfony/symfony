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
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\EventListener\SendFailedMessageForRetryListener;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Messenger\Retry\RetryStrategyInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;

class SendFailedMessageForRetryListenerTest extends TestCase
{
    public function testNoRetryStrategyCausesNoRetry()
    {
        $senderLocator = $this->createMock(ContainerInterface::class);
        $senderLocator->expects($this->never())->method('has');
        $senderLocator->expects($this->never())->method('get');
        $retryStrategyLocator = $this->createMock(ContainerInterface::class);
        $retryStrategyLocator->expects($this->once())->method('has')->willReturn(false);

        $listener = new SendFailedMessageForRetryListener($senderLocator, $retryStrategyLocator);

        $exception = new \Exception('no!');
        $envelope = new Envelope(new \stdClass());
        $event = new WorkerMessageFailedEvent($envelope, 'my_receiver', $exception);

        $listener->onMessageFailed($event);
    }

    public function testRecoverableStrategyCausesRetry()
    {
        $sender = $this->createMock(SenderInterface::class);
        $sender->expects($this->once())->method('send')->willReturnCallback(function (Envelope $envelope) {
            /** @var DelayStamp $delayStamp */
            $delayStamp = $envelope->last(DelayStamp::class);
            /** @var RedeliveryStamp $redeliveryStamp */
            $redeliveryStamp = $envelope->last(RedeliveryStamp::class);

            $this->assertInstanceOf(DelayStamp::class, $delayStamp);
            $this->assertSame(1000, $delayStamp->getDelay());

            $this->assertInstanceOf(RedeliveryStamp::class, $redeliveryStamp);
            $this->assertSame(1, $redeliveryStamp->getRetryCount());

            return $envelope;
        });
        $senderLocator = $this->createMock(ContainerInterface::class);
        $senderLocator->expects($this->once())->method('has')->willReturn(true);
        $senderLocator->expects($this->once())->method('get')->willReturn($sender);
        $retryStategy = $this->createMock(RetryStrategyInterface::class);
        $retryStategy->expects($this->never())->method('isRetryable');
        $retryStategy->expects($this->once())->method('getWaitingTime')->willReturn(1000);
        $retryStrategyLocator = $this->createMock(ContainerInterface::class);
        $retryStrategyLocator->expects($this->once())->method('has')->willReturn(true);
        $retryStrategyLocator->expects($this->once())->method('get')->willReturn($retryStategy);

        $listener = new SendFailedMessageForRetryListener($senderLocator, $retryStrategyLocator);

        $exception = new RecoverableMessageHandlingException('retry');
        $envelope = new Envelope(new \stdClass());
        $event = new WorkerMessageFailedEvent($envelope, 'my_receiver', $exception);

        $listener->onMessageFailed($event);
    }

    public function testEnvelopeIsSentToTransportOnRetry()
    {
        $exception = new \Exception('no!');
        $envelope = new Envelope(new \stdClass());

        $sender = $this->createMock(SenderInterface::class);
        $sender->expects($this->once())->method('send')->willReturnCallback(function (Envelope $envelope) {
            /** @var DelayStamp $delayStamp */
            $delayStamp = $envelope->last(DelayStamp::class);
            /** @var RedeliveryStamp $redeliveryStamp */
            $redeliveryStamp = $envelope->last(RedeliveryStamp::class);

            $this->assertInstanceOf(DelayStamp::class, $delayStamp);
            $this->assertSame(1000, $delayStamp->getDelay());

            $this->assertInstanceOf(RedeliveryStamp::class, $redeliveryStamp);
            $this->assertSame(1, $redeliveryStamp->getRetryCount());

            return $envelope;
        });
        $senderLocator = $this->createMock(ContainerInterface::class);
        $senderLocator->expects($this->once())->method('has')->willReturn(true);
        $senderLocator->expects($this->once())->method('get')->willReturn($sender);
        $retryStategy = $this->createMock(RetryStrategyInterface::class);
        $retryStategy->expects($this->once())->method('isRetryable')->willReturn(true);
        $retryStategy->expects($this->once())->method('getWaitingTime')->willReturn(1000);
        $retryStrategyLocator = $this->createMock(ContainerInterface::class);
        $retryStrategyLocator->expects($this->once())->method('has')->willReturn(true);
        $retryStrategyLocator->expects($this->once())->method('get')->willReturn($retryStategy);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())->method('dispatch');

        $listener = new SendFailedMessageForRetryListener($senderLocator, $retryStrategyLocator, null, $eventDispatcher);

        $event = new WorkerMessageFailedEvent($envelope, 'my_receiver', $exception);

        $listener->onMessageFailed($event);
    }

    public function testEnvelopeIsSentToTransportOnRetryWithExceptionPassedToRetryStrategy()
    {
        $exception = new \Exception('no!');
        $envelope = new Envelope(new \stdClass());

        $sender = $this->createMock(SenderInterface::class);
        $sender->expects($this->once())->method('send')->willReturnCallback(function (Envelope $envelope) {
            /** @var DelayStamp $delayStamp */
            $delayStamp = $envelope->last(DelayStamp::class);
            /** @var RedeliveryStamp $redeliveryStamp */
            $redeliveryStamp = $envelope->last(RedeliveryStamp::class);

            $this->assertInstanceOf(DelayStamp::class, $delayStamp);
            $this->assertSame(1000, $delayStamp->getDelay());

            $this->assertInstanceOf(RedeliveryStamp::class, $redeliveryStamp);
            $this->assertSame(1, $redeliveryStamp->getRetryCount());

            return $envelope;
        });
        $senderLocator = $this->createMock(ContainerInterface::class);
        $senderLocator->expects($this->once())->method('has')->willReturn(true);
        $senderLocator->expects($this->once())->method('get')->willReturn($sender);
        $retryStategy = $this->createMock(RetryStrategyInterface::class);
        $retryStategy->expects($this->once())->method('isRetryable')->with($envelope, $exception)->willReturn(true);
        $retryStategy->expects($this->once())->method('getWaitingTime')->with($envelope, $exception)->willReturn(1000);
        $retryStrategyLocator = $this->createMock(ContainerInterface::class);
        $retryStrategyLocator->expects($this->once())->method('has')->willReturn(true);
        $retryStrategyLocator->expects($this->once())->method('get')->willReturn($retryStategy);

        $listener = new SendFailedMessageForRetryListener($senderLocator, $retryStrategyLocator);

        $event = new WorkerMessageFailedEvent($envelope, 'my_receiver', $exception);

        $listener->onMessageFailed($event);
    }

    public function testEnvelopeKeepOnlyTheLast10Stamps()
    {
        $exception = new \Exception('no!');
        $stamps = array_merge(
            array_fill(0, 15, new DelayStamp(1)),
            array_fill(0, 3, new RedeliveryStamp(1))
        );
        $envelope = new Envelope(new \stdClass(), $stamps);

        $sender = $this->createMock(SenderInterface::class);
        $sender->expects($this->once())->method('send')->willReturnCallback(function (Envelope $envelope) {
            $delayStamps = $envelope->all(DelayStamp::class);
            $redeliveryStamps = $envelope->all(RedeliveryStamp::class);

            $this->assertCount(10, $delayStamps);
            $this->assertCount(4, $redeliveryStamps);

            return $envelope;
        });
        $senderLocator = $this->createMock(ContainerInterface::class);
        $senderLocator->expects($this->once())->method('has')->willReturn(true);
        $senderLocator->expects($this->once())->method('get')->willReturn($sender);
        $retryStrategy = $this->createMock(RetryStrategyInterface::class);
        $retryStrategy->expects($this->once())->method('isRetryable')->willReturn(true);
        $retryStrategy->expects($this->once())->method('getWaitingTime')->willReturn(1000);
        $retryStrategyLocator = $this->createMock(ContainerInterface::class);
        $retryStrategyLocator->expects($this->once())->method('has')->willReturn(true);
        $retryStrategyLocator->expects($this->once())->method('get')->willReturn($retryStrategy);

        $listener = new SendFailedMessageForRetryListener($senderLocator, $retryStrategyLocator);

        $event = new WorkerMessageFailedEvent($envelope, 'my_receiver', $exception);

        $listener->onMessageFailed($event);
    }
}
