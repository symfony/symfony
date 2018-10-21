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
use Symfony\Component\Messenger\Transport\AmqpExt\AmqpTransport;
use Symfony\Component\Messenger\Transport\AmqpExt\Connection;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * @requires extension amqp
 */
class AmqpTransportTest extends TestCase
{
    public function testItIsATransport()
    {
        $transport = $this->getTransport();

        $this->assertInstanceOf(TransportInterface::class, $transport);
    }

    public function testReceivesMessages()
    {
        $transport = $this->getTransport(
            $serializer = $this->getMockBuilder(SerializerInterface::class)->getMock(),
            $connection = $this->getMockBuilder(Connection::class)->disableOriginalConstructor()->getMock()
        );

        $decodedMessage = new DummyMessage('Decoded.');

        $amqpEnvelope = $this->getMockBuilder(\AMQPEnvelope::class)->getMock();
        $amqpEnvelope->method('getBody')->willReturn('body');
        $amqpEnvelope->method('getHeaders')->willReturn(array('my' => 'header'));

        $serializer->method('decode')->with(array('body' => 'body', 'headers' => array('my' => 'header')))->willReturn(new Envelope($decodedMessage));
        $connection->method('get')->willReturn($amqpEnvelope);

        $transport->receive(function (Envelope $envelope) use ($transport, $decodedMessage) {
            $this->assertSame($decodedMessage, $envelope->getMessage());

            $transport->stop();
        });
    }

    private function getTransport(SerializerInterface $serializer = null, Connection $connection = null)
    {
        $serializer = $serializer ?: $this->getMockBuilder(SerializerInterface::class)->getMock();
        $connection = $connection ?: $this->getMockBuilder(Connection::class)->disableOriginalConstructor()->getMock();

        return new AmqpTransport($connection, $serializer);
    }
}
