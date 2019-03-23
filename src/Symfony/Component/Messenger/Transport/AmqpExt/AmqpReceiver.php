<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport\AmqpExt;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\LogicException;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * Symfony Messenger receiver to get messages from AMQP brokers using PHP's AMQP extension.
 *
 * @author Samuel Roze <samuel.roze@gmail.com>
 *
 * @experimental in 4.2
 */
class AmqpReceiver implements ReceiverInterface
{
    private $serializer;
    private $connection;
    private $shouldStop;

    public function __construct(Connection $connection, SerializerInterface $serializer = null)
    {
        $this->connection = $connection;
        $this->serializer = $serializer ?? new PhpSerializer();
    }

    /**
     * {@inheritdoc}
     */
    public function receive(callable $handler): void
    {
        while (!$this->shouldStop) {
            try {
                $amqpEnvelope = $this->connection->get();
            } catch (\AMQPException $exception) {
                throw new TransportException($exception->getMessage(), 0, $exception);
            }

            if (null === $amqpEnvelope) {
                $handler(null);

                usleep($this->connection->getConnectionConfiguration()['loop_sleep'] ?? 200000);

                continue;
            }

            try {
                $envelope = $this->serializer->decode([
                    'body' => $amqpEnvelope->getBody(),
                    'headers' => $amqpEnvelope->getHeaders(),
                ]);
            } catch (MessageDecodingFailedException $exception) {
                // invalid message of some type
                $this->rejectAmqpEnvelope($amqpEnvelope);

                throw $exception;
            }

            $envelope = $envelope->with(new AmqpReceivedStamp($amqpEnvelope));
            $handler($envelope);
        }
    }

    public function ack(Envelope $envelope): void
    {
        try {
            $this->connection->ack($this->findAmqpEnvelope($envelope));
        } catch (\AMQPException $exception) {
            throw new TransportException($exception->getMessage(), 0, $exception);
        }
    }

    public function reject(Envelope $envelope): void
    {
        $this->rejectAmqpEnvelope($this->findAmqpEnvelope($envelope));
    }

    public function stop(): void
    {
        $this->shouldStop = true;
    }

    private function rejectAmqpEnvelope(\AMQPEnvelope $amqpEnvelope): void
    {
        try {
            $this->connection->nack($amqpEnvelope, AMQP_NOPARAM);
        } catch (\AMQPException $exception) {
            throw new TransportException($exception->getMessage(), 0, $exception);
        }
    }

    private function findAmqpEnvelope(Envelope $envelope): \AMQPEnvelope
    {
        /** @var AmqpReceivedStamp|null $amqpReceivedStamp */
        $amqpReceivedStamp = $envelope->last(AmqpReceivedStamp::class);

        if (null === $amqpReceivedStamp) {
            throw new LogicException('No AmqpReceivedStamp found on the Envelope.');
        }

        return $amqpReceivedStamp->getAmqpEnvelope();
    }
}
