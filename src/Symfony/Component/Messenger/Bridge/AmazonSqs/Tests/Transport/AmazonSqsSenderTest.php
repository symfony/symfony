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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\AmazonSqs\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Bridge\AmazonSqs\Transport\AmazonSqsFifoStamp;
use Symfony\Component\Messenger\Bridge\AmazonSqs\Transport\AmazonSqsSender;
use Symfony\Component\Messenger\Bridge\AmazonSqs\Transport\Connection;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

class AmazonSqsSenderTest extends TestCase
{
    public function testSend(): void
    {
        $envelope = new Envelope(new DummyMessage('Oy'));
        $encoded = ['body' => '...', 'headers' => ['type' => DummyMessage::class]];

        $connection = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->getMock();
        $connection->expects($this->once())->method('send')->with($encoded['body'], $encoded['headers']);

        $serializer = $this->getMockBuilder(SerializerInterface::class)->getMock();
        $serializer->method('encode')->with($envelope)->willReturnOnConsecutiveCalls($encoded);

        $sender = new AmazonSqsSender($connection, $serializer);
        $sender->send($envelope);
    }

    public function testSendWithAmazonSqsFifoStamp(): void
    {
        $envelope = (new Envelope(new DummyMessage('Oy')))
            ->with($stamp = new AmazonSqsFifoStamp('testGroup', 'testDeduplicationId'));

        $encoded = ['body' => '...', 'headers' => ['type' => DummyMessage::class]];

        $connection = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->getMock();
        $connection->expects($this->once())->method('send')
            ->with($encoded['body'], $encoded['headers'], 0, $stamp->getMessageGroupId(), $stamp->getMessageDeduplicationId());

        $serializer = $this->getMockBuilder(SerializerInterface::class)->getMock();
        $serializer->method('encode')->with($envelope)->willReturnOnConsecutiveCalls($encoded);

        $sender = new AmazonSqsSender($connection, $serializer);
        $sender->send($envelope);
    }
}
