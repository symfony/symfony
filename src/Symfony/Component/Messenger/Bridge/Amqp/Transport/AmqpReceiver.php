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

    /**
     * @param int $fetchSize
     */
    public function get(/* int $fetchSize = 1 */): iterable
    {
        $fetchSize = \func_num_args() > 0 ? max(1, func_get_arg(0)) : 1;

        yield from $this->getFromQueues($this->connection->getQueueNames(), $fetchSize);
    }

    /**
     * @param int $fetchSize
     */
    public function getFromQueues(array $queueNames/* , int $fetchSize = 1 */): iterable
    {
        $fetchSize = \func_num_args() > 1 ? max(1, func_get_arg(1)) : 1;
        $remaining = $fetchSize;
        $activeQueues = array_values($queueNames);
        $firstRound = true;

        while ($activeQueues && ($remaining > 0 || $firstRound)) {
            $exhausted = [];

            foreach ($activeQueues as $i => $queueName) {
                if (null === $envelope = $this->getEnvelope($queueName)) {
                    $exhausted[] = $i;
                    continue;
                }

                yield $envelope;
                --$remaining;

                if ($remaining <= 0 && !$firstRound) {
                    return;
                }
            }

            $firstRound = false;

            foreach (array_reverse($exhausted) as $i) {
                array_splice($activeQueues, $i, 1);
            }
        }
    }

    private function getEnvelope(string $queueName): ?Envelope
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
            } catch (\AMQPException $e) {
                throw new TransportException($e->getMessage(), 0, $e);
            }
        } catch (\AMQPException $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }

        if (null === $amqpEnvelope) {
            return null;
        }

        $body = $amqpEnvelope->getBody();
        $id = $amqpEnvelope->getMessageId();
        $stamps = [
            new AmqpReceivedStamp($amqpEnvelope, $queueName),
            ...($id ? [new TransportMessageIdStamp($id)] : []),
        ];

        $data = [
            'body' => false === $body ? '' : $body,
            'headers' => $amqpEnvelope->getHeaders(),
        ];

        try {
            return $this->serializer->decode($data)->withoutAll(TransportMessageIdStamp::class)->with(...$stamps);
        } catch (MessageDecodingFailedException $e) {
            return MessageDecodingFailedException::wrap($data, $e->getMessage(), $e->getCode(), $e)->with(...$stamps);
        }
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
            } catch (\AMQPException $e) {
                throw new TransportException($e->getMessage(), 0, $e);
            }
        } catch (\AMQPException $e) {
            throw new TransportException($e->getMessage(), 0, $e);
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
        } catch (\AMQPException $e) {
            throw new TransportException($e->getMessage(), 0, $e);
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
            } catch (\AMQPException $e) {
                throw new TransportException($e->getMessage(), 0, $e);
            }
        } catch (\AMQPException $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }
    }

    private function findAmqpStamp(Envelope $envelope): AmqpReceivedStamp
    {
        return $envelope->last(AmqpReceivedStamp::class) ?? throw new LogicException('No "AmqpReceivedStamp" stamp found on the Envelope.');
    }
}
