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

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\Amqp\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpReceivedStamp;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpReceiver;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\Connection;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Serializer as SerializerComponent;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

#[RequiresPhpExtension('amqp')]
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
        $connection->expects($this->once())->method('get')->with('queueName')->willReturn($amqpEnvelope);

        $receiver = new AmqpReceiver($connection, $serializer);
        $actualEnvelopes = iterator_to_array($receiver->get());
        $this->assertCount(1, $actualEnvelopes);
        $this->assertEquals(new DummyMessage('Hi'), $actualEnvelopes[0]->getMessage());
    }

    public function testGetAcceptsFetchSize()
    {
        $serializer = new Serializer(
            new SerializerComponent\Serializer([new ObjectNormalizer()], ['json' => new JsonEncoder()])
        );

        $amqpEnvelope = $this->createAMQPEnvelope();
        $connection = $this->createMock(Connection::class);
        $connection->method('getQueueNames')->willReturn(['queueName']);
        $connection->expects($this->exactly(2))->method('get')->with('queueName')->willReturnOnConsecutiveCalls($amqpEnvelope, null);

        $receiver = new AmqpReceiver($connection, $serializer);
        $actualEnvelopes = iterator_to_array($receiver->get(7));

        $this->assertCount(1, $actualEnvelopes);
    }

    public function testGetFromQueuesAcceptsFetchSize()
    {
        $serializer = new Serializer(
            new SerializerComponent\Serializer([new ObjectNormalizer()], ['json' => new JsonEncoder()])
        );

        $amqpEnvelope = $this->createAMQPEnvelope();
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->exactly(2))->method('get')->with('queueName')->willReturnOnConsecutiveCalls($amqpEnvelope, null);

        $receiver = new AmqpReceiver($connection, $serializer);
        $actualEnvelopes = iterator_to_array($receiver->getFromQueues(['queueName'], 7));

        $this->assertCount(1, $actualEnvelopes);
    }

    public function testItThrowsATransportExceptionIfItCannotAcknowledgeMessage()
    {
        $this->expectException(TransportException::class);
        $serializer = $this->createStub(SerializerInterface::class);
        $amqpEnvelope = $this->createAMQPEnvelope();
        $connection = $this->createMock(Connection::class);
        $connection->method('getQueueNames')->willReturn(['queueName']);
        $connection->expects($this->once())->method('ack')->with($amqpEnvelope, 'queueName')->willThrowException(new \AMQPException());

        $receiver = new AmqpReceiver($connection, $serializer);
        $receiver->ack(new Envelope(new \stdClass(), [new AmqpReceivedStamp($amqpEnvelope, 'queueName')]));
    }

    public function testItThrowsATransportExceptionIfItCannotRejectMessage()
    {
        $this->expectException(TransportException::class);
        $serializer = $this->createStub(SerializerInterface::class);
        $amqpEnvelope = $this->createAMQPEnvelope();
        $connection = $this->createMock(Connection::class);
        $connection->method('getQueueNames')->willReturn(['queueName']);
        $connection->expects($this->once())->method('nack')->with($amqpEnvelope, 'queueName', \AMQP_NOPARAM)->willThrowException(new \AMQPException());

        $receiver = new AmqpReceiver($connection, $serializer);
        $receiver->reject(new Envelope(new \stdClass(), [new AmqpReceivedStamp($amqpEnvelope, 'queueName')]));
    }

    public function testTransportMessageIdStampIsCreatedWhenMessageIdIsSet()
    {
        $serializer = new Serializer(
            new SerializerComponent\Serializer([new DateTimeNormalizer(), new ArrayDenormalizer(), new ObjectNormalizer()], ['json' => new JsonEncoder()])
        );

        $id = '01946fcb-4bcb-7aa7-9727-dac1c0374443';
        $amqpEnvelope = $this->createAMQPEnvelope($id);

        $connection = $this->createMock(Connection::class);
        $connection->method('getQueueNames')->willReturn(['queueName']);
        $connection->expects($this->once())->method('get')->with('queueName')->willReturn($amqpEnvelope);

        $receiver = new AmqpReceiver($connection, $serializer);
        $actualEnvelopes = iterator_to_array($receiver->get());
        $this->assertCount(1, $actualEnvelopes);

        /** @var Envelope $actualEnvelope */
        $actualEnvelope = $actualEnvelopes[0];
        $this->assertEquals(new DummyMessage('Hi'), $actualEnvelope->getMessage());

        /** @var AmqpReceivedStamp $amqpReceivedStamp */
        $amqpReceivedStamp = $actualEnvelope->last(AmqpReceivedStamp::class);
        $this->assertNotNull($amqpReceivedStamp);
        $this->assertSame($amqpEnvelope->getBody(), $amqpReceivedStamp->getAmqpEnvelope()->getBody());
        $this->assertSame($amqpEnvelope->getHeaders(), $amqpReceivedStamp->getAmqpEnvelope()->getHeaders());
        $this->assertSame($amqpEnvelope->getMessageId(), $amqpReceivedStamp->getAmqpEnvelope()->getMessageId());

        /** @var TransportMessageIdStamp $transportMessageIdStamp */
        $transportMessageIdStamp = $actualEnvelope->last(TransportMessageIdStamp::class);
        $this->assertNotNull($transportMessageIdStamp);
        $this->assertSame($id, $transportMessageIdStamp->getId());
    }

    public function testTransportMessageIdStampIsNotCreatedWhenMessageIdIsNotSet()
    {
        $serializer = new Serializer(
            new SerializerComponent\Serializer([new DateTimeNormalizer(), new ArrayDenormalizer(), new ObjectNormalizer()], ['json' => new JsonEncoder()])
        );

        $amqpEnvelope = $this->createAMQPEnvelope();

        $connection = $this->createMock(Connection::class);
        $connection->method('getQueueNames')->willReturn(['queueName']);
        $connection->expects($this->once())->method('get')->with('queueName')->willReturn($amqpEnvelope);

        $receiver = new AmqpReceiver($connection, $serializer);
        $actualEnvelopes = iterator_to_array($receiver->get());
        $this->assertCount(1, $actualEnvelopes);

        /** @var Envelope $actualEnvelope */
        $actualEnvelope = $actualEnvelopes[0];
        $this->assertEquals(new DummyMessage('Hi'), $actualEnvelope->getMessage());

        /** @var AmqpReceivedStamp $amqpReceivedStamp */
        $amqpReceivedStamp = $actualEnvelope->last(AmqpReceivedStamp::class);
        $this->assertNotNull($amqpReceivedStamp);
        $this->assertSame($amqpEnvelope->getBody(), $amqpReceivedStamp->getAmqpEnvelope()->getBody());
        $this->assertSame($amqpEnvelope->getHeaders(), $amqpReceivedStamp->getAmqpEnvelope()->getHeaders());
        $this->assertSame($amqpEnvelope->getMessageId(), $amqpReceivedStamp->getAmqpEnvelope()->getMessageId());

        /** @var TransportMessageIdStamp $transportMessageIdStamp */
        $transportMessageIdStamp = $actualEnvelope->last(TransportMessageIdStamp::class);
        $this->assertNull($transportMessageIdStamp);
    }

    public function testItReturnsSerializedEnvelopeWhenDecodingFails()
    {
        $serializer = $this->createStub(SerializerInterface::class);
        $serializer->method('decode')->willThrowException(new MessageDecodingFailedException());

        $amqpEnvelope = $this->createAMQPEnvelope();
        $connection = $this->createStub(Connection::class);
        $connection->method('getQueueNames')->willReturn(['queueName']);
        $connection->method('get')->willReturn($amqpEnvelope);

        $receiver = new AmqpReceiver($connection, $serializer);
        $envelopes = iterator_to_array($receiver->get());

        $this->assertCount(1, $envelopes);
        $this->assertInstanceOf(MessageDecodingFailedException::class, $envelopes[0]->getMessage());
    }

    public function testItAddsARedeliveryStampFromTheXDeathHeader()
    {
        $serializer = new Serializer(
            new SerializerComponent\Serializer([new ObjectNormalizer()], ['json' => new JsonEncoder()])
        );

        $amqpEnvelope = $this->createAMQPEnvelope(headers: ['x-death' => [['count' => 3, 'time' => 1717000000]]]);
        $connection = $this->createMock(Connection::class);
        $connection->method('getQueueNames')->willReturn(['queueName']);
        $connection->expects($this->once())->method('get')->with('queueName')->willReturn($amqpEnvelope);

        $receiver = new AmqpReceiver($connection, $serializer);
        $envelope = iterator_to_array($receiver->get())[0];

        $stamp = $envelope->last(RedeliveryStamp::class);
        $this->assertInstanceOf(RedeliveryStamp::class, $stamp);
        // The broker redelivery must not feed the Messenger retry counter.
        $this->assertSame(0, $stamp->getRetryCount());
        $this->assertSame(1717000000, $stamp->getRedeliveredAt()->getTimestamp());
        $this->assertSame('+00:00', $stamp->getRedeliveredAt()->getTimezone()->getName());
    }

    public function testItAddsARedeliveryStampFromADateTimeXDeathHeader()
    {
        $serializer = new Serializer(
            new SerializerComponent\Serializer([new ObjectNormalizer()], ['json' => new JsonEncoder()])
        );

        $amqpEnvelope = $this->createAMQPEnvelope(headers: ['x-death' => [['count' => 3, 'time' => new \DateTimeImmutable('@1717000000')]]]);
        $connection = $this->createMock(Connection::class);
        $connection->method('getQueueNames')->willReturn(['queueName']);
        $connection->expects($this->once())->method('get')->with('queueName')->willReturn($amqpEnvelope);

        $receiver = new AmqpReceiver($connection, $serializer);
        $envelope = iterator_to_array($receiver->get())[0];

        $stamp = $envelope->last(RedeliveryStamp::class);
        $this->assertInstanceOf(RedeliveryStamp::class, $stamp);
        $this->assertSame(1717000000, $stamp->getRedeliveredAt()->getTimestamp());
    }

    public function testItDoesNotAddARedeliveryStampWithoutTheXDeathHeader()
    {
        $serializer = new Serializer(
            new SerializerComponent\Serializer([new ObjectNormalizer()], ['json' => new JsonEncoder()])
        );

        $amqpEnvelope = $this->createAMQPEnvelope();
        $connection = $this->createMock(Connection::class);
        $connection->method('getQueueNames')->willReturn(['queueName']);
        $connection->expects($this->once())->method('get')->with('queueName')->willReturn($amqpEnvelope);

        $receiver = new AmqpReceiver($connection, $serializer);
        $envelope = iterator_to_array($receiver->get())[0];

        $this->assertNull($envelope->last(RedeliveryStamp::class));
    }

    public function testItDoesNotAddARedeliveryStampWhenTheXDeathHeaderHasNoUsableTime()
    {
        $serializer = new Serializer(
            new SerializerComponent\Serializer([new ObjectNormalizer()], ['json' => new JsonEncoder()])
        );

        $amqpEnvelope = $this->createAMQPEnvelope(headers: ['x-death' => [['count' => 3]]]);
        $connection = $this->createMock(Connection::class);
        $connection->method('getQueueNames')->willReturn(['queueName']);
        $connection->expects($this->once())->method('get')->with('queueName')->willReturn($amqpEnvelope);

        $receiver = new AmqpReceiver($connection, $serializer);
        $envelope = iterator_to_array($receiver->get())[0];

        $this->assertNull($envelope->last(RedeliveryStamp::class));
    }

    public function testItKeepsTheRedeliveryStampCarriedByTheMessage()
    {
        $serializer = new PhpSerializer();

        $existing = new RedeliveryStamp(7, new \DateTimeImmutable('@1700000000'));
        $encoded = $serializer->encode(new Envelope(new DummyMessage('Hi'), [$existing]));

        $amqpEnvelope = $this->createAMQPEnvelope(body: $encoded['body'], headers: ['x-death' => [['count' => 3, 'time' => 1717000000]]]);
        $connection = $this->createMock(Connection::class);
        $connection->method('getQueueNames')->willReturn(['queueName']);
        $connection->expects($this->once())->method('get')->with('queueName')->willReturn($amqpEnvelope);

        $receiver = new AmqpReceiver($connection, $serializer);
        $envelope = iterator_to_array($receiver->get())[0];

        $stamp = $envelope->last(RedeliveryStamp::class);
        $this->assertInstanceOf(RedeliveryStamp::class, $stamp);
        $this->assertSame(7, $stamp->getRetryCount());
        $this->assertSame(1700000000, $stamp->getRedeliveredAt()->getTimestamp());
    }

    private function createAMQPEnvelope(?string $messageId = null, string $body = '{"message": "Hi"}', array $headers = []): \AMQPEnvelope
    {
        $envelope = $this->createStub(\AMQPEnvelope::class);
        $envelope->method('getBody')->willReturn($body);
        $envelope->method('getHeaders')->willReturn([
            'type' => DummyMessage::class,
        ] + $headers);
        $envelope->method('getMessageId')->willReturn($messageId);

        return $envelope;
    }
}
