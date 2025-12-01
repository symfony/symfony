<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\Doctrine\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\Doctrine\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\Connection;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineSender;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

class DoctrineSenderTest extends TestCase
{
    public function testSend()
    {
        $envelope = new Envelope(new DummyMessage('Oy'));
        $encoded = ['body' => '...', 'headers' => ['type' => DummyMessage::class]];

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('send')->with($encoded['body'], $encoded['headers'])->willReturn('15');

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->method('encode')->with($envelope)->willReturn($encoded);

        $sender = new DoctrineSender($connection, $serializer);
        $actualEnvelope = $sender->send($envelope);

        /** @var TransportMessageIdStamp $transportMessageIdStamp */
        $transportMessageIdStamp = $actualEnvelope->last(TransportMessageIdStamp::class);
        $this->assertNotNull($transportMessageIdStamp);
        $this->assertSame('15', $transportMessageIdStamp->getId());
    }

    public function testSendWithDelay()
    {
        $envelope = (new Envelope(new DummyMessage('Oy')))->with(new DelayStamp(500));
        $encoded = ['body' => '...', 'headers' => ['type' => DummyMessage::class]];

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('send')->with($encoded['body'], $encoded['headers'], 500);

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->method('encode')->with($envelope)->willReturn($encoded);

        $sender = new DoctrineSender($connection, $serializer);
        $sender->send($envelope);
    }

    public function testSendBatchWithIdsReturned()
    {
        $envelope1 = new Envelope(new DummyMessage('First'));
        $envelope2 = new Envelope(new DummyMessage('Second'));
        $encoded1 = ['body' => 'body1', 'headers' => ['type' => DummyMessage::class]];
        $encoded2 = ['body' => 'body2', 'headers' => ['type' => DummyMessage::class]];

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('sendBatch')
            ->with([
                ['body' => 'body1', 'headers' => ['type' => DummyMessage::class], 'delay' => 0],
                ['body' => 'body2', 'headers' => ['type' => DummyMessage::class], 'delay' => 0],
            ])
            ->willReturn(['15', '16']);

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->method('encode')->willReturnCallback(fn ($envelope) => $envelope->getMessage()->getMessage() === 'First' ? $encoded1 : $encoded2);

        $sender = new DoctrineSender($connection, $serializer);
        $result = $sender->sendBatch([$envelope1, $envelope2]);

        $this->assertCount(2, $result);

        $stamp1 = $result[0]->last(TransportMessageIdStamp::class);
        $this->assertNotNull($stamp1);
        $this->assertSame('15', $stamp1->getId());

        $stamp2 = $result[1]->last(TransportMessageIdStamp::class);
        $this->assertNotNull($stamp2);
        $this->assertSame('16', $stamp2->getId());
    }

    public function testSendBatchWithoutIdsReturned()
    {
        $envelope1 = new Envelope(new DummyMessage('First'));
        $envelope2 = new Envelope(new DummyMessage('Second'));
        $encoded1 = ['body' => 'body1', 'headers' => ['type' => DummyMessage::class]];
        $encoded2 = ['body' => 'body2', 'headers' => ['type' => DummyMessage::class]];

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('sendBatch')
            ->willReturn(null);

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->method('encode')->willReturnCallback(fn ($envelope) => $envelope->getMessage()->getMessage() === 'First' ? $encoded1 : $encoded2);

        $sender = new DoctrineSender($connection, $serializer);
        $result = $sender->sendBatch([$envelope1, $envelope2]);

        $this->assertCount(2, $result);
        $this->assertNull($result[0]->last(TransportMessageIdStamp::class));
        $this->assertNull($result[1]->last(TransportMessageIdStamp::class));
    }
}
