<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\Redis\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\Redis\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Bridge\Redis\Transport\Connection;
use Symfony\Component\Messenger\Bridge\Redis\Transport\RedisSender;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\BatchSendFailedException;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

class RedisSenderTest extends TestCase
{
    public function testSend()
    {
        $envelope = new Envelope(new DummyMessage('Oy'));
        $encoded = ['body' => '...', 'headers' => ['type' => DummyMessage::class]];

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('add')->with($encoded['body'], $encoded['headers'])->willReturn('THE_MESSAGE_ID');

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->once())->method('encode')->with($envelope)->willReturn($encoded);

        $sender = new RedisSender($connection, $serializer);

        /** @var TransportMessageIdStamp $stamp */
        $stamp = $sender->send($envelope)->last(TransportMessageIdStamp::class);

        $this->assertNotNull($stamp, \sprintf('A "%s" stamp should be added', TransportMessageIdStamp::class));
        $this->assertSame('THE_MESSAGE_ID', $stamp->getId());
    }

    public function testSendBatch()
    {
        $envelopes = ['a' => new Envelope(new DummyMessage('a')), 'b' => new Envelope(new DummyMessage('b'), [new DelayStamp(500)])];

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('addBatch')->with([
            'a' => ['body a', ['type' => DummyMessage::class], 0],
            'b' => ['body b', ['type' => DummyMessage::class], 500],
        ])->willReturn([['a' => '1-0', 'b' => 'uniqid'], []]);

        $serializer = $this->createStub(SerializerInterface::class);
        $serializer->method('encode')->willReturnCallback(static fn (Envelope $envelope) => ['body' => 'body '.$envelope->getMessage()->getMessage(), 'headers' => ['type' => DummyMessage::class]]);

        $sent = (new RedisSender($connection, $serializer))->sendBatch($envelopes);

        $this->assertSame(['a', 'b'], array_keys($sent));
        $this->assertSame('1-0', $sent['a']->last(TransportMessageIdStamp::class)?->getId());
        $this->assertSame('uniqid', $sent['b']->last(TransportMessageIdStamp::class)?->getId());
    }

    public function testSendBatchTellsWhichMessagesWereSent()
    {
        $envelopes = ['a' => new Envelope(new DummyMessage('a')), 'b' => new Envelope(new DummyMessage('b')), 'c' => new Envelope(new DummyMessage('c'))];

        $connection = $this->createStub(Connection::class);
        $connection->method('addBatch')->willReturn([['c' => '1-1', 'a' => '1-0'], ['b' => $exception = new TransportException('WRONGTYPE')]]);

        $serializer = $this->createStub(SerializerInterface::class);
        $serializer->method('encode')->willReturn(['body' => '...']);

        try {
            (new RedisSender($connection, $serializer))->sendBatch($envelopes);
            $this->fail('An exception should have been thrown.');
        } catch (BatchSendFailedException $e) {
            $this->assertSame(['a', 'c'], array_keys($e->getEnvelopes()));
            $this->assertSame('1-0', $e->getEnvelopes()['a']->last(TransportMessageIdStamp::class)?->getId());
            $this->assertSame('1-1', $e->getEnvelopes()['c']->last(TransportMessageIdStamp::class)?->getId());
            $this->assertSame(['b' => $exception], $e->getExceptions());
        }
    }
}
