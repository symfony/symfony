<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Transport\Semaphore;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Transport\Semaphore\Connection;
use Symfony\Component\Messenger\Transport\Semaphore\SemaphoreEnvelope;
use Symfony\Component\Messenger\Transport\Semaphore\SemaphoreTransport;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * @author Cedrick Oka Baidai <okacedrick@gmail.com>
 */
class SemaphoreTransportTest extends TestCase
{
    public function testItIsATransport()
    {
        $transport = $this->getTransport();

        $this->assertInstanceOf(TransportInterface::class, $transport);
    }

    public function testReceivesMessages()
    {
        $transport = $this->getTransport(
                $serializer = $this->createMock(SerializerInterface::class),
                $connection = $this->createMock(Connection::class)
        );

        $decodedMessage = new DummyMessage('Decoded.');

        $semaphoreEnvelope = $this->getMockBuilder(SemaphoreEnvelope::class)->disableOriginalConstructor()->getMock();
        $semaphoreEnvelope->method('getBody')->willReturn('body');
        $semaphoreEnvelope->method('getHeaders')->willReturn(['my' => 'header']);

        $serializer->method('decode')->with(['body' => 'body', 'headers' => ['my' => 'header']])->willReturn(new Envelope($decodedMessage));
        $connection->method('get')->willReturn($semaphoreEnvelope);

        $envelopes = $transport->get();
        $envelopes = iterator_to_array($transport->get());

        $this->assertSame($decodedMessage, $envelopes[0]->getMessage());
    }

    private function getTransport(SerializerInterface $serializer = null, Connection $connection = null): SemaphoreTransport
    {
        $serializer = $serializer ?: $this->createMock(SerializerInterface::class);
        $connection = $connection ?: $this->createMock(Connection::class);

        return new SemaphoreTransport($connection, $serializer);
    }
}
