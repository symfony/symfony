<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\Beanstalkd\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\Beanstalkd\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Bridge\Beanstalkd\Transport\BeanstalkdPriorityStamp;
use Symfony\Component\Messenger\Bridge\Beanstalkd\Transport\BeanstalkdSender;
use Symfony\Component\Messenger\Bridge\Beanstalkd\Transport\BeanstalkdTubeStamp;
use Symfony\Component\Messenger\Bridge\Beanstalkd\Transport\Connection;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\Sender\OutboxSender;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

final class BeanstalkdSenderTest extends TestCase
{
    public function testSend()
    {
        $envelope = new Envelope(new DummyMessage('Oy'));
        $encoded = ['body' => '...', 'headers' => ['type' => DummyMessage::class]];

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('send')
            ->with($encoded['body'], $encoded['headers'], 0, null)
            ->willReturn('1')
        ;

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->once())->method('encode')->with($envelope)->willReturn($encoded);

        $sender = new BeanstalkdSender($connection, $serializer);
        $actualEnvelope = $sender->send($envelope);

        /** @var TransportMessageIdStamp $transportMessageIdStamp */
        $transportMessageIdStamp = $actualEnvelope->last(TransportMessageIdStamp::class);
        $this->assertNotNull($transportMessageIdStamp);
        $this->assertSame('1', $transportMessageIdStamp->getId());
    }

    public function testSendWithDelay()
    {
        $envelope = (new Envelope(new DummyMessage('Oy')))->with(new DelayStamp(500));
        $encoded = ['body' => '...', 'headers' => ['type' => DummyMessage::class]];

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('send')->with($encoded['body'], $encoded['headers'], 500, null);

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->once())->method('encode')->with($envelope)->willReturn($encoded);

        $sender = new BeanstalkdSender($connection, $serializer);
        $sender->send($envelope);
    }

    public function testSendWithPriority()
    {
        $envelope = (new Envelope(new DummyMessage('Oy')))->with(new BeanstalkdPriorityStamp(2));
        $encoded = ['body' => '...', 'headers' => ['type' => DummyMessage::class]];

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('send')->with($encoded['body'], $encoded['headers'], 0, 2);

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->once())->method('encode')->with($envelope)->willReturn($encoded);

        $sender = new BeanstalkdSender($connection, $serializer);
        $sender->send($envelope);
    }

    public function testSendWithSpecificTubeName()
    {
        $tube = 'foo';
        $envelope = (new Envelope(new DummyMessage('Oy')))->with(new BeanstalkdTubeStamp($tube));
        $encoded = ['body' => '...', 'headers' => ['type' => DummyMessage::class]];

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('send')->with($encoded['body'], $encoded['headers'], 0, null, $tube);

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->once())->method('encode')->with($envelope)->willReturn($encoded);

        $sender = new BeanstalkdSender($connection, $serializer);
        $sender->send($envelope);
    }

    public function testTubeStampIsKeptByAnOutbox()
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('send')->with($this->anything(), $this->anything(), 0, null, 'foo')->willReturn('1');

        $outbox = new InMemoryTransport(new PhpSerializer());
        $sender = new OutboxSender(new BeanstalkdSender($connection, new PhpSerializer()), $outbox, 'beanstalkd', 'outbox');
        $sender->send((new Envelope(new DummyMessage('Oy')))->with(new BeanstalkdTubeStamp('foo')));

        [$stored] = $outbox->get();
        $sender->send($stored->with(new ReceivedStamp('outbox')));
    }
}
