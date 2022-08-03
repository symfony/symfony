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
use Symfony\Component\Messenger\Bridge\Redis\Transport\RedisTransport;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

class RedisTransportTest extends TestCase
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

        $redisEnvelope = [
            'id' => '5',
            'data' => [
                'message' => json_encode([
                    'body' => 'body',
                    'headers' => ['my' => 'header'],
                ]),
            ],
        ];

        $serializer->method('decode')->with(['body' => 'body', 'headers' => ['my' => 'header']])->willReturn(new Envelope($decodedMessage));
        $connection->method('get')->willReturn($redisEnvelope);

        $envelopes = $transport->get();
        $this->assertSame($decodedMessage, $envelopes[0]->getMessage());
    }

    private function getTransport(SerializerInterface $serializer = null, Connection $connection = null): RedisTransport
    {
        $serializer ??= $this->createMock(SerializerInterface::class);
        $connection ??= $this->createMock(Connection::class);

        return new RedisTransport($connection, $serializer);
    }
}
