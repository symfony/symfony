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
use Symfony\Component\Messenger\Transport\Serialization\DecoderInterface;
use Symfony\Component\Messenger\Transport\Serialization\EncoderInterface;
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
            null,
            $decoder = $this->getMockBuilder(DecoderInterface::class)->getMock(),
            $connection = $this->getMockBuilder(Connection::class)->disableOriginalConstructor()->getMock()
        );

        $decodedMessage = new DummyMessage('Decoded.');

        $amqpEnvelope = $this->getMockBuilder(\AMQPEnvelope::class)->getMock();
        $amqpEnvelope->method('getBody')->willReturn('body');
        $amqpEnvelope->method('getHeaders')->willReturn(array('my' => 'header'));

        $decoder->method('decode')->with(array('body' => 'body', 'headers' => array('my' => 'header')))->willReturn(Envelope::wrap($decodedMessage));
        $connection->method('get')->willReturn($amqpEnvelope);

        $transport->receive(function (Envelope $envelope) use ($transport, $decodedMessage) {
            $this->assertSame($decodedMessage, $envelope->getMessage());

            $transport->stop();
        });
    }

    private function getTransport(EncoderInterface $encoder = null, DecoderInterface $decoder = null, Connection $connection = null)
    {
        $encoder = $encoder ?: $this->getMockBuilder(EncoderInterface::class)->getMock();
        $decoder = $decoder ?: $this->getMockBuilder(DecoderInterface::class)->getMock();
        $connection = $connection ?: $this->getMockBuilder(Connection::class)->disableOriginalConstructor()->getMock();

        return new AmqpTransport($encoder, $decoder, $connection);
    }
}
