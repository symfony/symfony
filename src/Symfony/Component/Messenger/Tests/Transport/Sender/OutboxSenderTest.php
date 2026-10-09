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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\BatchSendFailedException;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\OutboxStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\DummySenderStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\Sender\BatchSenderInterface;
use Symfony\Component\Messenger\Transport\Sender\OutboxSender;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

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

    #[DataProvider('provideOutboxSerializers')]
    public function testItGivesTheSenderStampsStoredWithTheMessageToTheTarget(SerializerInterface $serializer)
    {
        $target = new InMemoryTransport();
        $outbox = new InMemoryTransport($serializer);
        $sender = new OutboxSender($target, $outbox, 'orders');

        $sender->send(new Envelope(new DummyMessage('Hey'), [new DummySenderStamp('a'), new DummySenderStamp('b')]));
        $sender->send($outbox->get()[0]);

        $this->assertEquals([new DummySenderStamp('a'), new DummySenderStamp('b')], $target->getSent()[0]->all(DummySenderStamp::class));
    }

    public static function provideOutboxSerializers(): iterable
    {
        yield 'PHP serializer' => [new PhpSerializer()];
        yield 'Symfony serializer' => [Serializer::create()];
    }

    public function testTheNewMessagesOfABatchKeepTheirSenderStamps()
    {
        $target = new InMemoryTransport();
        $outbox = new OutboxSenderTestBatchSender();
        $sender = new OutboxSender($target, $outbox, 'orders');
        $serializer = new PhpSerializer();

        $sender->sendBatch(['a' => new Envelope(new DummyMessage('a'), [new DummySenderStamp('a')])]);
        $sender->send($serializer->decode($serializer->encode($outbox->batches[0]['a'])));

        $this->assertEquals([new DummySenderStamp('a')], $target->getSent()[0]->all(DummySenderStamp::class));
    }

    public function testOnlySenderStampsAreRestored()
    {
        $target = new InMemoryTransport();
        $received = (new Envelope(new DummyMessage('Hey')))->with(new OutboxStamp('orders', [\ArrayObject::class => base64_encode(serialize([new \ArrayObject()]))]), new ReceivedStamp('outbox'));

        try {
            (new OutboxSender($target, new InMemoryTransport(), 'orders'))->send($received);
            $this->fail('A stamp that is not a sender stamp must not be restored.');
        } catch (UnrecoverableMessageHandlingException $e) {
            $this->assertSame('The outbox stamp of the message carries invalid "ArrayObject" stamps.', $e->getMessage());
        }

        $this->assertSame([], $target->getSent());
    }

    public function testASenderStampIsRestoredWithoutAnyOtherClass()
    {
        $target = new InMemoryTransport();
        $received = (new Envelope(new DummyMessage('Hey')))->with(new OutboxStamp('orders', [DummySenderStamp::class => base64_encode(serialize([new \ArrayObject()]))]), new ReceivedStamp('outbox'));

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessage(\sprintf('The outbox stamp of the message carries invalid "%s" stamps.', DummySenderStamp::class));

        (new OutboxSender($target, new InMemoryTransport(), 'orders'))->send($received);
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

    public function testItStoresTheNewMessagesOfABatchInTheOutboxWithOneBatch()
    {
        $target = $this->createMock(SenderInterface::class);
        $target->expects($this->never())->method('send');
        $outbox = new OutboxSenderTestBatchSender();

        $envelopes = (new OutboxSender($target, $outbox, 'orders'))->sendBatch([
            'a' => new Envelope(new DummyMessage('a')),
            'b' => new Envelope(new DummyMessage('b'), [new DelayStamp(1000)]),
        ]);

        $this->assertCount(1, $outbox->batches);
        $this->assertSame(['a', 'b'], array_keys($outbox->batches[0]));
        $this->assertSame('orders', $outbox->batches[0]['a']->last(OutboxStamp::class)?->getTransportName());
        $this->assertSame('orders', $outbox->batches[0]['b']->last(OutboxStamp::class)?->getTransportName());
        $this->assertSame(1000, $outbox->batches[0]['b']->last(DelayStamp::class)?->getDelay());
        $this->assertSame(['a', 'b'], array_keys($envelopes));
        $this->assertNull($envelopes['a']->last(OutboxStamp::class));
        $this->assertNull($envelopes['b']->last(OutboxStamp::class));
        $this->assertSame('outbox-a', $envelopes['a']->last(TransportMessageIdStamp::class)?->getId());
        $this->assertSame('outbox-b', $envelopes['b']->last(TransportMessageIdStamp::class)?->getId());
    }

    public function testItSendsTheRelayedAndRedeliveredMessagesOfABatchToTheTargetOneByOne()
    {
        $target = new InMemoryTransport();
        $outbox = new OutboxSenderTestBatchSender();

        $envelopes = (new OutboxSender($target, $outbox, 'orders'))->sendBatch([
            'relayed' => (new Envelope(new DummyMessage('relayed')))->with(new OutboxStamp('orders'), new DelayStamp(1000), new TransportMessageIdStamp(42), new ReceivedStamp('outbox')),
            'new' => new Envelope(new DummyMessage('new')),
            'redelivered' => (new Envelope(new DummyMessage('redelivered')))->with(new RedeliveryStamp(1)),
        ]);

        $this->assertSame([['new']], array_map(array_keys(...), $outbox->batches));
        $this->assertSame(['relayed', 'redelivered'], array_map(static fn (Envelope $envelope) => $envelope->getMessage()->getMessage(), $target->getSent()));
        $this->assertNull($target->getSent()[0]->last(OutboxStamp::class));
        $this->assertNull($target->getSent()[0]->last(DelayStamp::class));
        $this->assertEqualsCanonicalizing(['relayed', 'new', 'redelivered'], array_keys($envelopes));
        $this->assertSame(42, $envelopes['relayed']->last(TransportMessageIdStamp::class)?->getId());
        $this->assertSame('outbox-new', $envelopes['new']->last(TransportMessageIdStamp::class)?->getId());
        $this->assertNull($envelopes['new']->last(OutboxStamp::class));
        $this->assertSame(2, $envelopes['redelivered']->last(TransportMessageIdStamp::class)?->getId());
    }

    public function testItStoresABatchOneByOneWhenTheOutboxCannotSendBatches()
    {
        $target = $this->createMock(SenderInterface::class);
        $target->expects($this->never())->method('send');
        $outbox = $this->createMock(SenderInterface::class);
        $outbox->expects($this->exactly(2))->method('send')->willReturnCallback(static fn (Envelope $envelope) => $envelope->with(new TransportMessageIdStamp($envelope->last(OutboxStamp::class)?->getTransportName().'-'.$envelope->getMessage()->getMessage())));

        $envelopes = (new OutboxSender($target, $outbox, 'orders'))->sendBatch(['a' => new Envelope(new DummyMessage('a')), 'b' => new Envelope(new DummyMessage('b'))]);

        $this->assertSame(['a', 'b'], array_keys($envelopes));
        $this->assertSame('orders-a', $envelopes['a']->last(TransportMessageIdStamp::class)?->getId());
        $this->assertSame('orders-b', $envelopes['b']->last(TransportMessageIdStamp::class)?->getId());
        $this->assertNull($envelopes['a']->last(OutboxStamp::class));
    }

    public function testItReportsTheMessagesTheOutboxFailedToStore()
    {
        $target = new InMemoryTransport();
        $outbox = new OutboxSenderTestBatchSender();
        $outbox->failures = ['b' => $failure = new TransportException('Refused.')];

        try {
            (new OutboxSender($target, $outbox, 'orders'))->sendBatch([
                'a' => new Envelope(new DummyMessage('a')),
                'b' => new Envelope(new DummyMessage('b')),
                'redelivered' => (new Envelope(new DummyMessage('redelivered')))->with(new RedeliveryStamp(1)),
            ]);
            $this->fail('An exception should have been thrown.');
        } catch (BatchSendFailedException $e) {
        }

        $this->assertSame(['b' => $failure], $e->getExceptions());
        $this->assertEqualsCanonicalizing(['a', 'redelivered'], array_keys($e->getEnvelopes()));
        $this->assertSame('outbox-a', $e->getEnvelopes()['a']->last(TransportMessageIdStamp::class)?->getId());
        $this->assertNull($e->getEnvelopes()['a']->last(OutboxStamp::class));
        $this->assertCount(1, $target->getSent());
    }

    public function testNothingIsSentWhenTheOutboxFailsToStoreTheBatch()
    {
        $target = $this->createMock(SenderInterface::class);
        $target->expects($this->never())->method('send');
        $outbox = new OutboxSenderTestBatchSender();
        $outbox->exception = $failure = new TransportException('Down.');

        try {
            (new OutboxSender($target, $outbox, 'orders'))->sendBatch([
                'a' => new Envelope(new DummyMessage('a')),
                'redelivered' => (new Envelope(new DummyMessage('redelivered')))->with(new RedeliveryStamp(1)),
            ]);
            $this->fail('An exception should have been thrown.');
        } catch (TransportException $e) {
            $this->assertSame($failure, $e);
        }
    }

    public function testSendingOneByOneStopsAtTheFirstFailure()
    {
        $target = $this->createMock(SenderInterface::class);
        $target->expects($this->once())->method('send')->willThrowException($failure = new TransportException('Down.'));
        $outbox = new OutboxSenderTestBatchSender();

        try {
            (new OutboxSender($target, $outbox, 'orders'))->sendBatch([
                'first' => (new Envelope(new DummyMessage('first')))->with(new RedeliveryStamp(1)),
                'new' => new Envelope(new DummyMessage('new')),
                'second' => (new Envelope(new DummyMessage('second')))->with(new RedeliveryStamp(1)),
            ]);
            $this->fail('An exception should have been thrown.');
        } catch (BatchSendFailedException $e) {
        }

        $this->assertSame(['first' => $failure, 'second' => $failure], $e->getExceptions());
        $this->assertSame(['new'], array_keys($e->getEnvelopes()));
    }
}

class OutboxSenderTestBatchSender implements BatchSenderInterface
{
    public array $batches = [];
    public array $failures = [];
    public ?\Throwable $exception = null;

    public function send(Envelope $envelope): Envelope
    {
        throw new \LogicException('Batches must be sent with sendBatch().');
    }

    public function sendBatch(array $envelopes): array
    {
        $this->batches[] = $envelopes;

        if ($this->exception) {
            throw $this->exception;
        }

        $sent = array_map(static fn (Envelope $envelope) => $envelope->with(new TransportMessageIdStamp('outbox-'.$envelope->getMessage()->getMessage())), array_diff_key($envelopes, $this->failures));

        if ($failures = array_intersect_key($this->failures, $envelopes)) {
            throw new BatchSendFailedException($sent, $failures);
        }

        return $sent;
    }
}
