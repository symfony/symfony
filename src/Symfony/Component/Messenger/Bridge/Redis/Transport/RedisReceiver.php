<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\Redis\Transport;

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
 * @author Alexander Schranz <alexander@sulu.io>
 * @author Antoine Bluchet <soyuka@gmail.com>
 */
class RedisReceiver implements KeepaliveReceiverInterface, MessageCountAwareInterface, ListableReceiverInterface
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
        if (null === $message = $this->connection->get()) {
            return [];
        }

        if (null === $message['data']) {
            try {
                $this->connection->reject($message['id']);
            } catch (TransportException $e) {
                if ($e->getPrevious()) {
                    throw $e;
                }
            }

            return $this->get();
        }

        if (null === $redisEnvelope = json_decode($message['data']['message'] ?? '', true)) {
            return [];
        }

        try {
            if (\array_key_exists('body', $redisEnvelope) && \array_key_exists('headers', $redisEnvelope)) {
                $envelope = $this->serializer->decode([
                    'body' => $redisEnvelope['body'],
                    'headers' => $redisEnvelope['headers'],
                ]);
            } else {
                $envelope = $this->serializer->decode($redisEnvelope);
            }
        } catch (MessageDecodingFailedException $exception) {
            $this->connection->reject($message['id']);

            throw $exception;
        }

        return [$envelope
            ->withoutAll(TransportMessageIdStamp::class)
            ->with(
                new RedisReceivedStamp($message['id']),
                new TransportMessageIdStamp($message['id'])
            )];
    }

    public function ack(Envelope $envelope): void
    {
        $this->connection->ack($this->findRedisReceivedStampId($envelope));
    }

    public function reject(Envelope $envelope): void
    {
        $this->connection->reject($this->findRedisReceivedStampId($envelope));
    }

    public function keepalive(Envelope $envelope, ?int $seconds = null): void
    {
        $this->connection->keepalive($this->findRedisReceivedStampId($envelope), $seconds);
    }

    public function getMessageCount(): int
    {
        return $this->connection->getMessageCount();
    }

    public function all(?int $limit = null): iterable
    {
        $messages = $this->connection->findAll($limit);

        foreach ($messages as $message) {
            if (null !== $envelope = $this->createEnvelopeFromData($message['id'], $message['data']['message'] ?? null)) {
                yield $envelope;
            }
        }
    }

    public function find(mixed $id): ?Envelope
    {
        if (null === $message = $this->connection->find($id)) {
            return null;
        }

        return $this->createEnvelopeFromData($message['id'], $message['data']['message'] ?? null);
    }

    private function createEnvelopeFromData(string $id, ?string $json): ?Envelope
    {
        if (null === $json) {
            return null;
        }

        if (null === $redisEnvelope = json_decode($json, true)) {
            return null;
        }

        try {
            if (\array_key_exists('body', $redisEnvelope) && \array_key_exists('headers', $redisEnvelope)) {
                $envelope = $this->serializer->decode([
                    'body' => $redisEnvelope['body'],
                    'headers' => $redisEnvelope['headers'],
                ]);
            } else {
                $envelope = $this->serializer->decode($redisEnvelope);
            }
        } catch (MessageDecodingFailedException) {
            return null;
        }

        return $envelope
            ->withoutAll(TransportMessageIdStamp::class)
            ->with(
                new RedisReceivedStamp($id),
                new TransportMessageIdStamp($id)
            );
    }

    private function findRedisReceivedStampId(Envelope $envelope): string
    {
        return $envelope->last(RedisReceivedStamp::class)?->getId() ?? throw new LogicException('No RedisReceivedStamp found on the Envelope.');
    }
}
