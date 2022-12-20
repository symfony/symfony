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
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

class RedisSenderTest extends TestCase
{
    public function testSend()
    {
        $envelope = new Envelope(new DummyMessage('Oy'));
        $encoded = ['body' => '...', 'headers' => ['type' => DummyMessage::class]];

        $connection = self::createMock(Connection::class);
        $connection->expects(self::once())->method('add')->with($encoded['body'], $encoded['headers']);

        $serializer = self::createMock(SerializerInterface::class);
        $serializer->method('encode')->with($envelope)->willReturnOnConsecutiveCalls($encoded);

        $sender = new RedisSender($connection, $serializer);
        $sender->send($envelope);
    }
}
