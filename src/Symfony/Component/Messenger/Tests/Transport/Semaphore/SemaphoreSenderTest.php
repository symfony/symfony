<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Transport\Semaphore;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Transport\Semaphore\Connection;
use Symfony\Component\Messenger\Transport\Semaphore\Exception\SemaphoreException;
use Symfony\Component\Messenger\Transport\Semaphore\SemaphoreSender;
use Symfony\Component\Messenger\Transport\Semaphore\SemaphoreStamp;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

class SemaphoreSenderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (false === \extension_loaded('sysvmsg')) {
            $this->markTestSkipped('Semaphore extension (sysvmsg) is required.');
        }
    }

    public function testItSendsTheEncodedMessage()
    {
        $envelope = new Envelope(new DummyMessage('Oy'));
        $encoded = ['body' => '...', 'headers' => ['type' => DummyMessage::class]];

        $serializer = $this->getMockBuilder(SerializerInterface::class)->getMock();
        $serializer->method('encode')->with($envelope)->willReturnOnConsecutiveCalls($encoded);

        $connection = $this->getMockBuilder(Connection::class)->disableOriginalConstructor()->getMock();
        $connection->expects($this->once())->method('send')->with($encoded['body'], $encoded['headers']);

        $sender = new SemaphoreSender($connection, $serializer);
        $sender->send($envelope);
    }

    public function testItSendsTheEncodedMessageUsingAType()
    {
        $envelope = (new Envelope(new DummyMessage('Oy')))->with($stamp = new SemaphoreStamp(1));
        $encoded = ['body' => '...', 'headers' => ['type' => DummyMessage::class]];

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->method('encode')->with($envelope)->willReturn($encoded);

        $connection = $this->getMockBuilder(Connection::class)->disableOriginalConstructor()->getMock();
        $connection->expects($this->once())->method('send')->with($encoded['body'], $encoded['headers'], 0, $stamp);

        $sender = new SemaphoreSender($connection, $serializer);
        $sender->send($envelope);
    }

    public function testItSendsTheEncodedMessageWithoutHeaders()
    {
        $envelope = new Envelope(new DummyMessage('Oy'));
        $encoded = ['body' => '...'];

        $serializer = $this->getMockBuilder(SerializerInterface::class)->getMock();
        $serializer->method('encode')->with($envelope)->willReturnOnConsecutiveCalls($encoded);

        $connection = $this->getMockBuilder(Connection::class)->disableOriginalConstructor()->getMock();
        $connection->expects($this->once())->method('send')->with($encoded['body'], []);

        $sender = new SemaphoreSender($connection, $serializer);
        $sender->send($envelope);
    }

    public function testItThrowsATransportExceptionIfItCannotSendTheMessage()
    {
        $this->expectException(TransportException::class);
        $envelope = new Envelope(new DummyMessage('Oy'));
        $encoded = ['body' => '...', 'headers' => ['type' => DummyMessage::class]];

        $serializer = $this->getMockBuilder(SerializerInterface::class)->getMock();
        $serializer->method('encode')->with($envelope)->willReturnOnConsecutiveCalls($encoded);

        $connection = $this->getMockBuilder(Connection::class)->disableOriginalConstructor()->getMock();
        $connection->method('send')->with($encoded['body'], $encoded['headers'])->willThrowException(new SemaphoreException());

        $sender = new SemaphoreSender($connection, $serializer);
        $sender->send($envelope);
    }
}
