<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\Doctrine\Transport;

use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\Exception\ConnectionLost;
use Doctrine\DBAL\Exception\RetryableException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\LogicException;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Receiver\KeepaliveReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * @author Vincent Touzet <vincent.touzet@gmail.com>
 */
class DoctrineReceiver implements ListableReceiverInterface, MessageCountAwareInterface, KeepaliveReceiverInterface
{
    private const MAX_RETRIES = 3;
    // How long, in seconds, ack() and reject() wait for a lost connection to come back: less than the 10 seconds supervisor gives a worker to stop by default
    private const RECONNECT_TIMEOUT = 5;
    private int $retryingSafetyCounter = 0;
    private SerializerInterface $serializer;

    public function __construct(
        private Connection $connection,
        ?SerializerInterface $serializer = null,
    ) {
        $this->serializer = $serializer ?? new PhpSerializer();
    }

    /**
     * @param int $fetchSize
     */
    public function get(/* int $fetchSize = 1 */): iterable
    {
        $fetchSize = \func_num_args() > 0 ? max(1, func_get_arg(0)) : 1;

        // Signals that reach the process while the driver waits on the database are dropped by
        // the engine when that wait ends by throwing, which is what a lock timeout does. Holding
        // them here and dispatching them once the call is over keeps the keepalive alarm armed,
        // since it is rescheduled by its own handler, and keeps workers stoppable.
        $asyncSignals = \function_exists('pcntl_async_signals') && pcntl_async_signals(false);

        try {
            $doctrineEnvelopes = $this->connection->get($fetchSize);
            $this->retryingSafetyCounter = 0; // reset counter
        } catch (RetryableException $exception) {
            // Do nothing when RetryableException occurs less than "MAX_RETRIES"
            // as it will likely be resolved on the next call to get()
            // Problem with concurrent consumers and database deadlocks
            if (++$this->retryingSafetyCounter >= self::MAX_RETRIES) {
                $this->retryingSafetyCounter = 0; // reset counter
                throw new TransportException($exception->getMessage(), 0, $exception);
            }

            return [];
        } catch (DBALException $exception) {
            // Closing a lost connection lets the next call open a fresh one, instead of failing on the dead one
            $this->connection->closeIfDisconnected();

            throw new TransportException($exception->getMessage(), 0, $exception);
        } finally {
            if ($asyncSignals) {
                pcntl_async_signals(true);
                pcntl_signal_dispatch();
            }
        }

        if (null === $doctrineEnvelopes) {
            return [];
        }

        return array_map($this->createEnvelopeFromData(...), $doctrineEnvelopes);
    }

    public function ack(Envelope $envelope): void
    {
        $this->withRetryableExceptionRetry(function () use ($envelope) {
            $this->connection->ack($this->findDoctrineReceivedStampId($envelope));
        });
    }

    public function keepalive(Envelope $envelope, ?int $seconds = null): void
    {
        $this->connection->keepalive($this->findDoctrineReceivedStampId($envelope), $seconds);
    }

    public function reject(Envelope $envelope): void
    {
        $this->withRetryableExceptionRetry(function () use ($envelope) {
            $this->connection->reject($this->findDoctrineReceivedStampId($envelope));
        });
    }

    public function getMessageCount(): int
    {
        try {
            return $this->connection->getMessageCount();
        } catch (DBALException $exception) {
            throw new TransportException($exception->getMessage(), 0, $exception);
        }
    }

    public function all(?int $limit = null): iterable
    {
        try {
            $doctrineEnvelopes = $this->connection->findAll($limit);
        } catch (DBALException $exception) {
            throw new TransportException($exception->getMessage(), 0, $exception);
        }

        foreach ($doctrineEnvelopes as $doctrineEnvelope) {
            yield $this->createEnvelopeFromData($doctrineEnvelope);
        }
    }

    public function find(mixed $id): ?Envelope
    {
        try {
            $doctrineEnvelope = $this->connection->find($id);
        } catch (DBALException $exception) {
            throw new TransportException($exception->getMessage(), 0, $exception);
        }

        if (null === $doctrineEnvelope) {
            return null;
        }

        return $this->createEnvelopeFromData($doctrineEnvelope);
    }

    private function findDoctrineReceivedStampId(Envelope $envelope): string
    {
        return $envelope->last(DoctrineReceivedStamp::class)?->getId() ?? throw new LogicException('No DoctrineReceivedStamp found on the Envelope.');
    }

    private function createEnvelopeFromData(array $data): Envelope
    {
        $stamps = [
            new DoctrineReceivedStamp($data['id']),
            new TransportMessageIdStamp($data['id']),
        ];

        try {
            return $this->serializer->decode($data = [
                'body' => $data['body'],
                'headers' => $data['headers'],
            ])->withoutAll(TransportMessageIdStamp::class)->with(...$stamps);
        } catch (MessageDecodingFailedException $e) {
            return MessageDecodingFailedException::wrap($data, $e->getMessage(), $e->getCode(), $e)->with(...$stamps);
        }
    }

    /**
     * @param-immediately-invoked-callable $callable
     */
    private function withRetryableExceptionRetry(callable $callable): void
    {
        $delay = 100;
        $multiplier = 2;
        $jitter = 0.1;
        $retries = 0;
        $reconnected = false;

        retry:
        try {
            $callable();
        } catch (DBALException|TransportException $exception) {
            // Connection::ack() and reject() wrap DBAL exceptions in a TransportException
            $dbalException = $exception instanceof TransportException ? $exception->getPrevious() : $exception;

            if ($dbalException instanceof RetryableException && ++$retries <= self::MAX_RETRIES) {
                $delay *= $multiplier;

                $randomness = (int) ($delay * $jitter);
                $delay += random_int(-$randomness, +$randomness);

                usleep($delay * 1000);

                goto retry;
            }

            // Losing an acknowledgement or a rejection to a database restart would get the message redelivered and handled again
            if (!$dbalException instanceof RetryableException && !$reconnected && $dbalException instanceof DBALException && $this->waitForReconnection($dbalException)) {
                $reconnected = true;

                goto retry;
            }

            throw $exception instanceof TransportException ? $exception : new TransportException($exception->getMessage(), 0, $exception);
        }
    }

    /**
     * Waits for the database to come back, up to the timeout, and tells whether the failed call can be replayed.
     */
    private function waitForReconnection(DBALException $exception): bool
    {
        $deadline = microtime(true) + self::RECONNECT_TIMEOUT;

        for ($attempts = 0; $this->connection->closeIfDisconnected(); ++$attempts) {
            if (microtime(true) >= $deadline) {
                return false;
            }

            sleep(1);
        }

        // The database answered at once: replay the call only if DBAL already closed the lost connection
        return 0 < $attempts || $exception instanceof ConnectionLost;
    }
}
