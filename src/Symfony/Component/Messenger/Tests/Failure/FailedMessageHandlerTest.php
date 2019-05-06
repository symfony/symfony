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
use Symfony\Component\Messenger\Failure\FailedMessage;
use Symfony\Component\Messenger\Failure\FailedMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentStamp;

class FailedMessageHandlerTest extends TestCase
{
    public function testItDispatchesOriginalEnvelope()
    {
        $failedEnvelope = $envelope = new Envelope(new \stdClass(), [new SentStamp('Some\\Sender', 'sender_alias')]);
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())->method('dispatch')->with($this->callback(function ($envelope) use ($failedEnvelope) {
            /* @var Envelope $envelope */
            $this->assertSame($failedEnvelope->getMessage(), $envelope->getMessage());

            $this->assertNull($envelope->last(ReceivedStamp::class));
            $this->assertNull($envelope->last(DelayStamp::class));
            $this->assertEquals(new RedeliveryStamp(0, 'sender_alias'), $envelope->last(RedeliveryStamp::class));

            return true;
        }))->willReturn(new Envelope(new \stdClass()));

        $handler = new FailedMessageHandler($bus);
        $failedMessage = new FailedMessage($failedEnvelope, 'Things went wrong');
        $failedMessage->setToResendStrategy();
        $handler($failedMessage);
    }

    public function testItDispatchesForARetry()
    {
        $failedEnvelope = $envelope = new Envelope(new \stdClass());
        $failedEnvelope = $failedEnvelope->with(new ReceivedStamp('some_transport'));

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())->method('dispatch')->with($this->callback(function ($envelope) use ($failedEnvelope) {
            /* @var Envelope $envelope */
            $this->assertSame($failedEnvelope->getMessage(), $envelope->getMessage());

            $this->assertSame($failedEnvelope->last(ReceivedStamp::class), $envelope->last(ReceivedStamp::class));

            return true;
        }))->willReturn(new Envelope(new \stdClass()));

        $handler = new FailedMessageHandler($bus);
        $failedMessage = new FailedMessage($failedEnvelope, 'Things went wrong');
        $failedMessage->setToRetryStrategy();
        $handler($failedMessage);
    }
}
