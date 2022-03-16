<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\Amqp\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\Amqp\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpReceivedStamp;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpReceiver;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\Connection;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Serializer as SerializerComponent;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

/**
 * @requires extension amqp
 */
class AmqpReceiverTest extends TestCase
{
    public function testItReturnsTheDecodedMessageToTheHandler()
    {
        $serializer = new Serializer(
            new SerializerComponent\Serializer([new ObjectNormalizer()], ['json' => new JsonEncoder()])
        );

        $amqpEnvelope = $this->createAMQPEnvelope();
        $connection = $this->createMock(Connection::class);
        $connection->method('getQueueNames')->willReturn(['queueName']);
        $connection->method('get')->with('queueName')->willReturn($amqpEnvelope);

        $receiver = new AmqpReceiver($connection, $serializer);
        $actualEnvelopes = iterator_to_array($receiver->get());
        $this->assertCount(1, $actualEnvelopes);
        $this->assertEquals(new DummyMessage('Hi'), $actualEnvelopes[0]->getMessage());
    }

    public function testItThrowsATransportExceptionIfItCannotAcknowledgeMessage()
    {
        $this->expectException(TransportException::class);
        $serializer = $this->createMock(SerializerInterface::class);
        $amqpEnvelope = $this->createAMQPEnvelope();
        $connection = $this->createMock(Connection::class);
        $connection->method('getQueueNames')->willReturn(['queueName']);
        $connection->method('get')->with('queueName')->willReturn($amqpEnvelope);
        $connection->method('ack')->with($amqpEnvelope, 'queueName')->willThrowException(new \AMQPException());

        $receiver = new AmqpReceiver($connection, $serializer);
        $receiver->ack(new Envelope(new \stdClass(), [new AmqpReceivedStamp($amqpEnvelope, 'queueName')]));
    }

    public function testItThrowsATransportExceptionIfItCannotRejectMessage()
    {
        $this->expectException(TransportException::class);
        $serializer = $this->createMock(SerializerInterface::class);
        $amqpEnvelope = $this->createAMQPEnvelope();
        $connection = $this->createMock(Connection::class);
        $connection->method('getQueueNames')->willReturn(['queueName']);
        $connection->method('get')->with('queueName')->willReturn($amqpEnvelope);
        $connection->method('nack')->with($amqpEnvelope, 'queueName', \AMQP_NOPARAM)->willThrowException(new \AMQPException());

        $receiver = new AmqpReceiver($connection, $serializer);
        $receiver->reject(new Envelope(new \stdClass(), [new AmqpReceivedStamp($amqpEnvelope, 'queueName')]));
    }

    private function createAMQPEnvelope(): \AMQPEnvelope
    {
        $envelope = $this->createMock(\AMQPEnvelope::class);
        $envelope->method('getBody')->willReturn('{"message": "Hi"}');
        $envelope->method('getHeaders')->willReturn([
            'type' => DummyMessage::class,
        ]);

        return $envelope;
    }
}
