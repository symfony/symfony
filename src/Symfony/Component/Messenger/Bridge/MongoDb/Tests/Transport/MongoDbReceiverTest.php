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
use MongoDB\Model\BSONDocument;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\MongoDb\Stamp\MongoDbReceivedStamp;
use Symfony\Component\Messenger\Bridge\MongoDb\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Bridge\MongoDb\Transport\Connection;
use Symfony\Component\Messenger\Bridge\MongoDb\Transport\MongoDbReceiver;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\LogicException;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

class MongoDbReceiverTest extends TestCase
{
    public function testItReturnsTheDecodedMessageToTheHandler()
    {
        $serializer = new PhpSerializer();
        $document = $this->createDocument($serializer->encode(new Envelope(new DummyMessage('Hi'))));

        $connection = $this->createStub(Connection::class);
        $connection->method('get')->willReturn($document);

        $receiver = new MongoDbReceiver($connection, $serializer);
        $envelopes = iterator_to_array($receiver->get());
        $this->assertCount(1, $envelopes);

        $envelope = $envelopes[0];
        $message = $envelope->getMessage();
        $this->assertInstanceOf(DummyMessage::class, $message);
        $this->assertSame('Hi', $message->getMessage());
        $this->assertSame((string) $document->_id, $envelope->last(MongoDbReceivedStamp::class)->getId());
        $this->assertSame((string) $document->_id, $envelope->last(TransportMessageIdStamp::class)->getId());
    }

    public function testItReturnsEmptyWhenThereAreNoMessages()
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('get')->willReturn(null);

        $receiver = new MongoDbReceiver($connection, $this->createStub(SerializerInterface::class));

        $this->assertSame(0, iterator_count($receiver->get()));
    }

    public function testItReturnsTheDecodedMessageFromTheGivenQueues()
    {
        $serializer = new PhpSerializer();
        $document = $this->createDocument($serializer->encode(new Envelope(new DummyMessage('Hi'))));

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('get')
            ->with(['foo', 'bar'])
            ->willReturn($document);

        $receiver = new MongoDbReceiver($connection, $serializer);
        $envelopes = iterator_to_array($receiver->getFromQueues(['foo', 'bar']));

        $this->assertCount(1, $envelopes);
        $this->assertSame('Hi', $envelopes[0]->getMessage()->getMessage());
        $this->assertSame((string) $document->_id, $envelopes[0]->last(MongoDbReceivedStamp::class)->getId());
    }

    public function testItReturnsEmptyFromTheGivenQueuesWhenThereAreNoMessages()
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('get')->willReturn(null);

        $receiver = new MongoDbReceiver($connection, $this->createStub(SerializerInterface::class));

        $this->assertSame(0, iterator_count($receiver->getFromQueues(['foo', 'bar'])));
    }

    public function testItFetchesSeveralMessagesFromTheGivenQueues()
    {
        $serializer = new PhpSerializer();
        $document1 = $this->createDocument($serializer->encode(new Envelope(new DummyMessage('Hi'))));
        $document2 = $this->createDocument($serializer->encode(new Envelope(new DummyMessage('Ho'))));

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->exactly(2))
            ->method('get')
            ->with(['foo', 'bar'])
            ->willReturnOnConsecutiveCalls($document1, $document2);

        $receiver = new MongoDbReceiver($connection, $serializer);
        $envelopes = iterator_to_array($receiver->getFromQueues(['foo', 'bar'], 2));

        $this->assertCount(2, $envelopes);
        $this->assertSame('Hi', $envelopes[0]->getMessage()->getMessage());
        $this->assertSame('Ho', $envelopes[1]->getMessage()->getMessage());
    }

    public function testItClaimsMessagesAsTheyAreConsumed()
    {
        $serializer = new PhpSerializer();
        $document = $this->createDocument($serializer->encode(new Envelope(new DummyMessage('Hi'))));
        $claims = 0;

        $connection = $this->createStub(Connection::class);
        $connection->method('get')->willReturnCallback(static function () use (&$claims, $document) {
            ++$claims;

            return $document;
        });

        $receiver = new MongoDbReceiver($connection, $serializer);
        $envelopes = $receiver->getFromQueues(['foo'], 3);
        $this->assertSame(0, $claims);

        $envelopes->rewind();
        $this->assertSame(1, $claims);

        $envelopes->next();
        $this->assertSame(2, $claims);
    }

    public function testItClaimsMessagesFromTheConfiguredQueueAsTheyAreConsumed()
    {
        $serializer = new PhpSerializer();
        $document = $this->createDocument($serializer->encode(new Envelope(new DummyMessage('Hi'))));
        $claims = 0;

        $connection = $this->createStub(Connection::class);
        $connection->method('get')->willReturnCallback(static function () use (&$claims, $document) {
            ++$claims;

            return $document;
        });

        $receiver = new MongoDbReceiver($connection, $serializer);
        $envelopes = $receiver->get(3);
        $this->assertSame(0, $claims);

        foreach ($envelopes as $envelope) {
            break;
        }
        $this->assertSame(1, $claims);
    }

    #[DataProvider('provideNonPositiveFetchSizes')]
    public function testItFetchesAtLeastOneMessageWhenTheFetchSizeIsNotPositive(int $fetchSize)
    {
        $serializer = new PhpSerializer();
        $document = $this->createDocument($serializer->encode(new Envelope(new DummyMessage('Hi'))));

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('get')
            ->willReturn($document);

        $receiver = new MongoDbReceiver($connection, $serializer);

        $this->assertSame(1, iterator_count($receiver->get($fetchSize)));
    }

    #[DataProvider('provideNonPositiveFetchSizes')]
    public function testItFetchesAtLeastOneMessageFromTheGivenQueuesWhenTheFetchSizeIsNotPositive(int $fetchSize)
    {
        $serializer = new PhpSerializer();
        $document = $this->createDocument($serializer->encode(new Envelope(new DummyMessage('Hi'))));

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('get')
            ->with(['foo', 'bar'])
            ->willReturn($document);

        $receiver = new MongoDbReceiver($connection, $serializer);

        $this->assertSame(1, iterator_count($receiver->getFromQueues(['foo', 'bar'], $fetchSize)));
    }

    public static function provideNonPositiveFetchSizes(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
    }

    public function testItStampsTheQueueTheMessageWasClaimedFrom()
    {
        $serializer = new PhpSerializer();
        $document = $this->createDocument($serializer->encode(new Envelope(new DummyMessage('Hi'))));
        $document->queueName = 'bar';

        $connection = $this->createStub(Connection::class);
        $connection->method('get')->willReturn($document);

        $receiver = new MongoDbReceiver($connection, $serializer);
        $envelopes = iterator_to_array($receiver->getFromQueues(['bar']));

        $this->assertSame('bar', $envelopes[0]->last(MongoDbReceivedStamp::class)->getQueueName());
    }

    public function testItReturnsTheEncodedEnvelopeWhenDecodingFails()
    {
        $document = $this->createDocument(['body' => 'foo', 'headers' => ['type' => 'App\Missing']]);

        $connection = $this->createMock(Connection::class);
        $connection->method('get')->willReturn($document);
        $connection->expects($this->never())->method('reject');

        $serializer = $this->createStub(SerializerInterface::class);
        $serializer->method('decode')->willThrowException(new MessageDecodingFailedException('Class not found.'));

        $receiver = new MongoDbReceiver($connection, $serializer);
        $envelopes = iterator_to_array($receiver->get());

        $this->assertCount(1, $envelopes);
        $failure = $envelopes[0]->getMessage();
        $this->assertInstanceOf(MessageDecodingFailedException::class, $failure);
        $this->assertSame('Class not found.', $failure->getMessage());
        $this->assertSame(['body' => 'foo', 'headers' => ['type' => 'App\Missing']], $failure->encodedEnvelope);
        $this->assertSame((string) $document->_id, $envelopes[0]->last(MongoDbReceivedStamp::class)->getId());
        $this->assertSame((string) $document->_id, $envelopes[0]->last(TransportMessageIdStamp::class)->getId());
    }

    public function testListingKeepsTheMessagesThatCannotBeDecoded()
    {
        $document = $this->createDocument(['body' => 'foo']);

        $connection = $this->createMock(Connection::class);
        $connection->method('findAll')->willReturn([$document]);
        $connection->method('find')->willReturn($document);
        $connection->expects($this->never())->method('reject');

        $serializer = $this->createStub(SerializerInterface::class);
        $serializer->method('decode')->willThrowException(new MessageDecodingFailedException());

        $receiver = new MongoDbReceiver($connection, $serializer);

        $this->assertInstanceOf(MessageDecodingFailedException::class, iterator_to_array($receiver->all())[0]->getMessage());
        $this->assertInstanceOf(MessageDecodingFailedException::class, $receiver->find((string) $document->_id)->getMessage());
    }

    public function testAck()
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('ack')->with('some_id');

        $receiver = new MongoDbReceiver($connection, $this->createStub(SerializerInterface::class));
        $receiver->ack(new Envelope(new DummyMessage('Hi'), [new MongoDbReceivedStamp('some_id')]));
    }

    public function testAckThrowsWithoutStamp()
    {
        $receiver = new MongoDbReceiver($this->createStub(Connection::class), $this->createStub(SerializerInterface::class));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('No MongoDbReceivedStamp found on the Envelope.');

        $receiver->ack(new Envelope(new DummyMessage('Hi')));
    }

    public function testReject()
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('reject')->with('some_id');

        $receiver = new MongoDbReceiver($connection, $this->createStub(SerializerInterface::class));
        $receiver->reject(new Envelope(new DummyMessage('Hi'), [new MongoDbReceivedStamp('some_id')]));
    }

    public function testAll()
    {
        $serializer = new PhpSerializer();
        $documents = [
            $this->createDocument($serializer->encode(new Envelope(new DummyMessage('Hi')))),
            $this->createDocument($serializer->encode(new Envelope(new DummyMessage('Ho')))),
        ];

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('findAll')->with(50)->willReturn($documents);

        $receiver = new MongoDbReceiver($connection, $serializer);
        $envelopes = iterator_to_array($receiver->all(50));

        $this->assertCount(2, $envelopes);
        $this->assertSame('Hi', $envelopes[0]->getMessage()->getMessage());
        $this->assertSame('Ho', $envelopes[1]->getMessage()->getMessage());
    }

    public function testFind()
    {
        $serializer = new PhpSerializer();
        $document = $this->createDocument($serializer->encode(new Envelope(new DummyMessage('Hi'))));

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('find')->with((string) $document->_id)->willReturn($document);

        $receiver = new MongoDbReceiver($connection, $serializer);

        $this->assertSame('Hi', $receiver->find((string) $document->_id)->getMessage()->getMessage());
    }

    public function testFindReturnsNullWhenThereIsNoMatch()
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('find')->willReturn(null);

        $receiver = new MongoDbReceiver($connection, $this->createStub(SerializerInterface::class));

        $this->assertNull($receiver->find((string) new ObjectId()));
    }

    public function testGetMessageCount()
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('getMessageCount')->willReturn(3);

        $receiver = new MongoDbReceiver($connection, $this->createStub(SerializerInterface::class));

        $this->assertSame(3, $receiver->getMessageCount());
    }

    /**
     * @param array{body: string, headers?: array<string, string>} $encodedEnvelope
     */
    private function createDocument(array $encodedEnvelope): BSONDocument
    {
        $document = new BSONDocument();
        $document->_id = new ObjectId();
        $document->body = $encodedEnvelope['body'];
        $document->headers = (object) ($encodedEnvelope['headers'] ?? []);
        $document->queueName = 'default';

        return $document;
    }
}
