<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\AmazonSqs\Transport;

use AsyncAws\Core\Exception\Exception as AsyncAwsException;
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
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class AmazonSqsReceiver implements KeepaliveReceiverInterface, ListableReceiverInterface, MessageCountAwareInterface
{
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

        try {
            if (!$sqsEnvelopes = $this->connection->get($fetchSize)) {
                return;
            }
        } catch (AsyncAwsException $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }

        foreach ($sqsEnvelopes as $sqsEnvelope) {
            yield $this->createEnvelope($sqsEnvelope, $sqsEnvelope['id']);
        }
    }

    /**
     * Receives every message of the queue, then makes them visible again.
     *
     * The messages are identified by their SQS message id, as their receipt handle changes each time they are received.
     */
    public function all(?int $limit = null): iterable
    {
        try {
            foreach ($this->connection->findAll($limit) as $sqsEnvelope) {
                yield $this->createEnvelope($sqsEnvelope, $sqsEnvelope['message_id']);
            }
        } catch (AsyncAwsException $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }
    }

    /**
     * Receives the messages of the queue until the one with this SQS message id is found, then makes them visible again.
     */
    public function find(mixed $id): ?Envelope
    {
        try {
            $sqsEnvelope = $this->connection->find((string) $id);
        } catch (AsyncAwsException $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }

        return null === $sqsEnvelope ? null : $this->createEnvelope($sqsEnvelope, $sqsEnvelope['message_id']);
    }

    public function ack(Envelope $envelope): void
    {
        try {
            $this->connection->delete($this->findSqsReceivedStampId($envelope));
        } catch (AsyncAwsException $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }
    }

    public function reject(Envelope $envelope): void
    {
        try {
            $this->connection->reject($this->findSqsReceivedStampId($envelope));
        } catch (AsyncAwsException $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }
    }

    public function keepalive(Envelope $envelope, ?int $seconds = null): void
    {
        try {
            $this->connection->keepalive($this->findSqsReceivedStampId($envelope), $seconds);
        } catch (AsyncAwsException $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }
    }

    public function getMessageCount(): int
    {
        try {
            return $this->connection->getMessageCount();
        } catch (AsyncAwsException $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }
    }

    private function createEnvelope(array $sqsEnvelope, string $transportMessageId): Envelope
    {
        $stamps = [
            new AmazonSqsReceivedStamp($sqsEnvelope['id'], $sqsEnvelope['system_attributes'] ?? []),
            new TransportMessageIdStamp($transportMessageId),
        ];

        try {
            return $this->serializer->decode($sqsEnvelope = [
                'body' => $sqsEnvelope['body'],
                'headers' => $sqsEnvelope['headers'],
            ])->withoutAll(TransportMessageIdStamp::class)->with(...$stamps);
        } catch (MessageDecodingFailedException $e) {
            return MessageDecodingFailedException::wrap($sqsEnvelope, $e->getMessage(), $e->getCode(), $e)->with(...$stamps);
        }
    }

    private function findSqsReceivedStampId(Envelope $envelope): string
    {
        return $envelope->last(AmazonSqsReceivedStamp::class)?->getId() ?? throw new LogicException('No AmazonSqsReceivedStamp found on the Envelope.');
    }
}
