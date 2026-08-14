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
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Bridge\AmazonSqs\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Bridge\AmazonSqs\Transport\AmazonSqsFairQueueStamp;
use Symfony\Component\Messenger\Bridge\AmazonSqs\Transport\AmazonSqsFifoStamp;
use Symfony\Component\Messenger\Bridge\AmazonSqs\Transport\AmazonSqsSender;
use Symfony\Component\Messenger\Bridge\AmazonSqs\Transport\AmazonSqsXrayTraceHeaderStamp;
use Symfony\Component\Messenger\Bridge\AmazonSqs\Transport\Connection;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

class AmazonSqsSenderTest extends TestCase
{
    public function testSend()
    {
        $envelope = new Envelope(new DummyMessage('Oy'));
        $encoded = ['body' => '...', 'headers' => ['type' => DummyMessage::class]];

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('send')->with($encoded['body'], $encoded['headers']);

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->once())->method('encode')->with($envelope)->willReturn($encoded);

        $sender = new AmazonSqsSender($connection, $serializer);
        $sender->send($envelope);
    }

    public function testSendWithAmazonSqsFifoStamp()
    {
        $envelope = (new Envelope(new DummyMessage('Oy')))
            ->with($stamp = new AmazonSqsFifoStamp('testGroup', 'testDeduplicationId'));

        $encoded = ['body' => '...', 'headers' => ['type' => DummyMessage::class]];

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('send')
            ->with($encoded['body'], $encoded['headers'], null, $stamp->getMessageGroupId(), $stamp->getMessageDeduplicationId());

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->once())->method('encode')->with($envelope)->willReturn($encoded);

        $sender = new AmazonSqsSender($connection, $serializer);
        $sender->send($envelope);
    }

    public function testSendWithAmazonSqsXrayTraceHeaderStamp()
    {
        $envelope = (new Envelope(new DummyMessage('Oy')))
            ->with($stamp = new AmazonSqsXrayTraceHeaderStamp('traceHeader'));

        $encoded = ['body' => '...', 'headers' => ['type' => DummyMessage::class]];

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('send')
            ->with($encoded['body'], $encoded['headers'], null, null, null, $stamp->getTraceId());

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->once())->method('encode')->with($envelope)->willReturn($encoded);

        $sender = new AmazonSqsSender($connection, $serializer);
        $sender->send($envelope);
    }

    public function testSendEncodeBodyToRespectAmazonRequirements()
    {
        $envelope = new Envelope(new DummyMessage('Oy'));
        $encoded = ['body' => "\x7", 'headers' => ['type' => DummyMessage::class]];

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('send')->with(base64_encode($encoded['body']), $encoded['headers']);

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->once())->method('encode')->with($envelope)->willReturn($encoded);

        $sender = new AmazonSqsSender($connection, $serializer);
        $sender->send($envelope);
    }

    public function testSendWithAmazonSqsFairQueueStamp()
    {
        $envelope = (new Envelope(new DummyMessage('Oy')))
            ->with($stamp = new AmazonSqsFairQueueStamp('tenant-123'));

        $encoded = ['body' => '...', 'headers' => ['type' => DummyMessage::class]];

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('send')
            ->with($encoded['body'], $encoded['headers'], 0, $stamp->getMessageGroupId());

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->method('encode')->with($envelope)->willReturn($encoded);

        $sender = new AmazonSqsSender($connection, $serializer);
        $sender->send($envelope);
    }

    public function testSendWithAmazonSqsFairQueueStampWontWorkAlongsideFifoStamp()
    {
        $envelope = (new Envelope(new DummyMessage('Oy')))
            ->with($fifoStamp = new AmazonSqsFifoStamp('testGroup', 'testDeduplicationId'))
            ->with(new AmazonSqsFairQueueStamp('tenant-123'));

        $encoded = ['body' => '...', 'headers' => ['type' => DummyMessage::class]];

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('send')
            ->with($encoded['body'], $encoded['headers'], 0, $fifoStamp->getMessageGroupId(), $fifoStamp->getMessageDeduplicationId());

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->method('encode')->with($envelope)->willReturn($encoded);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('debug')
            ->with($this->stringContains('takes precedence'), $this->anything());

        $sender = new AmazonSqsSender($connection, $serializer, $logger);
        $sender->send($envelope);
    }

    public function testItConvertsNetworkExceptionDuringSendIntoTransportException()
    {
        $envelope = new Envelope(new DummyMessage('Oy'));
        $encoded = ['body' => '...', 'headers' => ['type' => DummyMessage::class]];

        $connection = $this->createStub(Connection::class);
        $connection->method('send')->willThrowException(new NetworkException('Could not contact remote server.'));

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->once())->method('encode')->with($envelope)->willReturn($encoded);

        $sender = new AmazonSqsSender($connection, $serializer);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Could not contact remote server.');

        $sender->send($envelope);
    }
}
