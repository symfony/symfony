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
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpSender;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\Connection;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * @requires extension amqp
 */
class AmqpSenderTest extends TestCase
{
    public function testItSendsTheEncodedMessage()
    {
        $envelope = new Envelope(new DummyMessage('Oy'));
        $encoded = ['body' => '...', 'headers' => ['type' => DummyMessage::class]];

        $serializer = $this->createStub(SerializerInterface::class);
        $serializer->method('encode')->with($envelope)->willReturn($encoded);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('publish')->with($encoded['body'], $encoded['headers']);

        $sender = new AmqpSender($connection, $serializer);
        $sender->send($envelope);
    }

    public function testItSendsTheEncodedMessageUsingARoutingKey()
    {
        $envelope = (new Envelope(new DummyMessage('Oy')))->with($stamp = new AmqpStamp('rk'));
        $encoded = ['body' => '...', 'headers' => ['type' => DummyMessage::class]];

        $serializer = $this->createStub(SerializerInterface::class);
        $serializer->method('encode')->with($envelope)->willReturn($encoded);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('publish')->with($encoded['body'], $encoded['headers'], 0, $stamp);

        $sender = new AmqpSender($connection, $serializer);
        $sender->send($envelope);
    }

    public function testItSendsTheEncodedMessageWithoutHeaders()
    {
        $envelope = new Envelope(new DummyMessage('Oy'));
        $encoded = ['body' => '...'];

        $serializer = $this->createStub(SerializerInterface::class);
        $serializer->method('encode')->with($envelope)->willReturn($encoded);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('publish')->with($encoded['body'], []);

        $sender = new AmqpSender($connection, $serializer);
        $sender->send($envelope);
    }

    public function testContentTypeHeaderIsMovedToAttribute()
    {
        $envelope = new Envelope(new DummyMessage('Oy'));
        $encoded = ['body' => '...', 'headers' => ['type' => DummyMessage::class, 'Content-Type' => 'application/json']];

        $serializer = $this->createStub(SerializerInterface::class);
        $serializer->method('encode')->with($envelope)->willReturn($encoded);

        $connection = $this->createMock(Connection::class);
        unset($encoded['headers']['Content-Type']);
        $stamp = new AmqpStamp(null, \AMQP_NOPARAM, ['content_type' => 'application/json']);
        $connection->expects($this->once())->method('publish')->with($encoded['body'], $encoded['headers'], 0, $stamp);

        $sender = new AmqpSender($connection, $serializer);
        $sender->send($envelope);
    }

    public function testContentTypeHeaderDoesNotOverwriteAttribute()
    {
        $envelope = (new Envelope(new DummyMessage('Oy')))->with($stamp = new AmqpStamp('rk', \AMQP_NOPARAM, ['content_type' => 'custom']));
        $encoded = ['body' => '...', 'headers' => ['type' => DummyMessage::class, 'Content-Type' => 'application/json']];

        $serializer = $this->createStub(SerializerInterface::class);
        $serializer->method('encode')->with($envelope)->willReturn($encoded);

        $connection = $this->createMock(Connection::class);
        unset($encoded['headers']['Content-Type']);
        $connection->expects($this->once())->method('publish')->with($encoded['body'], $encoded['headers'], 0, $stamp);

        $sender = new AmqpSender($connection, $serializer);
        $sender->send($envelope);
    }

    public function testItThrowsATransportExceptionIfItCannotSendTheMessage()
    {
        $this->expectException(TransportException::class);
        $envelope = new Envelope(new DummyMessage('Oy'));
        $encoded = ['body' => '...', 'headers' => ['type' => DummyMessage::class]];

        $serializer = $this->createStub(SerializerInterface::class);
        $serializer->method('encode')->with($envelope)->willReturn($encoded);

        $connection = $this->createStub(Connection::class);
        $connection->method('publish')->with($encoded['body'], $encoded['headers'])->willThrowException(new \AMQPException());

        $sender = new AmqpSender($connection, $serializer);
        $sender->send($envelope);
    }

    public function testDoNotUseRetryRoutingKeyWhenSendingToFailureTransport()
    {
        $amqpEnvelope = $this->createStub(\AMQPEnvelope::class);
        $amqpEnvelope->method('getRoutingKey')->willReturn('original_routing_key');

        $envelope = new Envelope(new DummyMessage('Oy'), [
            new AmqpReceivedStamp($amqpEnvelope, 'original_queue'),
            new RedeliveryStamp(0),
            new SentToFailureTransportStamp('async'),
        ]);
        $encoded = ['body' => '...', 'headers' => ['type' => DummyMessage::class]];

        $serializer = $this->createStub(SerializerInterface::class);
        $serializer->method('encode')->willReturn($encoded);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('publish')
            ->with($encoded['body'], $encoded['headers'], 0, $this->callback(function (AmqpStamp $stamp) {
                // The routing key must NOT be the original queue name
                $this->assertNotSame('original_queue', $stamp->getRoutingKey());
                $this->assertFalse($stamp->isRetryAttempt());

                return true;
            }));

        $sender = new AmqpSender($connection, $serializer);
        $sender->send($envelope);
    }

    public function testItRetriesToTheOriginalQueueOfTheTransportTheMessageWasReceivedFrom()
    {
        $amqpEnvelope = $this->createStub(\AMQPEnvelope::class);
        $amqpEnvelope->method('getRoutingKey')->willReturn('original_routing_key');

        $envelope = new Envelope(new DummyMessage('Oy'), [
            new AmqpReceivedStamp($amqpEnvelope, 'original_queue'),
            new RedeliveryStamp(1),
            new DelayStamp(1000),
        ]);
        $encoded = ['body' => '...', 'headers' => ['type' => DummyMessage::class]];

        $serializer = $this->createStub(SerializerInterface::class);
        $serializer->method('encode')->willReturn($encoded);

        $connection = $this->createMock(Connection::class);
        $connection->method('getQueueNames')->willReturn(['other_queue', 'original_queue']);
        $connection->expects($this->once())->method('publish')
            ->with($encoded['body'], $encoded['headers'], 1000, $this->callback(function (AmqpStamp $stamp) {
                $this->assertSame('original_queue', $stamp->getRoutingKey());
                $this->assertTrue($stamp->isRetryAttempt());

                return true;
            }));

        $sender = new AmqpSender($connection, $serializer);
        $sender->send($envelope);
    }

    public function testItKeepsTheReceivedRoutingKeyWhenSendingToTheTransportTheMessageWasReceivedFrom()
    {
        $amqpEnvelope = $this->createStub(\AMQPEnvelope::class);
        $amqpEnvelope->method('getRoutingKey')->willReturn('original_routing_key');

        $envelope = new Envelope(new DummyMessage('Oy'), [new AmqpReceivedStamp($amqpEnvelope, 'original_queue')]);
        $encoded = ['body' => '...', 'headers' => ['type' => DummyMessage::class]];

        $serializer = $this->createStub(SerializerInterface::class);
        $serializer->method('encode')->willReturn($encoded);

        $connection = $this->createMock(Connection::class);
        $connection->method('getQueueNames')->willReturn(['original_queue']);
        $connection->expects($this->once())->method('publish')
            ->with($encoded['body'], $encoded['headers'], 0, $this->callback(function (AmqpStamp $stamp) {
                $this->assertSame('original_routing_key', $stamp->getRoutingKey());
                $this->assertFalse($stamp->isRetryAttempt());

                return true;
            }));

        $sender = new AmqpSender($connection, $serializer);
        $sender->send($envelope);
    }

    public function testItDoesNotReuseTheReceivedRoutingKeyWhenSendingToAnotherTransport()
    {
        $this->assertRoutingKeyWhenSendingToAnotherTransport(null);
        $this->assertRoutingKeyWhenSendingToAnotherTransport(null, [new RedeliveryStamp(1)]);
        $this->assertRoutingKeyWhenSendingToAnotherTransport(null, [new RedeliveryStamp(0), new SentToFailureTransportStamp('async')]);
    }

    public function testAnExplicitRoutingKeyIsUsedWhenSendingAReceivedMessageToAnotherTransport()
    {
        $this->assertRoutingKeyWhenSendingToAnotherTransport('explicit_routing_key', [new AmqpStamp('explicit_routing_key')]);
        $this->assertRoutingKeyWhenSendingToAnotherTransport('explicit_routing_key', [new AmqpStamp('explicit_routing_key'), new RedeliveryStamp(1)]);
        $this->assertRoutingKeyWhenSendingToAnotherTransport('explicit_routing_key', [new AmqpStamp('explicit_routing_key'), new RedeliveryStamp(0), new SentToFailureTransportStamp('async')]);
    }

    public function testItDoesNotRepublishAReceivedZeroPriorityOnRetry()
    {
        $amqpEnvelope = $this->createStub(\AMQPEnvelope::class);
        $amqpEnvelope->method('getPriority')->willReturn(0);

        $envelope = new Envelope(new DummyMessage('Oy'), [
            new AmqpReceivedStamp($amqpEnvelope, 'original_queue'),
            new RedeliveryStamp(0),
        ]);
        $encoded = ['body' => '...', 'headers' => ['type' => DummyMessage::class]];

        $serializer = $this->createStub(SerializerInterface::class);
        $serializer->method('encode')->willReturn($encoded);

        $connection = $this->createMock(Connection::class);
        $connection->method('getQueueNames')->willReturn(['original_queue']);
        $connection->expects($this->once())->method('publish')
            ->with($encoded['body'], $encoded['headers'], 0, $this->callback(function (AmqpStamp $stamp) {
                $this->assertSame('original_queue', $stamp->getRoutingKey());
                $this->assertTrue($stamp->isRetryAttempt());
                $this->assertArrayNotHasKey('priority', $stamp->getAttributes());

                return true;
            }));

        $sender = new AmqpSender($connection, $serializer);
        $sender->send($envelope);
    }

    public function testItDoesNotReuseTheReceivedExpirationWhenSendingToAnotherTransport()
    {
        $this->assertExpirationWhenSending(null, ['other_queue']);
        $this->assertExpirationWhenSending(null, ['other_queue'], [new RedeliveryStamp(1)]);
        $this->assertExpirationWhenSending(null, ['other_queue'], [new RedeliveryStamp(0), new SentToFailureTransportStamp('async')]);
    }

    public function testAnExplicitExpirationIsUsedWhenSendingAReceivedMessageToAnotherTransport()
    {
        $amqpStamp = new AmqpStamp(null, \AMQP_NOPARAM, ['expiration' => '300000']);

        $this->assertExpirationWhenSending('300000', ['other_queue'], [$amqpStamp]);
        $this->assertExpirationWhenSending('300000', ['other_queue'], [$amqpStamp, new RedeliveryStamp(0), new SentToFailureTransportStamp('async')]);
    }

    public function testItKeepsTheReceivedExpirationWhenSendingToTheTransportTheMessageWasReceivedFrom()
    {
        $this->assertExpirationWhenSending('60000', ['original_queue']);
        $this->assertExpirationWhenSending('60000', ['original_queue'], [new RedeliveryStamp(1)]);
    }

    private function assertExpirationWhenSending(?string $expectedExpiration, array $queueNames, array $stamps = []): void
    {
        $amqpEnvelope = $this->createStub(\AMQPEnvelope::class);
        $amqpEnvelope->method('getExpiration')->willReturn('60000');

        $envelope = new Envelope(new DummyMessage('Oy'), [new AmqpReceivedStamp($amqpEnvelope, 'original_queue'), ...$stamps]);
        $encoded = ['body' => '...', 'headers' => ['type' => DummyMessage::class]];

        $serializer = $this->createStub(SerializerInterface::class);
        $serializer->method('encode')->willReturn($encoded);

        $connection = $this->createMock(Connection::class);
        $connection->method('getQueueNames')->willReturn($queueNames);
        $connection->expects($this->once())->method('publish')
            ->with($encoded['body'], $encoded['headers'], 0, $this->callback(function (AmqpStamp $stamp) use ($expectedExpiration) {
                $this->assertSame($expectedExpiration, $stamp->getAttributes()['expiration'] ?? null);

                return true;
            }));

        $sender = new AmqpSender($connection, $serializer);
        $sender->send($envelope);
    }

    private function assertRoutingKeyWhenSendingToAnotherTransport(?string $expectedRoutingKey, array $stamps = []): void
    {
        $amqpEnvelope = $this->createStub(\AMQPEnvelope::class);
        $amqpEnvelope->method('getRoutingKey')->willReturn('original_routing_key');
        $amqpEnvelope->method('getContentType')->willReturn('application/x-original');

        $envelope = new Envelope(new DummyMessage('Oy'), [new AmqpReceivedStamp($amqpEnvelope, 'original_queue'), ...$stamps]);
        $encoded = ['body' => '...', 'headers' => ['type' => DummyMessage::class]];

        $serializer = $this->createStub(SerializerInterface::class);
        $serializer->method('encode')->willReturn($encoded);

        $connection = $this->createMock(Connection::class);
        $connection->method('getQueueNames')->willReturn(['other_queue']);
        $connection->expects($this->once())->method('publish')
            ->with($encoded['body'], $encoded['headers'], 0, $this->callback(function (AmqpStamp $stamp) use ($expectedRoutingKey) {
                $this->assertSame($expectedRoutingKey, $stamp->getRoutingKey());
                $this->assertFalse($stamp->isRetryAttempt());
                $this->assertSame('application/x-original', $stamp->getAttributes()['content_type']);

                return true;
            }));

        $sender = new AmqpSender($connection, $serializer);
        $sender->send($envelope);
    }
}
