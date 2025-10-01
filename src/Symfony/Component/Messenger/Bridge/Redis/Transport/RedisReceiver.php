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
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * @author Alexander Schranz <alexander@sulu.io>
 * @author Antoine Bluchet <soyuka@gmail.com>
 */
class RedisReceiver implements KeepaliveReceiverInterface, MessageCountAwareInterface
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
        $message = $this->connection->get();

        if (null === $message) {
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

        $redisEnvelope = json_decode($message['data']['message'] ?? '', true);

        if (null === $redisEnvelope) {
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
        $this->connection->ack($this->findRedisReceivedStamp($envelope)->getId());
    }

    public function reject(Envelope $envelope): void
    {
        $this->connection->reject($this->findRedisReceivedStamp($envelope)->getId());
    }

    public function keepalive(Envelope $envelope, ?int $seconds = null): void
    {
        $this->connection->keepalive($this->findRedisReceivedStamp($envelope)->getId(), $seconds);
    }

    public function getMessageCount(): int
    {
        return $this->connection->getMessageCount();
    }

    private function findRedisReceivedStamp(Envelope $envelope): RedisReceivedStamp
    {
        /** @var RedisReceivedStamp|null $redisReceivedStamp */
        $redisReceivedStamp = $envelope->last(RedisReceivedStamp::class);

        if (null === $redisReceivedStamp) {
            throw new LogicException('No RedisReceivedStamp found on the Envelope.');
        }

        return $redisReceivedStamp;
    }
}
