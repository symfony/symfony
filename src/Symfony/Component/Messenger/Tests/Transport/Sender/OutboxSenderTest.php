<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Transport\Sender;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\OutboxStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\Sender\OutboxSender;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;

class OutboxSenderTest extends TestCase
{
    public function testItStoresANewMessageInTheOutbox()
    {
        $target = $this->createMock(SenderInterface::class);
        $target->expects($this->never())->method('send');
        $outbox = new InMemoryTransport();

        $envelope = (new OutboxSender($target, $outbox, 'orders'))->send(new Envelope(new DummyMessage('Hey')));

        $this->assertCount(1, $stored = $outbox->getSent());
        $this->assertSame('orders', $stored[0]->last(OutboxStamp::class)?->getTransportName());
        $this->assertNull($envelope->last(OutboxStamp::class));
        $this->assertSame(1, $envelope->last(TransportMessageIdStamp::class)?->getId());
    }

    public function testItForwardsAMessageReceivedFromTheOutboxToTheTarget()
    {
        $target = new InMemoryTransport();
        $outbox = $this->createMock(SenderInterface::class);
        $outbox->expects($this->never())->method('send');
        $received = (new Envelope(new DummyMessage('Hey')))->with(new OutboxStamp('orders'), new TransportMessageIdStamp(42), new ReceivedStamp('outbox'));

        $envelope = (new OutboxSender($target, $outbox, 'orders'))->send($received);

        $this->assertCount(1, $forwarded = $target->getSent());
        $this->assertNull($forwarded[0]->last(OutboxStamp::class));
        $this->assertSame($received->getMessage(), $forwarded[0]->getMessage());
        $this->assertNull($envelope->last(OutboxStamp::class));
        $this->assertSame(42, $envelope->last(TransportMessageIdStamp::class)?->getId(), 'the relay worker acks the returned envelope on the outbox transport');
    }

    public function testItForwardsWithoutTheStampsAddedWhileInTheOutbox()
    {
        $target = new InMemoryTransport();
        $outbox = $this->createMock(SenderInterface::class);
        $outbox->expects($this->never())->method('send');
        $stamps = [new OutboxStamp('orders'), new DelayStamp(1000), new RedeliveryStamp(2), new ErrorDetailsStamp('Exception', 0, 'relay failed'), new SentToFailureTransportStamp('outbox'), new ReceivedStamp('outbox')];
        $received = (new Envelope(new DummyMessage('Hey')))->with(...$stamps);

        $envelope = (new OutboxSender($target, $outbox, 'orders'))->send($received);

        foreach ([OutboxStamp::class, DelayStamp::class, RedeliveryStamp::class, ErrorDetailsStamp::class, SentToFailureTransportStamp::class] as $stampFqcn) {
            $this->assertNull($target->getSent()[0]->last($stampFqcn), $stampFqcn.' must not reach the target');
            $this->assertNull($envelope->last($stampFqcn));
        }
    }

    public function testItSendsARedeliveredMessageToTheTargetDirectly()
    {
        $target = new InMemoryTransport();
        $outbox = $this->createMock(SenderInterface::class);
        $outbox->expects($this->never())->method('send');
        $redelivered = (new Envelope(new DummyMessage('Hey')))->with(new RedeliveryStamp(1), new DelayStamp(1000));

        $envelope = (new OutboxSender($target, $outbox, 'orders'))->send($redelivered);

        $this->assertCount(1, $target->getSent());
        $this->assertSame(1, $target->getSent()[0]->last(RedeliveryStamp::class)?->getRetryCount());
        $this->assertSame(1000, $target->getSent()[0]->last(DelayStamp::class)?->getDelay());
        $this->assertNull($envelope->last(OutboxStamp::class));
        $this->assertSame(1, $envelope->last(TransportMessageIdStamp::class)?->getId());
    }
}
