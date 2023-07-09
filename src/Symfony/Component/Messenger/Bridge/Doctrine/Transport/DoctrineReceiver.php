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
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * @author Vincent Touzet <vincent.touzet@gmail.com>
 * @author Herberto Graca <herberto.graca@gmail.com>
 */
class DoctrineReceiver implements ListableReceiverInterface, MessageCountAwareInterface
{
    private const MAX_RETRIES = 3;
    private int $retryingSafetyCounter = 0;
    private Connection $connection;
    private SerializerInterface $serializer;

    public function __construct(Connection $connection, SerializerInterface $serializer = null)
    {
        $this->connection = $connection;
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
        try {
            $this->connection->ack($this->findMessageIdStamp($envelope)->getId());
        } catch (DBALException $exception) {
            throw new TransportException($exception->getMessage(), 0, $exception);
        }
    }

    public function reject(Envelope $envelope): void
    {
        try {
            $this->connection->reject($this->findMessageIdStamp($envelope)->getId());
        } catch (DBALException $exception) {
            throw new TransportException($exception->getMessage(), 0, $exception);
        }
    }

    public function getMessageCount(): int
    {
        try {
            return $this->connection->getMessageCount();
        } catch (DBALException $exception) {
            throw new TransportException($exception->getMessage(), 0, $exception);
        }
    }

    public function all(int $limit = null): iterable
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

    private function findMessageIdStamp(Envelope $envelope): TransportMessageIdStamp
    {
        /** @var TransportMessageIdStamp|null $transportMessageIdStamp */
        $transportMessageIdStamp = $envelope->last(TransportMessageIdStamp::class);

        if (null === $transportMessageIdStamp) {
            throw new LogicException('No TransportMessageIdStamp found on the Envelope.');
        }

        return $transportMessageIdStamp;
    }

    private function createEnvelopeFromData(array $data): Envelope
    {
        try {
            $envelope = $this->serializer->decode([
                'body' => $data['body'],
                'headers' => $data['headers'],
            ]);
        } catch (MessageDecodingFailedException $exception) {
            $this->connection->reject($data['id']);

            throw $exception;
        }

        return $envelope->with(
            new TransportMessageIdStamp($data['id'])
        );
    }
}
