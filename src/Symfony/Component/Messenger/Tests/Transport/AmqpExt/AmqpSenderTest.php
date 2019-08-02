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
use Symfony\Component\Messenger\Transport\AmqpExt\AmqpSender;
use Symfony\Component\Messenger\Transport\AmqpExt\AmqpStamp;
use Symfony\Component\Messenger\Transport\AmqpExt\Connection;
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

        $serializer = $this->getMockBuilder(SerializerInterface::class)->getMock();
        $serializer->method('encode')->with($envelope)->willReturnOnConsecutiveCalls($encoded);

        $connection = $this->getMockBuilder(Connection::class)->disableOriginalConstructor()->getMock();
        $connection->expects($this->once())->method('publish')->with($encoded['body'], $encoded['headers']);

        $sender = new AmqpSender($connection, $serializer);
        $sender->send($envelope);
    }

    public function testItSendsTheEncodedMessageUsingARoutingKey()
    {
        $envelope = (new Envelope(new DummyMessage('Oy')))->with($stamp = new AmqpStamp('rk'));
        $encoded = ['body' => '...', 'headers' => ['type' => DummyMessage::class]];

        $serializer = $this->createMock(SerializerInterface::class);
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

        $serializer = $this->getMockBuilder(SerializerInterface::class)->getMock();
        $serializer->method('encode')->with($envelope)->willReturnOnConsecutiveCalls($encoded);

        $connection = $this->getMockBuilder(Connection::class)->disableOriginalConstructor()->getMock();
        $connection->expects($this->once())->method('publish')->with($encoded['body'], []);

        $sender = new AmqpSender($connection, $serializer);
        $sender->send($envelope);
    }

    public function testContentTypeHeaderIsMovedToAttribute()
    {
        $envelope = new Envelope(new DummyMessage('Oy'));
        $encoded = ['body' => '...', 'headers' => ['type' => DummyMessage::class, 'Content-Type' => 'application/json']];

        $serializer = $this->getMockBuilder(SerializerInterface::class)->getMock();
        $serializer->method('encode')->with($envelope)->willReturnOnConsecutiveCalls($encoded);

        $connection = $this->getMockBuilder(Connection::class)->disableOriginalConstructor()->getMock();
        unset($encoded['headers']['Content-Type']);
        $stamp = new AmqpStamp(null, AMQP_NOPARAM, ['content_type' => 'application/json']);
        $connection->expects($this->once())->method('publish')->with($encoded['body'], $encoded['headers'], 0, $stamp);

        $sender = new AmqpSender($connection, $serializer);
        $sender->send($envelope);
    }

    public function testContentTypeHeaderDoesNotOverwriteAttribute()
    {
        $envelope = (new Envelope(new DummyMessage('Oy')))->with($stamp = new AmqpStamp('rk', AMQP_NOPARAM, ['content_type' => 'custom']));
        $encoded = ['body' => '...', 'headers' => ['type' => DummyMessage::class, 'Content-Type' => 'application/json']];

        $serializer = $this->getMockBuilder(SerializerInterface::class)->getMock();
        $serializer->method('encode')->with($envelope)->willReturnOnConsecutiveCalls($encoded);

        $connection = $this->getMockBuilder(Connection::class)->disableOriginalConstructor()->getMock();
        unset($encoded['headers']['Content-Type']);
        $connection->expects($this->once())->method('publish')->with($encoded['body'], $encoded['headers'], 0, $stamp);

        $sender = new AmqpSender($connection, $serializer);
        $sender->send($envelope);
    }

    public function testItThrowsATransportExceptionIfItCannotSendTheMessage()
    {
        $this->expectException('Symfony\Component\Messenger\Exception\TransportException');
        $envelope = new Envelope(new DummyMessage('Oy'));
        $encoded = ['body' => '...', 'headers' => ['type' => DummyMessage::class]];

        $serializer = $this->getMockBuilder(SerializerInterface::class)->getMock();
        $serializer->method('encode')->with($envelope)->willReturnOnConsecutiveCalls($encoded);

        $connection = $this->getMockBuilder(Connection::class)->disableOriginalConstructor()->getMock();
        $connection->method('publish')->with($encoded['body'], $encoded['headers'])->willThrowException(new \AMQPException());

        $sender = new AmqpSender($connection, $serializer);
        $sender->send($envelope);
    }
}
