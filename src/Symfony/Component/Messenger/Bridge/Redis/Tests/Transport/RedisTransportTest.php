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
use Symfony\Component\Messenger\Bridge\Redis\Transport\RedisReceivedStamp;
use Symfony\Component\Messenger\Bridge\Redis\Transport\RedisTransport;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\Serializer as SerializerComponent;

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
            $connection = $this->createStub(Connection::class)
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

        $serializer->expects($this->once())->method('decode')->with(['body' => 'body', 'headers' => ['my' => 'header']])->willReturn(new Envelope($decodedMessage));
        $connection->method('get')->willReturn([$redisEnvelope]);

        $envelopes = $transport->get();
        $this->assertSame($decodedMessage, $envelopes[0]->getMessage());
    }

    public function testAll()
    {
        $serializer = $this->createSerializer();

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('findAll')->with(50)->willReturn([
            $this->createRedisEnvelope(),
            $this->createRedisEnvelope(),
        ]);

        $transport = $this->getTransport($serializer, $connection);

        $envelopes = [...$transport->all(50)];
        $this->assertCount(2, $envelopes);
        $this->assertEquals(new DummyMessage('Hi'), $envelopes[0]->getMessage());
    }

    public function testFind()
    {
        $serializer = $this->createSerializer();

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('find')->with('5')->willReturn($this->createRedisEnvelope());

        $transport = $this->getTransport($serializer, $connection);

        $this->assertEquals(new DummyMessage('Hi'), $transport->find('5')->getMessage());
    }

    public function testKeepalive()
    {
        $transport = $this->getTransport(
            null,
            $connection = $this->createMock(Connection::class),
        );

        $connection->expects($this->once())->method('keepalive')->with('redisid-123');

        $transport->keepalive(new Envelope(new DummyMessage('foo'), [new RedisReceivedStamp('redisid-123')]));
    }

    private function getTransport(?SerializerInterface $serializer = null, ?Connection $connection = null): RedisTransport
    {
        $serializer ??= $this->createStub(SerializerInterface::class);
        $connection ??= $this->createStub(Connection::class);

        return new RedisTransport($connection, $serializer);
    }

    private function createRedisEnvelope(): array
    {
        return [
            'id' => '1-0',
            'data' => [
                'message' => json_encode([
                    'body' => '{"message": "Hi"}',
                    'headers' => [
                        'type' => DummyMessage::class,
                    ],
                ]),
            ],
        ];
    }

    private function createSerializer(): Serializer
    {
        return new Serializer(
            new SerializerComponent\Serializer([new SerializerComponent\Normalizer\ObjectNormalizer()], ['json' => new SerializerComponent\Encoder\JsonEncoder()])
        );
    }
}
