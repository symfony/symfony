<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\MongoDb\Tests\Transport;

require_once __DIR__.'/../Stubs/mongodb.php';

use MongoDB\BSON\ObjectId;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\MongoDb\Stamp\MongoDbReceivedStamp;
use Symfony\Component\Messenger\Bridge\MongoDb\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Bridge\MongoDb\Transport\Connection;
use Symfony\Component\Messenger\Bridge\MongoDb\Transport\MongoDbSender;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\BatchSendFailedException;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

class MongoDbSenderTest extends TestCase
{
    public function testSend()
    {
        $envelope = new Envelope(new DummyMessage('Oy'));
        $encoded = ['body' => '...', 'headers' => ['type' => DummyMessage::class]];
        $objectId = new ObjectId();

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('send')
            ->with('...', ['type' => DummyMessage::class], 0, null)
            ->willReturn($objectId);

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->once())->method('encode')->with($envelope)->willReturn($encoded);

        $sender = new MongoDbSender($connection, $serializer);
        $envelope = $sender->send($envelope);

        $this->assertSame((string) $objectId, $envelope->last(TransportMessageIdStamp::class)->getId());
    }

    public function testARedeliveryIsSentBackToTheQueueItWasReceivedFrom()
    {
        $envelope = new Envelope(new DummyMessage('Oy'), [
            new MongoDbReceivedStamp('some_id', 'bar'),
            new RedeliveryStamp(1),
            new DelayStamp(500),
        ]);
        $encoded = ['body' => '...', 'headers' => ['type' => DummyMessage::class]];

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('send')
            ->with('...', ['type' => DummyMessage::class], 500, null, 'bar')
            ->willReturn(new ObjectId());

        $serializer = $this->createStub(SerializerInterface::class);
        $serializer->method('encode')->willReturn($encoded);

        (new MongoDbSender($connection, $serializer))->send($envelope);
    }

    public function testARedeliveryIsSentToTheTransportQueueWhenTheMessageWasNotClaimed()
    {
        $envelope = new Envelope(new DummyMessage('Oy'), [new RedeliveryStamp(1)]);
        $encoded = ['body' => '...'];

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('send')
            ->with('...', [], 0, null, null)
            ->willReturn(new ObjectId());

        $serializer = $this->createStub(SerializerInterface::class);
        $serializer->method('encode')->willReturn($encoded);

        (new MongoDbSender($connection, $serializer))->send($envelope);
    }

    public function testARedeliverySentToTheFailureTransportIsSentToTheTransportQueue()
    {
        $envelope = new Envelope(new DummyMessage('Oy'), [
            new MongoDbReceivedStamp('some_id', 'bar'),
            new RedeliveryStamp(1),
            new SentToFailureTransportStamp('default'),
        ]);
        $encoded = ['body' => '...'];

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('send')
            ->with('...', [], 0, null, null)
            ->willReturn(new ObjectId());

        $serializer = $this->createStub(SerializerInterface::class);
        $serializer->method('encode')->willReturn($encoded);

        (new MongoDbSender($connection, $serializer))->send($envelope);
    }

    public function testSendWithDelay()
    {
        $envelope = new Envelope(new DummyMessage('Oy'), [new DelayStamp(500)]);
        $encoded = ['body' => '...', 'headers' => ['type' => DummyMessage::class]];

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('send')
            ->with('...', ['type' => DummyMessage::class], 500, null)
            ->willReturn(new ObjectId());

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->once())->method('encode')->with($envelope)->willReturn($encoded);

        $sender = new MongoDbSender($connection, $serializer);
        $sender->send($envelope);
    }

    public function testSendBatch()
    {
        $envelopes = [
            'a' => new Envelope(new DummyMessage('a')),
            'b' => new Envelope(new DummyMessage('b'), [new MongoDbReceivedStamp('some_id', 'bar'), new RedeliveryStamp(1), new DelayStamp(500)]),
        ];
        $ids = ['a' => new ObjectId(), 'b' => new ObjectId()];

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('sendBatch')->with([
            'a' => ['body a', ['type' => DummyMessage::class], 0, null, null],
            'b' => ['body b', ['type' => DummyMessage::class], 500, null, 'bar'],
        ])->willReturn([$ids, []]);

        $serializer = $this->createStub(SerializerInterface::class);
        $serializer->method('encode')->willReturnCallback(static fn (Envelope $envelope) => ['body' => 'body '.$envelope->getMessage()->getMessage(), 'headers' => ['type' => DummyMessage::class]]);

        $sent = (new MongoDbSender($connection, $serializer))->sendBatch($envelopes);

        $this->assertSame(['a', 'b'], array_keys($sent));
        $this->assertSame((string) $ids['a'], $sent['a']->last(TransportMessageIdStamp::class)?->getId());
        $this->assertSame((string) $ids['b'], $sent['b']->last(TransportMessageIdStamp::class)?->getId());
    }

    public function testSendBatchTellsWhichMessagesWereSent()
    {
        $envelopes = ['a' => new Envelope(new DummyMessage('a')), 'b' => new Envelope(new DummyMessage('b'))];
        $id = new ObjectId();

        $connection = $this->createStub(Connection::class);
        $connection->method('sendBatch')->willReturn([['a' => $id], ['b' => $exception = new TransportException('Write failed.')]]);

        $serializer = $this->createStub(SerializerInterface::class);
        $serializer->method('encode')->willReturn(['body' => '...']);

        try {
            (new MongoDbSender($connection, $serializer))->sendBatch($envelopes);
            $this->fail('An exception should have been thrown.');
        } catch (BatchSendFailedException $e) {
            $this->assertSame(['a'], array_keys($e->getEnvelopes()));
            $this->assertSame((string) $id, $e->getEnvelopes()['a']->last(TransportMessageIdStamp::class)?->getId());
            $this->assertSame(['b' => $exception], $e->getExceptions());
        }
    }

    public function testSendWithoutHeaders()
    {
        $envelope = new Envelope(new DummyMessage('Oy'));
        $encoded = ['body' => '...'];

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('send')
            ->with('...', [], 0, null)
            ->willReturn(new ObjectId());

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->once())->method('encode')->with($envelope)->willReturn($encoded);

        $sender = new MongoDbSender($connection, $serializer);
        $sender->send($envelope);
    }
}
