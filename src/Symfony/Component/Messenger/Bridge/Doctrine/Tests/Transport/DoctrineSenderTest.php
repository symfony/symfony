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

use Doctrine\DBAL\Exception as DBALException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\Doctrine\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\Connection;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineSender;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
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
        $serializer->expects($this->once())->method('encode')->with($envelope)->willReturn($encoded);

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
        $serializer->expects($this->once())->method('encode')->with($envelope)->willReturn($encoded);

        $sender = new DoctrineSender($connection, $serializer);
        $sender->send($envelope);
    }

    public function testSendBatch()
    {
        $envelopes = ['a' => new Envelope(new DummyMessage('a')), 'b' => new Envelope(new DummyMessage('b'), [new DelayStamp(500)])];

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('sendBatch')->with([
            'a' => ['body a', ['type' => DummyMessage::class], 0],
            'b' => ['body b', ['type' => DummyMessage::class], 500],
        ])->willReturn(['a' => '15', 'b' => '16']);

        $serializer = $this->createStub(SerializerInterface::class);
        $serializer->method('encode')->willReturnCallback(static fn (Envelope $envelope) => ['body' => 'body '.$envelope->getMessage()->getMessage(), 'headers' => ['type' => DummyMessage::class]]);

        $sent = (new DoctrineSender($connection, $serializer))->sendBatch($envelopes);

        $this->assertSame(['a', 'b'], array_keys($sent));
        $this->assertSame('15', $sent['a']->last(TransportMessageIdStamp::class)?->getId());
        $this->assertSame('16', $sent['b']->last(TransportMessageIdStamp::class)?->getId());
    }

    public function testSendBatchWithoutIds()
    {
        $envelopes = [new Envelope(new DummyMessage('a')), new Envelope(new DummyMessage('b'))];

        $connection = $this->createStub(Connection::class);
        $connection->method('sendBatch')->willReturn([]);

        $serializer = $this->createStub(SerializerInterface::class);
        $serializer->method('encode')->willReturn(['body' => '...']);

        $this->assertSame($envelopes, (new DoctrineSender($connection, $serializer))->sendBatch($envelopes));
    }

    public function testSendBatchConvertsDbalExceptions()
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('sendBatch')->willThrowException(new class('Server has gone away.') extends \RuntimeException implements DBALException {});

        $serializer = $this->createStub(SerializerInterface::class);
        $serializer->method('encode')->willReturn(['body' => '...']);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Server has gone away.');

        (new DoctrineSender($connection, $serializer))->sendBatch([new Envelope(new DummyMessage('a'))]);
    }
}
