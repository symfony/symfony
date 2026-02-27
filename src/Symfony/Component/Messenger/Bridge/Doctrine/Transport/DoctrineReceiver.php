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
    private int $retryingSafetyCounter = 0;
    private SerializerInterface $serializer;

    public function __construct(
        private Connection $connection,
        ?SerializerInterface $serializer = null,
    ) {
        $this->serializer = $serializer ?? new PhpSerializer();
    }

    public function get(): iterable
    {
        try {
            $doctrineEnvelope = $this->connection->get();
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
            throw new TransportException($exception->getMessage(), 0, $exception);
        }

        if (null === $doctrineEnvelope) {
            return [];
        }

        return [$this->createEnvelopeFromData($doctrineEnvelope)];
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

    private function withRetryableExceptionRetry(callable $callable): void
    {
        $delay = 100;
        $multiplier = 2;
        $jitter = 0.1;
        $retries = 0;

        retry:
        try {
            $callable();
        } catch (RetryableException $exception) {
            if (++$retries <= self::MAX_RETRIES) {
                $delay *= $multiplier;

                $randomness = (int) ($delay * $jitter);
                $delay += random_int(-$randomness, +$randomness);

                usleep($delay * 1000);

                goto retry;
            }

            throw new TransportException($exception->getMessage(), 0, $exception);
        } catch (DBALException $exception) {
            throw new TransportException($exception->getMessage(), 0, $exception);
        }
    }
}
