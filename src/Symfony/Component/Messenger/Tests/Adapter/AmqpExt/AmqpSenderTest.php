<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Adapter\AmqpExt;

use Symfony\Component\Messenger\Adapter\AmqpExt\AmqpSender;
use Symfony\Component\Messenger\Adapter\AmqpExt\Connection;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Transport\Serialization\EncoderInterface;

/**
 * @requires extension amqp
 */
class AmqpSenderTest extends TestCase
{
    public function testItSendsTheEncodedMessage()
    {
        $message = new DummyMessage('Oy');
        $encoded = array('body' => '...', 'headers' => array('type' => DummyMessage::class));

        $encoder = $this->getMockBuilder(EncoderInterface::class)->getMock();
        $encoder->method('encode')->with($message)->willReturnOnConsecutiveCalls($encoded);

        $connection = $this->getMockBuilder(Connection::class)->disableOriginalConstructor()->getMock();
        $connection->expects($this->once())->method('publish')->with($encoded['body'], $encoded['headers']);

        $sender = new AmqpSender($encoder, $connection);
        $sender->send($message);
    }
}
