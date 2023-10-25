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
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\Receiver\QueueBlockingReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\QueueReceiverInterface;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * Symfony Messenger receiver to get messages from AMQP brokers using PHP's AMQP extension.
 *
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
class AmqpReceiver implements QueueReceiverInterface, QueueBlockingReceiverInterface, MessageCountAwareInterface
{
    private SerializerInterface $serializer;
    private Connection $connection;

    public function __construct(Connection $connection, SerializerInterface $serializer = null)
    {
        $this->connection = $connection;
        $this->serializer = $serializer ?? new PhpSerializer();
    }

    public function get(): iterable
    {
        yield from $this->getFromQueues($this->connection->getQueueNames());
    }

    public function pull(callable $callback): void
    {
        $this->pullFromQueues($this->connection->getQueueNames(), $callback);
    }

    public function pullFromQueues(array $queueNames, callable $callback): void
    {
        if (0 === \count($queueNames)) {
            return;
        }

        // Pop last queue to send callback
        $firstQueue = array_pop($queueNames);

        foreach ($queueNames as $queueName) {
            $this->pullEnvelope($queueName, null);
        }

        $this->pullEnvelope($firstQueue, $callback);
    }

    private function pullEnvelope(string $queueName, ?callable $callback): void
    {
        if (null !== $callback) {
            $callback = function (\AMQPEnvelope $amqpEnvelope, \AMQPQueue $queue) use ($callback) {
                $queueName = $queue->getName();
                $body = $amqpEnvelope->getBody();
                $envelope = $this->decodeAmqpEnvelope($amqpEnvelope, $body, $queueName);

                return $callback($envelope->with(new AmqpReceivedStamp($amqpEnvelope, $queueName)));
            };
        }

        try {
            $this->connection->pull($queueName, $callback);
        } catch (\AMQPException $exception) {
            throw new TransportException($exception->getMessage(), 0, $exception);
        }
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
        } catch (\AMQPException $exception) {
            throw new TransportException($exception->getMessage(), 0, $exception);
        }

        if (null === $amqpEnvelope) {
            return;
        }

        $body = $amqpEnvelope->getBody();
        $envelope = $this->decodeAmqpEnvelope($amqpEnvelope, $body, $queueName);

        yield $envelope->with(new AmqpReceivedStamp($amqpEnvelope, $queueName));
    }

    private function decodeAmqpEnvelope(\AMQPEnvelope $amqpEnvelope, false|string $body, string $queueName): Envelope
    {
        try {
            return $this->serializer->decode([
                'body' => false === $body ? '' : $body, // workaround https://github.com/pdezwart/php-amqp/issues/351
                'headers' => $amqpEnvelope->getHeaders(),
            ]);
        } catch (MessageDecodingFailedException $exception) {
            // invalid message of some type
            $this->rejectAmqpEnvelope($amqpEnvelope, $queueName);

            throw $exception;
        }
    }

    public function ack(Envelope $envelope): void
    {
        try {
            $stamp = $this->findAmqpStamp($envelope);

            $this->connection->ack(
                $stamp->getAmqpEnvelope(),
                $stamp->getQueueName()
            );
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
