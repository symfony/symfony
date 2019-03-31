<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Transport\Doctrine;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Transport\Doctrine\Connection;
use Symfony\Component\Messenger\Transport\Doctrine\DoctrineSender;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

class DoctrineSenderTest extends TestCase
{
    public function testSend()
    {
        $envelope = new Envelope(new DummyMessage('Oy'));
        $encoded = ['body' => '...', 'headers' => ['type' => DummyMessage::class]];

        $connection = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->getMock();
        $connection->expects($this->once())->method('send')->with($encoded['body'], $encoded['headers']);

        $serializer = $this->getMockBuilder(SerializerInterface::class)->getMock();
        $serializer->method('encode')->with($envelope)->willReturnOnConsecutiveCalls($encoded);

        $sender = new DoctrineSender($connection, $serializer);
        $sender->send($envelope);
    }

    public function testSendWithDelay()
    {
        $envelope = (new Envelope(new DummyMessage('Oy')))->with(new DelayStamp(500));
        $encoded = ['body' => '...', 'headers' => ['type' => DummyMessage::class]];

        $connection = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->getMock();
        $connection->expects($this->once())->method('send')->with($encoded['body'], $encoded['headers'], 500);

        $serializer = $this->getMockBuilder(SerializerInterface::class)->getMock();
        $serializer->method('encode')->with($envelope)->willReturnOnConsecutiveCalls($encoded);

        $sender = new DoctrineSender($connection, $serializer);
        $sender->send($envelope);
    }
}
