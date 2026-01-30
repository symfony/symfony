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

use Doctrine\DBAL\Driver\PDO\Exception;
use Doctrine\DBAL\Exception\DeadlockException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\Doctrine\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\Connection;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineReceivedStamp;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineReceiver;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\Serializer as SerializerComponent;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class DoctrineReceiverTest extends TestCase
{
    public function testItReturnsTheDecodedMessageToTheHandler(): void
    {
        $serializer = $this->createSerializer();

        $doctrineEnvelope = $this->createDoctrineEnvelope();
        $connection = $this->createStub(Connection::class);
        $connection->method('get')->willReturn($doctrineEnvelope);

        $receiver = new DoctrineReceiver($connection, $serializer);
        $actualEnvelopes = $receiver->get();
        $this->assertCount(1, $actualEnvelopes);
        /** @var Envelope $actualEnvelope */
        $actualEnvelope = $actualEnvelopes[0];
        $this->assertEquals(new DummyMessage('Hi'), $actualEnvelope->getMessage());

        /** @var DoctrineReceivedStamp $doctrineReceivedStamp */
        $doctrineReceivedStamp = $actualEnvelope->last(DoctrineReceivedStamp::class);
        $this->assertNotNull($doctrineReceivedStamp);
        $this->assertSame('1', $doctrineReceivedStamp->getId());

        /** @var TransportMessageIdStamp $transportMessageIdStamp */
        $transportMessageIdStamp = $actualEnvelope->last(TransportMessageIdStamp::class);
        $this->assertNotNull($transportMessageIdStamp);
        $this->assertSame(1, $transportMessageIdStamp->getId());
    }

    public function testItRejectTheMessageIfThereIsAMessageDecodingFailedException(): void
    {
        $this->expectException(MessageDecodingFailedException::class);
        $serializer = $this->createStub(PhpSerializer::class);
        $serializer->method('decode')->willThrowException(new MessageDecodingFailedException());

        $doctrineEnvelop = $this->createDoctrineEnvelope();
        $connection = $this->createMock(Connection::class);
        $connection->method('get')->willReturn($doctrineEnvelop);
        $connection->expects($this->once())->method('reject');

        $receiver = new DoctrineReceiver($connection, $serializer);
        $receiver->get();
    }

    public function testOccursRetryableExceptionFromConnection(): void
    {
        $serializer = $this->createSerializer();
        $connection = $this->createStub(Connection::class);
        $connection->method('get')->willThrowException(new DeadlockException(Exception::new(new \PDOException('Deadlock', 40001)), null));

        $receiver = new DoctrineReceiver($connection, $serializer);
        $this->assertSame([], $receiver->get());
        $this->assertSame([], $receiver->get());
        try {
            $receiver->get();
        } catch (TransportException $exception) {
            // skip, and retry
        }
        $this->assertSame([], $receiver->get());
        $this->assertSame([], $receiver->get());
        $this->expectException(TransportException::class);
        $receiver->get();
    }

    public function testGetReplacesExistingTransportMessageIdStamps(): void
    {
        $serializer = $this->createSerializer();

        $doctrineEnvelope = $this->createRetriedDoctrineEnvelope();
        $connection = $this->createStub(Connection::class);
        $connection->method('get')->willReturn($doctrineEnvelope);

        $receiver = new DoctrineReceiver($connection, $serializer);
        $actualEnvelopes = $receiver->get();
        /** @var Envelope $actualEnvelope */
        $actualEnvelope = $actualEnvelopes[0];
        $messageIdStamps = $actualEnvelope->all(TransportMessageIdStamp::class);

        $this->assertCount(1, $messageIdStamps);
    }

    public function testAll(): void
    {
        $serializer = $this->createSerializer();

        $doctrineEnvelope1 = $this->createDoctrineEnvelope();
        $doctrineEnvelope2 = $this->createDoctrineEnvelope();
        $connection = $this->createStub(Connection::class);
        $connection->method('findAll')->with(50)->willReturn([$doctrineEnvelope1, $doctrineEnvelope2]);

        $receiver = new DoctrineReceiver($connection, $serializer);
        $actualEnvelopes = iterator_to_array($receiver->all(50));
        $this->assertCount(2, $actualEnvelopes);
        $this->assertEquals(new DummyMessage('Hi'), $actualEnvelopes[0]->getMessage());
    }

    public function testAllReplacesExistingTransportMessageIdStamps(): void
    {
        $serializer = $this->createSerializer();

        $doctrineEnvelope1 = $this->createRetriedDoctrineEnvelope();
        $doctrineEnvelope2 = $this->createRetriedDoctrineEnvelope();
        $connection = $this->createStub(Connection::class);
        $connection->method('findAll')->willReturn([$doctrineEnvelope1, $doctrineEnvelope2]);

        $receiver = new DoctrineReceiver($connection, $serializer);
        $actualEnvelopes = $receiver->all();
        foreach ($actualEnvelopes as $actualEnvelope) {
            $messageIdStamps = $actualEnvelope->all(TransportMessageIdStamp::class);

            $this->assertCount(1, $messageIdStamps);
        }
    }

    public function testFind(): void
    {
        $serializer = $this->createSerializer();

        $doctrineEnvelope = $this->createDoctrineEnvelope();
        $connection = $this->createStub(Connection::class);
        $connection->method('find')->with(10)->willReturn($doctrineEnvelope);

        $receiver = new DoctrineReceiver($connection, $serializer);
        $actualEnvelope = $receiver->find(10);
        $this->assertEquals(new DummyMessage('Hi'), $actualEnvelope->getMessage());
    }

    public function testFindReplacesExistingTransportMessageIdStamps(): void
    {
        $serializer = $this->createSerializer();

        $doctrineEnvelope = $this->createRetriedDoctrineEnvelope();
        $connection = $this->createStub(Connection::class);
        $connection->method('find')->with(3)->willReturn($doctrineEnvelope);

        $receiver = new DoctrineReceiver($connection, $serializer);
        $actualEnvelope = $receiver->find(3);
        $messageIdStamps = $actualEnvelope->all(TransportMessageIdStamp::class);

        $this->assertCount(1, $messageIdStamps);
    }

    public function testAck(): void
    {
        $serializer = $this->createSerializer();
        $connection = $this->createMock(Connection::class);

        $envelope = new Envelope(new \stdClass(), [new DoctrineReceivedStamp('1')]);
        $receiver = new DoctrineReceiver($connection, $serializer);

        $connection
            ->expects($this->once())
            ->method('ack')
            ->with('1')
            ->willReturn(true);

        $receiver->ack($envelope);
    }

    public function testAckThrowsRetryableException(): void
    {
        $serializer = $this->createSerializer();
        $connection = $this->createMock(Connection::class);

        $envelope = new Envelope(new \stdClass(), [new DoctrineReceivedStamp('1')]);
        $receiver = new DoctrineReceiver($connection, $serializer);

        $driverException = Exception::new(new \PDOException('Deadlock', 40001));
        $deadlockException = new DeadlockException($driverException, null);

        $connection
            ->expects($this->exactly(2))
            ->method('ack')
            ->with('1')
            ->willReturnOnConsecutiveCalls(
                $this->throwException($deadlockException),
                true,
            );

        $receiver->ack($envelope);
    }

    public function testAckThrowsRetryableExceptionAndRetriesFail(): void
    {
        $serializer = $this->createSerializer();
        $connection = $this->createMock(Connection::class);

        $envelope = new Envelope(new \stdClass(), [new DoctrineReceivedStamp('1')]);
        $receiver = new DoctrineReceiver($connection, $serializer);

        $driverException = Exception::new(new \PDOException('Deadlock', 40001));
        $deadlockException = new DeadlockException($driverException, null);

        $connection
            ->expects($this->exactly(4))
            ->method('ack')
            ->with('1')
            ->willThrowException($deadlockException);

        $this->expectException(TransportException::class);
        $receiver->ack($envelope);
    }

    public function testAckThrowsException(): void
    {
        $serializer = $this->createSerializer();
        $connection = $this->createMock(Connection::class);

        $envelope = new Envelope(new \stdClass(), [new DoctrineReceivedStamp('1')]);
        $receiver = new DoctrineReceiver($connection, $serializer);

        $exception = new \RuntimeException();

        $connection
            ->expects($this->once())
            ->method('ack')
            ->with('1')
            ->willThrowException($exception);

        $this->expectException($exception::class);
        $receiver->ack($envelope);
    }

    public function testReject(): void
    {
        $serializer = $this->createSerializer();
        $connection = $this->createMock(Connection::class);

        $envelope = new Envelope(new \stdClass(), [new DoctrineReceivedStamp('1')]);
        $receiver = new DoctrineReceiver($connection, $serializer);

        $connection
            ->expects($this->once())
            ->method('reject')
            ->with('1')
            ->willReturn(true);

        $receiver->reject($envelope);
    }

    public function testRejectThrowsRetryableException(): void
    {
        $serializer = $this->createSerializer();
        $connection = $this->createMock(Connection::class);

        $envelope = new Envelope(new \stdClass(), [new DoctrineReceivedStamp('1')]);
        $receiver = new DoctrineReceiver($connection, $serializer);

        $driverException = Exception::new(new \PDOException('Deadlock', 40001));
        $deadlockException = new DeadlockException($driverException, null);

        $connection
            ->expects($this->exactly(2))
            ->method('reject')
            ->with('1')
            ->willReturnOnConsecutiveCalls(
                $this->throwException($deadlockException),
                true,
            );

        $receiver->reject($envelope);
    }

    public function testRejectThrowsRetryableExceptionAndRetriesFail(): void
    {
        $serializer = $this->createSerializer();
        $connection = $this->createMock(Connection::class);

        $envelope = new Envelope(new \stdClass(), [new DoctrineReceivedStamp('1')]);
        $receiver = new DoctrineReceiver($connection, $serializer);

        $driverException = Exception::new(new \PDOException('Deadlock', 40001));
        $deadlockException = new DeadlockException($driverException, null);

        $connection
            ->expects($this->exactly(4))
            ->method('reject')
            ->with('1')
            ->willThrowException($deadlockException);

        $this->expectException(TransportException::class);
        $receiver->reject($envelope);
    }

    public function testRejectThrowsException(): void
    {
        $serializer = $this->createSerializer();
        $connection = $this->createMock(Connection::class);

        $envelope = new Envelope(new \stdClass(), [new DoctrineReceivedStamp('1')]);
        $receiver = new DoctrineReceiver($connection, $serializer);

        $exception = new \RuntimeException();

        $connection
            ->expects($this->once())
            ->method('reject')
            ->with('1')
            ->willThrowException($exception);

        $this->expectException($exception::class);
        $receiver->reject($envelope);
    }

    public function testKeepalive(): void
    {
        $serializer = $this->createSerializer();
        $connection = $this->createMock(Connection::class);

        $envelope = new Envelope(new \stdClass(), [new DoctrineReceivedStamp('1')]);
        $receiver = new DoctrineReceiver($connection, $serializer);

        $connection
            ->expects($this->once())
            ->method('keepalive')
            ->with('1');

        $receiver->keepalive($envelope);
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

    private function createRetriedDoctrineEnvelope(): array
    {
        return [
            'id' => 3,
            'body' => '{"message": "Hi"}',
            'headers' => [
                'type' => DummyMessage::class,
                'X-Message-Stamp-Symfony\Component\Messenger\Stamp\BusNameStamp' => '[{"busName":"messenger.bus.default"}]',
                'X-Message-Stamp-Symfony\Component\Messenger\Stamp\TransportMessageIdStamp' => '[{"id":1},{"id":2}]',
                'X-Message-Stamp-Symfony\Component\Messenger\Stamp\ErrorDetailsStamp' => '[{"exceptionClass":"Symfony\\\\Component\\\\Messenger\\\\Exception\\\\RecoverableMessageHandlingException","exceptionCode":0,"exceptionMessage":"","flattenException":null}]',
                'X-Message-Stamp-Symfony\Component\Messenger\Stamp\DelayStamp' => '[{"delay":1000},{"delay":1000}]',
                'X-Message-Stamp-Symfony\Component\Messenger\Stamp\RedeliveryStamp' => '[{"retryCount":1,"redeliveredAt":"2025-01-05T13:58:25+00:00"},{"retryCount":2,"redeliveredAt":"2025-01-05T13:59:26+00:00"}]',
                'Content-Type' => 'application/json',
            ],
        ];
    }

    private function createSerializer(): Serializer
    {
        return new Serializer(
            new SerializerComponent\Serializer([new DateTimeNormalizer(), new ArrayDenormalizer(), new ObjectNormalizer()], ['json' => new JsonEncoder()])
        );
    }
}
