<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\AmazonSqs\Tests\Transport;

use AsyncAws\Core\Exception\Http\NetworkException;
use AsyncAws\Core\Exception\UnparsableResponse;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\AmazonSqs\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Bridge\AmazonSqs\Transport\AmazonSqsReceivedStamp;
use Symfony\Component\Messenger\Bridge\AmazonSqs\Transport\AmazonSqsReceiver;
use Symfony\Component\Messenger\Bridge\AmazonSqs\Transport\Connection;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\Serializer as SerializerComponent;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class AmazonSqsReceiverTest extends TestCase
{
    public function testItReturnsTheDecodedMessageToTheHandler()
    {
        $serializer = $this->createSerializer();

        $sqsEnvelop = $this->createSqsEnvelope();
        $connection = $this->createStub(Connection::class);
        $connection->method('get')->willReturn($sqsEnvelop);

        $receiver = new AmazonSqsReceiver($connection, $serializer);
        $actualEnvelopes = iterator_to_array($receiver->get());
        $this->assertCount(1, $actualEnvelopes);
        $this->assertEquals(new DummyMessage('Hi'), $actualEnvelopes[0]->getMessage());
    }

    public function testItRejectTheMessageIfThereIsAMessageDecodingFailedException()
    {
        $this->expectException(MessageDecodingFailedException::class);

        $serializer = $this->createStub(PhpSerializer::class);
        $serializer->method('decode')->willThrowException(new MessageDecodingFailedException());

        $sqsEnvelop = $this->createSqsEnvelope();
        $connection = $this->createMock(Connection::class);
        $connection->method('get')->willReturn($sqsEnvelop);
        $connection->expects($this->once())->method('delete');

        $receiver = new AmazonSqsReceiver($connection, $serializer);
        iterator_to_array($receiver->get());
    }

    public function testItConvertsNetworkExceptionDuringGetIntoTransportException()
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('get')->willThrowException(new NetworkException('Could not contact remote server.'));

        $receiver = new AmazonSqsReceiver($connection, $this->createSerializer());

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Could not contact remote server.');

        iterator_to_array($receiver->get());
    }

    public function testItConvertsNetworkExceptionDuringAckIntoTransportException()
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('delete')->willThrowException(new NetworkException('Could not contact remote server.'));

        $receiver = new AmazonSqsReceiver($connection, $this->createSerializer());

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Could not contact remote server.');

        $receiver->ack(new Envelope(new DummyMessage('Hi'), [new AmazonSqsReceivedStamp('1')]));
    }

    private function createSqsEnvelope()
    {
        return [
            'id' => 1,
            'body' => '{"message": "Hi"}',
            'headers' => [
                'type' => DummyMessage::class,
            ],
        ];
    }

    public function testItConvertsNetworkExceptionDuringRejectIntoTransportException()
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('delete')->willThrowException(new NetworkException('Could not contact remote server.'));

        $receiver = new AmazonSqsReceiver($connection, $this->createSerializer());

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Could not contact remote server.');

        $receiver->reject(new Envelope(new DummyMessage('Oops'), [new AmazonSqsReceivedStamp('id')]));
    }

    public function testItConvertsNetworkExceptionDuringGetMessageCountIntoTransportException()
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getMessageCount')->willThrowException(new NetworkException('Could not contact remote server.'));

        $receiver = new AmazonSqsReceiver($connection, $this->createSerializer());

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Could not contact remote server.');

        $receiver->getMessageCount();
    }

    public function testItConvertsUnparsableResponseIntoTransportException()
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('get')->willThrowException(new UnparsableResponse('Could not parse response as array.'));

        $receiver = new AmazonSqsReceiver($connection, $this->createSerializer());

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Could not parse response as array.');

        iterator_to_array($receiver->get());
    }

    private function createSerializer(): Serializer
    {
        $serializer = new Serializer(
            new SerializerComponent\Serializer([new ObjectNormalizer()], ['json' => new JsonEncoder()])
        );

        return $serializer;
    }
}
