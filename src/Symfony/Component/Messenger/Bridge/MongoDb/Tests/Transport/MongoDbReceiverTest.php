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
        $envelopes = $receiver->get();
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

        $this->assertSame([], $receiver->get());
    }

    public function testItRejectsTheMessageIfItCannotBeDecoded()
    {
        $document = $this->createDocument(['body' => 'foo']);

        $connection = $this->createMock(Connection::class);
        $connection->method('get')->willReturn($document);
        $connection->expects($this->once())->method('reject')->with((string) $document->_id);

        $serializer = $this->createStub(SerializerInterface::class);
        $serializer->method('decode')->willThrowException(new MessageDecodingFailedException());

        $receiver = new MongoDbReceiver($connection, $serializer);

        $this->expectException(MessageDecodingFailedException::class);

        $receiver->get();
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

        return $document;
    }
}
