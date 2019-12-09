<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport\Semaphore;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * Symfony Messenger receiver to get messages from Semaphore.
 *
 * @author Cedrick Oka Baidai <okacedrick@gmail.com>
 */
class SemaphoreReceiver implements ReceiverInterface
{
    private $connection;
    private $serializer;

    public function __construct(Connection $connection, SerializerInterface $serializer = null)
    {
        $this->connection = $connection;
        $this->serializer = $serializer ?? new PhpSerializer();
    }

    /**
     * {@inheritdoc}
     *
     * @see \Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface::get()
     */
    public function get(): iterable
    {
        $semaphoreEnvelope = $this->connection->get();

        if (null === $semaphoreEnvelope) {
            return;
        }

        try {
            $envelope = $this->serializer->decode([
                'body' => $semaphoreEnvelope->getBody(),
                'headers' => $semaphoreEnvelope->getHeaders(),
            ]);
        } catch (MessageDecodingFailedException $exception) {
            // TODO: [Researh] Implements nack strategy for semaphore

            throw $exception;
        }

        yield $envelope->with(new SemaphoreStamp($semaphoreEnvelope->getType()));
    }

    /**
     * {@inheritdoc}
     *
     * @see \Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface::ack()
     */
    public function ack(Envelope $envelope): void
    {
    }

    /**
     * {@inheritdoc}
     *
     * @see \Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface::reject()
     */
    public function reject(Envelope $envelope): void
    {
    }
}
