<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\Doctrine\Tests\Transport;

use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\DBAL\Schema\Schema;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\Doctrine\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\Connection;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineReceivedStamp;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransport;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\Serializer as SerializerComponent;

class DoctrineTransportTest extends TestCase
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

        $doctrineEnvelope = [
            'id' => '5',
            'body' => 'body',
            'headers' => ['my' => 'header'],
        ];

        $serializer->expects($this->once())->method('decode')->with(['body' => 'body', 'headers' => ['my' => 'header']])->willReturn(new Envelope($decodedMessage));
        $connection->method('get')->willReturn([$doctrineEnvelope]);

        $envelopes = $transport->get();
        $this->assertSame($decodedMessage, $envelopes[0]->getMessage());
    }

    public function testAll()
    {
        $serializer = $this->createSerializer();

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('findAll')->with(50)->willReturn([
            $this->createDoctrineEnvelope(),
            $this->createDoctrineEnvelope(),
        ]);

        $transport = $this->getTransport($serializer, $connection);

        $envelopes = [...$transport->all(50)];
        $this->assertEquals(new DummyMessage('Hi'), $envelopes[0]->getMessage());
    }

    public function testFind()
    {
        $serializer = $this->createSerializer();

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('find')->with('5')->willReturn($this->createDoctrineEnvelope());

        $transport = $this->getTransport($serializer, $connection);

        $this->assertEquals(new DummyMessage('Hi'), $transport->find('5')->getMessage());
    }

    public function testConfigureSchema()
    {
        $transport = $this->getTransport(
            null,
            $connection = $this->createMock(Connection::class)
        );

        $schema = new Schema();
        $dbalConnection = $this->createStub(DbalConnection::class);

        $isSameDatabaseChecker = static fn () => true;
        $connection->expects($this->once())
            ->method('configureSchema')
            ->with($schema, $dbalConnection, $isSameDatabaseChecker);

        $transport->configureSchema($schema, $dbalConnection, $isSameDatabaseChecker);
    }

    public function testKeepalive()
    {
        $transport = $this->getTransport(
            null,
            $connection = $this->createMock(Connection::class)
        );

        $envelope = new Envelope(new \stdClass(), [new DoctrineReceivedStamp('1')]);

        $connection->expects($this->once())
            ->method('keepalive')
            ->with('1');

        $transport->keepalive($envelope);
    }

    public function testSendBatch()
    {
        $serializer = $this->createStub(SerializerInterface::class);
        $serializer->method('encode')->willReturn(['body' => '...']);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('sendBatch')->with([['...', [], 0], ['...', [], 0]])->willReturn([1 => '16']);

        $envelopes = $this->getTransport($serializer, $connection)->sendBatch([new Envelope(new DummyMessage('a')), new Envelope(new DummyMessage('b'))]);

        $this->assertNull($envelopes[0]->last(TransportMessageIdStamp::class));
        $this->assertSame('16', $envelopes[1]->last(TransportMessageIdStamp::class)?->getId());
    }

    private function getTransport(?SerializerInterface $serializer = null, ?Connection $connection = null): DoctrineTransport
    {
        $serializer ??= $this->createStub(SerializerInterface::class);
        $connection ??= $this->createStub(Connection::class);

        return new DoctrineTransport($connection, $serializer);
    }

    private function createDoctrineEnvelope(): array
    {
        return [
            'id' => 1,
            'body' => '{"message": "Hi"}',
            'headers' => [
                'type' => DummyMessage::class,
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
