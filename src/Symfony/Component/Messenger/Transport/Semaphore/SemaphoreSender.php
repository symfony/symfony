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
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Transport\Semaphore\Exception\SemaphoreException;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * Symfony Messenger sender to send messages to Semaphore.
 *
 * @author Cedrick Oka Baidai <okacedrick@gmail.com>
 */
class SemaphoreSender implements SenderInterface
{
    /**
     * @var SerializerInterface
     */
    private $serializer;

    public function __construct(Connection $connection, SerializerInterface $serializer = null)
    {
        $this->connection = $connection;
        $this->serializer = $serializer ?? new PhpSerializer();
    }

    /**
     * {@inheritdoc}
     *
     * @see \Symfony\Component\Messenger\Transport\Sender\SenderInterface::send()
     */
    public function send(Envelope $envelope): Envelope
    {
        $encodedMessage = $this->serializer->encode($envelope);

        /** @var \Symfony\Component\Messenger\Stamp\DelayStamp|null $delayStamp */
        $delayStamp = $envelope->last(DelayStamp::class);
        $delay = null !== $delayStamp ? $delayStamp->getDelay() : 0;

        try {
            $this->connection->send(
                    $encodedMessage['body'],
                    $encodedMessage['headers'] ?? [],
                    $delay,
                    $envelope->last(SemaphoreStamp::class)
            );
        } catch (SemaphoreException $exception) {
            throw new TransportException($exception->getMessage(), 0, $exception);
        }

        return $envelope;
    }
}
