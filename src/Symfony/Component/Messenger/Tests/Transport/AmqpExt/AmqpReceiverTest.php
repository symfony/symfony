<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Transport\AmqpExt;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Transport\AmqpExt\AmqpReceiver;
use Symfony\Component\Messenger\Transport\AmqpExt\Connection;
use Symfony\Component\Messenger\Transport\AmqpExt\Exception\RejectMessageExceptionInterface;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\Serializer as SerializerComponent;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

/**
 * @requires extension amqp
 */
class AmqpReceiverTest extends TestCase
{
    public function testItSendTheDecodedMessageToTheHandlerAndAcknowledgeIt()
    {
        $serializer = new Serializer(
            new SerializerComponent\Serializer(array(new ObjectNormalizer()), array('json' => new JsonEncoder()))
        );

        $envelope = $this->getMockBuilder(\AMQPEnvelope::class)->getMock();
        $envelope->method('getBody')->willReturn('{"message": "Hi"}');
        $envelope->method('getHeaders')->willReturn(array(
            'type' => DummyMessage::class,
        ));

        $connection = $this->getMockBuilder(Connection::class)->disableOriginalConstructor()->getMock();
        $connection->method('get')->willReturn($envelope);

        $connection->expects($this->once())->method('ack')->with($envelope);

        $receiver = new AmqpReceiver($connection, $serializer);
        $receiver->receive(function (?Envelope $envelope) use ($receiver) {
            $this->assertEquals(new DummyMessage('Hi'), $envelope->getMessage());
            $receiver->stop();
        });
    }

    /**
     * @expectedException \Symfony\Component\Messenger\Tests\Transport\AmqpExt\InterruptException
     */
    public function testItNonAcknowledgeTheMessageIfAnExceptionHappened()
    {
        $serializer = new Serializer(
            new SerializerComponent\Serializer(array(new ObjectNormalizer()), array('json' => new JsonEncoder()))
        );

        $envelope = $this->getMockBuilder(\AMQPEnvelope::class)->getMock();
        $envelope->method('getBody')->willReturn('{"message": "Hi"}');
        $envelope->method('getHeaders')->willReturn(array(
            'type' => DummyMessage::class,
        ));

        $connection = $this->getMockBuilder(Connection::class)->disableOriginalConstructor()->getMock();
        $connection->method('get')->willReturn($envelope);

        $connection->expects($this->once())->method('nack')->with($envelope);

        $receiver = new AmqpReceiver($connection, $serializer);
        $receiver->receive(function () {
            throw new InterruptException('Well...');
        });
    }

    /**
     * @expectedException \Symfony\Component\Messenger\Tests\Transport\AmqpExt\WillNeverWorkException
     */
    public function testItRejectsTheMessageIfTheExceptionIsARejectMessageExceptionInterface()
    {
        $serializer = new Serializer(
            new SerializerComponent\Serializer(array(new ObjectNormalizer()), array('json' => new JsonEncoder()))
        );

        $envelope = $this->getMockBuilder(\AMQPEnvelope::class)->getMock();
        $envelope->method('getBody')->willReturn('{"message": "Hi"}');
        $envelope->method('getHeaders')->willReturn(array(
            'type' => DummyMessage::class,
        ));

        $connection = $this->getMockBuilder(Connection::class)->disableOriginalConstructor()->getMock();
        $connection->method('get')->willReturn($envelope);
        $connection->expects($this->once())->method('reject')->with($envelope);

        $receiver = new AmqpReceiver($connection, $serializer);
        $receiver->receive(function () {
            throw new WillNeverWorkException('Well...');
        });
    }
}

class InterruptException extends \Exception
{
}

class WillNeverWorkException extends \Exception implements RejectMessageExceptionInterface
{
}
