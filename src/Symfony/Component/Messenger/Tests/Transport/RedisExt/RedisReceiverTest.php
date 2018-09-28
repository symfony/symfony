<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Transport\RedisExt;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Transport\RedisExt\Connection;
use Symfony\Component\Messenger\Transport\RedisExt\Exception\RejectMessageExceptionInterface;
use Symfony\Component\Messenger\Transport\RedisExt\RedisReceiver;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\Serializer as SerializerComponent;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

/**
 * @requires extension redis
 */
class RedisReceiverTest extends TestCase
{
    public function testItSendTheDecodedMessageToTheHandlerAndAcknowledgeIt()
    {
        $serializer = new Serializer(
            new SerializerComponent\Serializer(array(new ObjectNormalizer()), array('json' => new JsonEncoder()))
        );

        $envelope = Envelope::wrap(new DummyMessage('Hi'));
        $encoded = $serializer->encode($envelope);

        $connection = $this->getMockBuilder(Connection::class)->disableOriginalConstructor()->getMock();
        $connection->method('waitAndGet')->willReturn($encoded);

        $connection->expects($this->once())->method('ack')->with($encoded);

        $receiver = new RedisReceiver($connection, $serializer);
        $receiver->receive(function (?Envelope $envelope) use ($receiver) {
            $this->assertEquals(new DummyMessage('Hi'), $envelope->getMessage());
            $receiver->stop();
        });
    }

    public function testItSendNoMessageToTheHandler()
    {
        $serializer = new Serializer(
            new SerializerComponent\Serializer(array(new ObjectNormalizer()), array('json' => new JsonEncoder()))
        );

        $connection = $this->getMockBuilder(Connection::class)->disableOriginalConstructor()->getMock();
        $connection->method('waitAndGet')->willReturn(null);

        $receiver = new RedisReceiver($connection, $serializer);
        $receiver->receive(function (?Envelope $envelope) use ($receiver) {
            $this->assertNull($envelope);
            $receiver->stop();
        });
    }

    /**
     * @expectedException \Symfony\Component\Messenger\Tests\Transport\RedisExt\InterruptException
     */
    public function testItNonAcknowledgeTheMessageIfAnExceptionHappened()
    {
        $serializer = new Serializer(
            new SerializerComponent\Serializer(array(new ObjectNormalizer()), array('json' => new JsonEncoder()))
        );

        $envelope = Envelope::wrap(new DummyMessage('Hi'));
        $encoded = $serializer->encode($envelope);

        $connection = $this->getMockBuilder(Connection::class)->disableOriginalConstructor()->getMock();
        $connection->method('waitAndGet')->willReturn($encoded);
        $connection->expects($this->once())->method('requeue')->with($encoded);

        $receiver = new RedisReceiver($connection, $serializer);
        $receiver->receive(function () {
            throw new InterruptException('Well...');
        });
    }

    /**
     * @expectedException \Symfony\Component\Messenger\Tests\Transport\RedisExt\WillNeverWorkException
     */
    public function testItRejectsTheMessageIfTheExceptionIsARejectMessageExceptionInterface()
    {
        $serializer = new Serializer(
            new SerializerComponent\Serializer(array(new ObjectNormalizer()), array('json' => new JsonEncoder()))
        );

        $envelope = Envelope::wrap(new DummyMessage('Hi'));
        $encoded = $serializer->encode($envelope);

        $connection = $this->getMockBuilder(Connection::class)->disableOriginalConstructor()->getMock();
        $connection->method('waitAndGet')->willReturn($encoded);
        $connection->expects($this->once())->method('reject')->with($encoded);

        $receiver = new RedisReceiver($connection, $serializer);
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
