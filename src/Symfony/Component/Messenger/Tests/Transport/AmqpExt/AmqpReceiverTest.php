<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Transport\AmqpExt;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Transport\AmqpExt\AmqpReceiver;
use Symfony\Component\Messenger\Transport\AmqpExt\Connection;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\Serializer as SerializerComponent;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

/**
 * @requires extension amqp
 */
class AmqpReceiverTest extends TestCase
{
    public function testItSendTheDecodedMessageToTheHandler()
    {
        $serializer = new Serializer(
            new SerializerComponent\Serializer([new ObjectNormalizer()], ['json' => new JsonEncoder()])
        );

        $amqpEnvelope = $this->createAMQPEnvelope();
        $connection = $this->getMockBuilder(Connection::class)->disableOriginalConstructor()->getMock();
        $connection->method('get')->willReturn($amqpEnvelope);

        $receiver = new AmqpReceiver($connection, $serializer);
        $receiver->receive(function (?Envelope $envelope) use ($receiver) {
            $this->assertEquals(new DummyMessage('Hi'), $envelope->getMessage());
            $receiver->stop();
        });
    }

    /**
     * @expectedException \Symfony\Component\Messenger\Exception\TransportException
     */
    public function testItThrowsATransportExceptionIfItCannotAcknowledgeMessage()
    {
        $serializer = new Serializer(
            new SerializerComponent\Serializer([new ObjectNormalizer()], ['json' => new JsonEncoder()])
        );

        $amqpEnvelope = $this->createAMQPEnvelope();
        $connection = $this->getMockBuilder(Connection::class)->disableOriginalConstructor()->getMock();
        $connection->method('get')->willReturn($amqpEnvelope);
        $connection->method('ack')->with($amqpEnvelope)->willThrowException(new \AMQPException());

        $receiver = new AmqpReceiver($connection, $serializer);
        $receiver->receive(function (?Envelope $envelope) use ($receiver) {
            $receiver->ack($envelope);
            $receiver->stop();
        });
    }

    /**
     * @expectedException \Symfony\Component\Messenger\Exception\TransportException
     */
    public function testItThrowsATransportExceptionIfItCannotRejectMessage()
    {
        $serializer = new Serializer(
            new SerializerComponent\Serializer([new ObjectNormalizer()], ['json' => new JsonEncoder()])
        );

        $amqpEnvelope = $this->createAMQPEnvelope();
        $connection = $this->getMockBuilder(Connection::class)->disableOriginalConstructor()->getMock();
        $connection->method('get')->willReturn($amqpEnvelope);
        $connection->method('nack')->with($amqpEnvelope, AMQP_NOPARAM)->willThrowException(new \AMQPException());

        $receiver = new AmqpReceiver($connection, $serializer);
        $receiver->receive(function (?Envelope $envelope) use ($receiver) {
            $receiver->reject($envelope);
            $receiver->stop();
        });
    }

    private function createAMQPEnvelope()
    {
        $envelope = $this->getMockBuilder(\AMQPEnvelope::class)->getMock();
        $envelope->method('getBody')->willReturn('{"message": "Hi"}');
        $envelope->method('getHeaders')->willReturn([
            'type' => DummyMessage::class,
        ]);

        return $envelope;
    }
}
