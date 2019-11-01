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

    public function testEnvelopeIsSentToTransportOnRetry()
    {
        $exception = new \Exception('no!');
        $envelope = new Envelope(new \stdClass());

        $sender = $this->createMock(SenderInterface::class);
        $sender->expects($this->once())->method('send')->with($envelope->with(new DelayStamp(1000), new RedeliveryStamp(1)))->willReturnArgument(0);
        $senderLocator = $this->createMock(ContainerInterface::class);
        $senderLocator->expects($this->once())->method('has')->willReturn(true);
        $senderLocator->expects($this->never())->method('get')->willReturn($sender);
        $retryStategy = $this->createMock(RetryStrategyInterface::class);
        $retryStategy->expects($this->once())->method('isRetryable')->willReturn(true);
        $retryStategy->expects($this->once())->method('getWaitingTime')->willReturn(1000);
        $retryStrategyLocator = $this->createMock(ContainerInterface::class);
        $retryStrategyLocator->expects($this->once())->method('has')->willReturn(true);
        $retryStrategyLocator->expects($this->once())->method('get')->willReturn($retryStategy);

        $listener = new SendFailedMessageForRetryListener($senderLocator, $retryStrategyLocator);

        $event = new WorkerMessageFailedEvent($envelope, 'my_receiver', $exception);

        $listener->onMessageFailed($event);
    }
}
