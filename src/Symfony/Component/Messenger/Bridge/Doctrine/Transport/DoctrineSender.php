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
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Sender\BatchSenderInterface;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * @author Vincent Touzet <vincent.touzet@gmail.com>
 */
class DoctrineSender implements BatchSenderInterface
{
    private SerializerInterface $serializer;

    public function __construct(
        private Connection $connection,
        ?SerializerInterface $serializer = null,
    ) {
        $this->serializer = $serializer ?? new PhpSerializer();
    }

    public function send(Envelope $envelope): Envelope
    {
        $encodedMessage = $this->serializer->encode($envelope);

        /** @var DelayStamp|null $delayStamp */
        $delayStamp = $envelope->last(DelayStamp::class);
        $delay = null !== $delayStamp ? $delayStamp->getDelay() : 0;

        try {
            $id = $this->connection->send($encodedMessage['body'], $encodedMessage['headers'] ?? [], $delay);
        } catch (DBALException $exception) {
            throw new TransportException($exception->getMessage(), 0, $exception);
        }

        return $envelope->with(new TransportMessageIdStamp($id));
    }

    public function getMaxBatchSize(): ?int
    {
        return null;
    }

    public function sendBatch(array $envelopes): array
    {
        if ([] === $envelopes) {
            return [];
        }

        $messages = [];
        foreach ($envelopes as $envelope) {
            $encodedMessage = $this->serializer->encode($envelope);

            /** @var DelayStamp|null $delayStamp */
            $delayStamp = $envelope->last(DelayStamp::class);

            $messages[] = [
                'body' => $encodedMessage['body'],
                'headers' => $encodedMessage['headers'] ?? [],
                'delay' => null !== $delayStamp ? $delayStamp->getDelay() : 0,
            ];
        }

        try {
            $ids = $this->connection->sendBatch($messages);
        } catch (DBALException $exception) {
            throw new TransportException($exception->getMessage(), 0, $exception);
        }

        if (null !== $ids) {
            foreach ($envelopes as $index => $envelope) {
                $envelopes[$index] = $envelope->with(new TransportMessageIdStamp($ids[$index]));
            }
        }

        return $envelopes;
    }
}
