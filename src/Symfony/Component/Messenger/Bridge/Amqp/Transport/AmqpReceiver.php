<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\Amqp\Transport;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\LogicException;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\Receiver\QueueReceiverInterface;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * Symfony Messenger receiver to get messages from AMQP brokers using PHP's AMQP extension.
 *
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
class AmqpReceiver implements QueueReceiverInterface, MessageCountAwareInterface
{
    private SerializerInterface $serializer;

    public function __construct(
        private Connection $connection,
        ?SerializerInterface $serializer = null,
    ) {
        $this->serializer = $serializer ?? new PhpSerializer();
    }

    public function get(): iterable
    {
        yield from $this->getFromQueues($this->connection->getQueueNames());
    }

    public function getFromQueues(array $queueNames): iterable
    {
        foreach ($queueNames as $queueName) {
            yield from $this->getEnvelope($queueName);
        }
    }

    private function getEnvelope(string $queueName): iterable
    {
        try {
            $amqpEnvelope = $this->connection->get($queueName);
        } catch (\AMQPConnectionException) {
            // Try to reconnect once to accommodate need for one of the nodes in cluster needing to stop serving the
            // traffic. This may happen for example when one of the nodes in cluster is going into maintenance node.
            // see https://github.com/php-amqplib/php-amqplib/issues/1161
            try {
                $this->connection->queue($queueName)->getConnection()->reconnect();
                $amqpEnvelope = $this->connection->get($queueName);
            } catch (\AMQPException $exception) {
                throw new TransportException($exception->getMessage(), 0, $exception);
            }
        } catch (\AMQPException $exception) {
            throw new TransportException($exception->getMessage(), 0, $exception);
        }

        if (null === $amqpEnvelope) {
            return;
        }

        $body = $amqpEnvelope->getBody();

        try {
            $envelope = $this->serializer->decode([
                'body' => false === $body ? '' : $body, // workaround https://github.com/pdezwart/php-amqp/issues/351
                'headers' => $amqpEnvelope->getHeaders(),
            ]);
        } catch (MessageDecodingFailedException $exception) {
            // invalid message of some type
            $this->rejectAmqpEnvelope($amqpEnvelope, $queueName);

            throw $exception;
        }

        if (null !== $amqpEnvelope->getMessageId()) {
            $envelope = $envelope
                ->withoutAll(TransportMessageIdStamp::class)
                ->with(new TransportMessageIdStamp($amqpEnvelope->getMessageId()));
        }

        yield $envelope->with(new AmqpReceivedStamp($amqpEnvelope, $queueName));
    }

    public function ack(Envelope $envelope): void
    {
        try {
            $stamp = $this->findAmqpStamp($envelope);

            $this->connection->ack($stamp->getAmqpEnvelope(), $stamp->getQueueName());
        } catch (\AMQPConnectionException) {
            try {
                $stamp = $this->findAmqpStamp($envelope);

                $this->connection->queue($stamp->getQueueName())->getConnection()->reconnect();
                $this->connection->ack($stamp->getAmqpEnvelope(), $stamp->getQueueName());
            } catch (\AMQPException $exception) {
                throw new TransportException($exception->getMessage(), 0, $exception);
            }
        } catch (\AMQPException $exception) {
            throw new TransportException($exception->getMessage(), 0, $exception);
        }
    }

    public function reject(Envelope $envelope): void
    {
        $stamp = $this->findAmqpStamp($envelope);

        $this->rejectAmqpEnvelope(
            $stamp->getAmqpEnvelope(),
            $stamp->getQueueName()
        );
    }

    public function getMessageCount(): int
    {
        try {
            return $this->connection->countMessagesInQueues();
        } catch (\AMQPException $exception) {
            throw new TransportException($exception->getMessage(), 0, $exception);
        }
    }

    private function rejectAmqpEnvelope(\AMQPEnvelope $amqpEnvelope, string $queueName): void
    {
        try {
            $this->connection->nack($amqpEnvelope, $queueName, \AMQP_NOPARAM);
        } catch (\AMQPConnectionException) {
            try {
                $this->connection->queue($queueName)->getConnection()->reconnect();
                $this->connection->nack($amqpEnvelope, $queueName, \AMQP_NOPARAM);
            } catch (\AMQPException $exception) {
                throw new TransportException($exception->getMessage(), 0, $exception);
            }
        } catch (\AMQPException $exception) {
            throw new TransportException($exception->getMessage(), 0, $exception);
        }
    }

    private function findAmqpStamp(Envelope $envelope): AmqpReceivedStamp
    {
        $amqpReceivedStamp = $envelope->last(AmqpReceivedStamp::class);
        if (null === $amqpReceivedStamp) {
            throw new LogicException('No "AmqpReceivedStamp" stamp found on the Envelope.');
        }

        return $amqpReceivedStamp;
    }
}
