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
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\EventListener\SendFailedMessageForRetryListener;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Messenger\Retry\RetryStrategyInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class SendFailedMessageForRetryListenerTest extends TestCase
{
    public function testNoRetryStrategyCausesNoRetry()
    {
        $senderLocator = self::createMock(ContainerInterface::class);
        $senderLocator->expects(self::never())->method('has');
        $senderLocator->expects(self::never())->method('get');
        $retryStrategyLocator = self::createMock(ContainerInterface::class);
        $retryStrategyLocator->expects(self::once())->method('has')->willReturn(false);

        $listener = new SendFailedMessageForRetryListener($senderLocator, $retryStrategyLocator);

        $exception = new \Exception('no!');
        $envelope = new Envelope(new \stdClass());
        $event = new WorkerMessageFailedEvent($envelope, 'my_receiver', $exception);

        $listener->onMessageFailed($event);
    }

    public function testRecoverableStrategyCausesRetry()
    {
        $sender = self::createMock(SenderInterface::class);
        $sender->expects(self::once())->method('send')->willReturnCallback(function (Envelope $envelope) {
            /** @var DelayStamp $delayStamp */
            $delayStamp = $envelope->last(DelayStamp::class);
            /** @var RedeliveryStamp $redeliveryStamp */
            $redeliveryStamp = $envelope->last(RedeliveryStamp::class);

            self::assertInstanceOf(DelayStamp::class, $delayStamp);
            self::assertSame(1000, $delayStamp->getDelay());

            self::assertInstanceOf(RedeliveryStamp::class, $redeliveryStamp);
            self::assertSame(1, $redeliveryStamp->getRetryCount());

            return $envelope;
        });
        $senderLocator = self::createMock(ContainerInterface::class);
        $senderLocator->expects(self::once())->method('has')->willReturn(true);
        $senderLocator->expects(self::once())->method('get')->willReturn($sender);
        $retryStategy = self::createMock(RetryStrategyInterface::class);
        $retryStategy->expects(self::never())->method('isRetryable');
        $retryStategy->expects(self::once())->method('getWaitingTime')->willReturn(1000);
        $retryStrategyLocator = self::createMock(ContainerInterface::class);
        $retryStrategyLocator->expects(self::once())->method('has')->willReturn(true);
        $retryStrategyLocator->expects(self::once())->method('get')->willReturn($retryStategy);

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

        $sender = self::createMock(SenderInterface::class);
        $sender->expects(self::once())->method('send')->willReturnCallback(function (Envelope $envelope) {
            /** @var DelayStamp $delayStamp */
            $delayStamp = $envelope->last(DelayStamp::class);
            /** @var RedeliveryStamp $redeliveryStamp */
            $redeliveryStamp = $envelope->last(RedeliveryStamp::class);

            self::assertInstanceOf(DelayStamp::class, $delayStamp);
            self::assertSame(1000, $delayStamp->getDelay());

            self::assertInstanceOf(RedeliveryStamp::class, $redeliveryStamp);
            self::assertSame(1, $redeliveryStamp->getRetryCount());

            return $envelope;
        });
        $senderLocator = self::createMock(ContainerInterface::class);
        $senderLocator->expects(self::once())->method('has')->willReturn(true);
        $senderLocator->expects(self::once())->method('get')->willReturn($sender);
        $retryStategy = self::createMock(RetryStrategyInterface::class);
        $retryStategy->expects(self::once())->method('isRetryable')->willReturn(true);
        $retryStategy->expects(self::once())->method('getWaitingTime')->willReturn(1000);
        $retryStrategyLocator = self::createMock(ContainerInterface::class);
        $retryStrategyLocator->expects(self::once())->method('has')->willReturn(true);
        $retryStrategyLocator->expects(self::once())->method('get')->willReturn($retryStategy);

        $eventDispatcher = self::createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::once())->method('dispatch');

        $listener = new SendFailedMessageForRetryListener($senderLocator, $retryStrategyLocator, null, $eventDispatcher);

        $event = new WorkerMessageFailedEvent($envelope, 'my_receiver', $exception);

        $listener->onMessageFailed($event);
    }

    public function testEnvelopeIsSentToTransportOnRetryWithExceptionPassedToRetryStrategy()
    {
        $exception = new \Exception('no!');
        $envelope = new Envelope(new \stdClass());

        $sender = self::createMock(SenderInterface::class);
        $sender->expects(self::once())->method('send')->willReturnCallback(function (Envelope $envelope) {
            /** @var DelayStamp $delayStamp */
            $delayStamp = $envelope->last(DelayStamp::class);
            /** @var RedeliveryStamp $redeliveryStamp */
            $redeliveryStamp = $envelope->last(RedeliveryStamp::class);

            self::assertInstanceOf(DelayStamp::class, $delayStamp);
            self::assertSame(1000, $delayStamp->getDelay());

            self::assertInstanceOf(RedeliveryStamp::class, $redeliveryStamp);
            self::assertSame(1, $redeliveryStamp->getRetryCount());

            return $envelope;
        });
        $senderLocator = self::createMock(ContainerInterface::class);
        $senderLocator->expects(self::once())->method('has')->willReturn(true);
        $senderLocator->expects(self::once())->method('get')->willReturn($sender);
        $retryStategy = self::createMock(RetryStrategyInterface::class);
        $retryStategy->expects(self::once())->method('isRetryable')->with($envelope, $exception)->willReturn(true);
        $retryStategy->expects(self::once())->method('getWaitingTime')->with($envelope, $exception)->willReturn(1000);
        $retryStrategyLocator = self::createMock(ContainerInterface::class);
        $retryStrategyLocator->expects(self::once())->method('has')->willReturn(true);
        $retryStrategyLocator->expects(self::once())->method('get')->willReturn($retryStategy);

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

        $sender = self::createMock(SenderInterface::class);
        $sender->expects(self::once())->method('send')->willReturnCallback(function (Envelope $envelope) {
            $delayStamps = $envelope->all(DelayStamp::class);
            $redeliveryStamps = $envelope->all(RedeliveryStamp::class);

            self::assertCount(10, $delayStamps);
            self::assertCount(4, $redeliveryStamps);

            return $envelope;
        });
        $senderLocator = self::createMock(ContainerInterface::class);
        $senderLocator->expects(self::once())->method('has')->willReturn(true);
        $senderLocator->expects(self::once())->method('get')->willReturn($sender);
        $retryStrategy = self::createMock(RetryStrategyInterface::class);
        $retryStrategy->expects(self::once())->method('isRetryable')->willReturn(true);
        $retryStrategy->expects(self::once())->method('getWaitingTime')->willReturn(1000);
        $retryStrategyLocator = self::createMock(ContainerInterface::class);
        $retryStrategyLocator->expects(self::once())->method('has')->willReturn(true);
        $retryStrategyLocator->expects(self::once())->method('get')->willReturn($retryStrategy);

        $listener = new SendFailedMessageForRetryListener($senderLocator, $retryStrategyLocator);

        $event = new WorkerMessageFailedEvent($envelope, 'my_receiver', $exception);

        $listener->onMessageFailed($event);
    }
}
