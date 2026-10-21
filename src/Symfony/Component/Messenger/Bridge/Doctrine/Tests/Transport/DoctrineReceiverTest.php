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
use Doctrine\DBAL\Exception\ConnectionException;
use Doctrine\DBAL\Exception\ConnectionLost;
use Doctrine\DBAL\Exception\DeadlockException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ClockMock;
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
    public function testItReturnsTheDecodedMessageToTheHandler()
    {
        $serializer = $this->createSerializer();

        $doctrineEnvelope = $this->createDoctrineEnvelope();
        $connection = $this->createStub(Connection::class);
        $connection->method('get')->willReturn([$doctrineEnvelope]);

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

    public function testItReturnsSerializedEnvelopeWhenDecodingFails()
    {
        $serializer = $this->createStub(PhpSerializer::class);
        $serializer->method('decode')->willThrowException(new MessageDecodingFailedException());

        $doctrineEnvelop = $this->createDoctrineEnvelope();
        $connection = $this->createStub(Connection::class);
        $connection->method('get')->willReturn([$doctrineEnvelop]);

        $receiver = new DoctrineReceiver($connection, $serializer);
        $envelopes = $receiver->get();

        $this->assertCount(1, $envelopes);
        $this->assertInstanceOf(MessageDecodingFailedException::class, $envelopes[0]->getMessage());
    }

    public function testOccursRetryableExceptionFromConnection()
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

    public function testGetReplacesExistingTransportMessageIdStamps()
    {
        $serializer = $this->createSerializer();

        $doctrineEnvelope = $this->createRetriedDoctrineEnvelope();
        $connection = $this->createStub(Connection::class);
        $connection->method('get')->willReturn([$doctrineEnvelope]);

        $receiver = new DoctrineReceiver($connection, $serializer);
        $actualEnvelopes = $receiver->get();
        /** @var Envelope $actualEnvelope */
        $actualEnvelope = $actualEnvelopes[0];
        $messageIdStamps = $actualEnvelope->all(TransportMessageIdStamp::class);

        $this->assertCount(1, $messageIdStamps);
    }

    public function testGetUsesFetchSizeWhenProvided()
    {
        $serializer = $this->createSerializer();

        $doctrineEnvelope = $this->createDoctrineEnvelope();
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('get')->with(7)->willReturn([$doctrineEnvelope]);

        $receiver = new DoctrineReceiver($connection, $serializer);
        $actualEnvelopes = $receiver->get(7);

        $this->assertCount(1, $actualEnvelopes);
    }

    public function testAll()
    {
        $serializer = $this->createSerializer();

        $doctrineEnvelope1 = $this->createDoctrineEnvelope();
        $doctrineEnvelope2 = $this->createDoctrineEnvelope();
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('findAll')->with(50)->willReturn([$doctrineEnvelope1, $doctrineEnvelope2]);

        $receiver = new DoctrineReceiver($connection, $serializer);
        $actualEnvelopes = iterator_to_array($receiver->all(50));
        $this->assertCount(2, $actualEnvelopes);
        $this->assertEquals(new DummyMessage('Hi'), $actualEnvelopes[0]->getMessage());
    }

    public function testAllReplacesExistingTransportMessageIdStamps()
    {
        $serializer = $this->createSerializer();

        $doctrineEnvelope1 = $this->createRetriedDoctrineEnvelope();
        $doctrineEnvelope2 = $this->createRetriedDoctrineEnvelope();
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('findAllInBatches')->willReturnCallback(static fn () => yield from [$doctrineEnvelope1, $doctrineEnvelope2]);

        $receiver = new DoctrineReceiver($connection, $serializer);
        $actualEnvelopes = $receiver->all();
        foreach ($actualEnvelopes as $actualEnvelope) {
            $messageIdStamps = $actualEnvelope->all(TransportMessageIdStamp::class);

            $this->assertCount(1, $messageIdStamps);
        }
    }

    public function testAllWithoutLimitReadsTheMessagesInBatches()
    {
        $read = 0;
        $doctrineEnvelope = $this->createDoctrineEnvelope();
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('findAll');
        $connection->expects($this->once())->method('findAllInBatches')->willReturnCallback(static function () use (&$read, $doctrineEnvelope) {
            foreach ([1, 2, 3] as $id) {
                ++$read;

                yield ['id' => $id] + $doctrineEnvelope;
            }
        });

        $receiver = new DoctrineReceiver($connection, $this->createSerializer());
        foreach ($receiver->all() as $envelope) {
            break;
        }

        $this->assertSame(1, $envelope->last(TransportMessageIdStamp::class)->getId());
        $this->assertSame(1, $read);
    }

    public function testAllThrowsATransportExceptionWhenABatchCannotBeRead()
    {
        $doctrineEnvelope = $this->createDoctrineEnvelope();
        $exception = $this->createAccessDenied();
        $connection = $this->createStub(Connection::class);
        $connection->method('findAllInBatches')->willReturnCallback(static function () use ($doctrineEnvelope, $exception) {
            yield $doctrineEnvelope;

            throw $exception;
        });

        $envelopes = [];
        try {
            foreach ((new DoctrineReceiver($connection, $this->createSerializer()))->all() as $envelope) {
                $envelopes[] = $envelope;
            }
            $this->fail('A TransportException should have been thrown.');
        } catch (TransportException $e) {
            $this->assertSame($exception, $e->getPrevious());
        }

        $this->assertCount(1, $envelopes);
    }

    public function testFind()
    {
        $serializer = $this->createSerializer();

        $doctrineEnvelope = $this->createDoctrineEnvelope();
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('find')->with(10)->willReturn($doctrineEnvelope);

        $receiver = new DoctrineReceiver($connection, $serializer);
        $actualEnvelope = $receiver->find(10);
        $this->assertEquals(new DummyMessage('Hi'), $actualEnvelope->getMessage());
    }

    public function testFindReplacesExistingTransportMessageIdStamps()
    {
        $serializer = $this->createSerializer();

        $doctrineEnvelope = $this->createRetriedDoctrineEnvelope();
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('find')->with(3)->willReturn($doctrineEnvelope);

        $receiver = new DoctrineReceiver($connection, $serializer);
        $actualEnvelope = $receiver->find(3);
        $messageIdStamps = $actualEnvelope->all(TransportMessageIdStamp::class);

        $this->assertCount(1, $messageIdStamps);
    }

    public function testAck()
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

    public function testAckThrowsRetryableException()
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

    public function testAckThrowsRetryableExceptionAndRetriesFail()
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

    public function testAckThrowsException()
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

    public function testReject()
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

    public function testRejectThrowsRetryableException()
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

    public function testRejectThrowsRetryableExceptionAndRetriesFail()
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

    public function testRejectThrowsException()
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

    public function testKeepalive()
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

    public function testAckRetriesADeadlockReportedByTheConnection()
    {
        $driverConnection = $this->createMock(\Doctrine\DBAL\Connection::class);
        $driverConnection->expects($this->exactly(2))->method('delete')->with('messenger_messages', ['id' => '1'])->willReturnOnConsecutiveCalls(
            $this->throwException($this->createDeadlockException()),
            1,
        );

        $receiver = new DoctrineReceiver(new Connection([], $driverConnection), $this->createSerializer());
        $receiver->ack(new Envelope(new \stdClass(), [new DoctrineReceivedStamp('1')]));
    }

    public function testRejectRetriesADeadlockReportedByTheConnection()
    {
        $driverConnection = $this->createMock(\Doctrine\DBAL\Connection::class);
        $driverConnection->expects($this->exactly(2))->method('delete')->with('messenger_messages', ['id' => '1'])->willReturnOnConsecutiveCalls(
            $this->throwException($this->createDeadlockException()),
            1,
        );

        $receiver = new DoctrineReceiver(new Connection([], $driverConnection), $this->createSerializer());
        $receiver->reject(new Envelope(new \stdClass(), [new DoctrineReceivedStamp('1')]));
    }

    public function testAckThrowsWhenTheConnectionKeepsDeadlocking()
    {
        $deadlockException = $this->createDeadlockException();
        $driverConnection = $this->createMock(\Doctrine\DBAL\Connection::class);
        $driverConnection->expects($this->exactly(4))->method('delete')->willThrowException($deadlockException);

        $receiver = new DoctrineReceiver(new Connection([], $driverConnection), $this->createSerializer());

        try {
            $receiver->ack(new Envelope(new \stdClass(), [new DoctrineReceivedStamp('1')]));
            $this->fail('A TransportException should have been thrown.');
        } catch (TransportException $e) {
            $this->assertSame($deadlockException, $e->getPrevious());
        }
    }

    public function testGetClosesALostConnectionBeforeThrowing()
    {
        $exception = $this->createConnectionLoss();
        $connection = $this->createMock(Connection::class);
        $connection->method('get')->willThrowException($exception);
        $connection->expects($this->once())->method('closeIfDisconnected')->willReturn(true);

        $receiver = new DoctrineReceiver($connection, $this->createSerializer());

        try {
            $receiver->get();
            $this->fail('The lost connection must be reported.');
        } catch (TransportException $e) {
            $this->assertSame($exception, $e->getPrevious());
        }
    }

    #[Group('time-sensitive')]
    public function testAckIsReplayedOnceTheDatabaseIsBack()
    {
        ClockMock::withClockMock(1000000000.0);
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->exactly(2))->method('ack')->with('1')->willReturnOnConsecutiveCalls(
            $this->throwException(new TransportException('Connection lost', 0, $this->createConnectionLoss())),
            true,
        );
        // Lost, still down after one second, back after two.
        $connection->expects($this->exactly(3))->method('closeIfDisconnected')->willReturn(true, true, false);

        $receiver = new DoctrineReceiver($connection, $this->createSerializer());
        $receiver->ack(new Envelope(new \stdClass(), [new DoctrineReceivedStamp('1')]));

        $this->assertSame(1000000002.0, microtime(true));
    }

    public function testAckIsReplayedWhenDbalAlreadyReopenedTheLostConnection()
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->exactly(2))->method('ack')->with('1')->willReturnOnConsecutiveCalls(
            $this->throwException(new TransportException('Connection lost', 0, $this->createDbalClosedConnectionLoss())),
            true,
        );
        $connection->expects($this->once())->method('closeIfDisconnected')->willReturn(false);

        $receiver = new DoctrineReceiver($connection, $this->createSerializer());
        $receiver->ack(new Envelope(new \stdClass(), [new DoctrineReceivedStamp('1')]));
    }

    public function testAckIsNotReplayedOnAConnectionErrorWhenTheDatabaseStillAnswers()
    {
        $exception = new TransportException('Access denied', 0, $this->createAccessDenied());
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('ack')->willThrowException($exception);
        $connection->method('closeIfDisconnected')->willReturn(false);

        $receiver = new DoctrineReceiver($connection, $this->createSerializer());

        try {
            $receiver->ack(new Envelope(new \stdClass(), [new DoctrineReceivedStamp('1')]));
            $this->fail('The acknowledgement failure must be surfaced.');
        } catch (TransportException $e) {
            $this->assertSame($exception, $e);
        }
    }

    #[Group('time-sensitive')]
    public function testAckThrowsWhenTheDatabaseDoesNotComeBack()
    {
        ClockMock::withClockMock(1000000000.0);
        $exception = new TransportException('Connection lost', 0, $this->createConnectionLoss());
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('ack')->willThrowException($exception);
        $connection->method('closeIfDisconnected')->willReturn(true);

        $receiver = new DoctrineReceiver($connection, $this->createSerializer());

        try {
            $receiver->ack(new Envelope(new \stdClass(), [new DoctrineReceivedStamp('1')]));
            $this->fail('The acknowledgement failure must be surfaced.');
        } catch (TransportException $e) {
            $this->assertSame($exception, $e);
        }

        $this->assertSame(1000000005.0, microtime(true));
    }

    #[Group('time-sensitive')]
    public function testRejectIsReplayedOnceTheDatabaseIsBack()
    {
        ClockMock::withClockMock(1000000000.0);
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->exactly(2))->method('reject')->with('1')->willReturnOnConsecutiveCalls(
            $this->throwException(new TransportException('Connection lost', 0, $this->createConnectionLoss())),
            true,
        );
        $connection->expects($this->exactly(2))->method('closeIfDisconnected')->willReturn(true, false);

        $receiver = new DoctrineReceiver($connection, $this->createSerializer());
        $receiver->reject(new Envelope(new \stdClass(), [new DoctrineReceivedStamp('1')]));
    }

    private function createConnectionLoss(): ConnectionException
    {
        return new ConnectionException(Exception::new(new \PDOException('SQLSTATE[08006] [7] connection to server failed: Connection refused')), null);
    }

    private function createDbalClosedConnectionLoss(): ConnectionLost
    {
        return new ConnectionLost(Exception::new(new \PDOException('SQLSTATE[HY000]: General error: 7 FATAL:  terminating connection due to administrator command')), null);
    }

    private function createAccessDenied(): ConnectionException
    {
        return new ConnectionException(Exception::new(new \PDOException("SQLSTATE[42000]: Syntax error or access violation: 1142 SELECT command denied to user 'app'@'localhost' for table 'messenger_messages'")), null);
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

    #[RequiresPhpExtension('pcntl')]
    #[RequiresPhpExtension('posix')]
    public function testItDispatchesTheSignalsRaisedWhileFetching()
    {
        $received = false;
        $previousHandler = pcntl_signal_get_handler(\SIGUSR1);
        $previousAsync = pcntl_async_signals(true);
        pcntl_signal(\SIGUSR1, static function () use (&$received) { $received = true; });

        try {
            $connection = $this->createMock(Connection::class);
            $connection->expects($this->once())->method('get')->willReturnCallback(function () use (&$received) {
                posix_kill(posix_getpid(), \SIGUSR1);

                // the transport holds the signal back until it is done talking to the database
                $this->assertFalse($received);

                return null;
            });

            $receiver = new DoctrineReceiver($connection, $this->createSerializer());

            $this->assertSame([], $receiver->get());
            $this->assertTrue($received);
            $this->assertTrue(pcntl_async_signals());
        } finally {
            pcntl_signal(\SIGUSR1, $previousHandler);
            pcntl_async_signals($previousAsync);
        }
    }

    #[RequiresPhpExtension('pcntl')]
    public function testItRestoresSignalDispatchingWhenFetchingThrows()
    {
        $previousAsync = pcntl_async_signals(true);

        try {
            $connection = $this->createStub(Connection::class);
            $connection->method('get')->willThrowException(new DeadlockException(Exception::new(new \PDOException('Deadlock', 40001)), null));

            $receiver = new DoctrineReceiver($connection, $this->createSerializer());

            // the first calls are swallowed as retryable, the last one throws
            $this->assertSame([], $receiver->get());
            $this->assertSame([], $receiver->get());

            try {
                $receiver->get();
                $this->fail('TransportException expected.');
            } catch (TransportException) {
            }

            $this->assertTrue(pcntl_async_signals());
        } finally {
            pcntl_async_signals($previousAsync);
        }
    }

    private function createSerializer(): Serializer
    {
        return new Serializer(
            new SerializerComponent\Serializer([new DateTimeNormalizer(), new ArrayDenormalizer(), new ObjectNormalizer()], ['json' => new JsonEncoder()])
        );
    }

    private function createDeadlockException(): DeadlockException
    {
        $driverException = class_exists(Exception::class) ? Exception::new(new \PDOException('Deadlock', 40001)) : new PDOException(new \PDOException('Deadlock', 40001));

        if (!class_exists(Version::class)) {
            // This is doctrine/dbal 3.x
            return new DeadlockException($driverException, null);
        }

        return new DeadlockException('Deadlock', $driverException);
    }
}
