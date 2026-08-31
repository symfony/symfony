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

    /**
     * @param int $fetchSize
     */
    public function get(/* int $fetchSize = 1 */): iterable
    {
        $fetchSize = \func_num_args() > 0 ? max(1, func_get_arg(0)) : 1;

        retry:
        if (null === $messages = $this->connection->get($fetchSize)) {
            return [];
        }

        $envelopes = [];
        $shouldRetry = false;

        foreach ($messages as $message) {
            if (null === $message['data']) {
                $shouldRetry = true;

                try {
                    $this->connection->reject($message['id']);
                } catch (TransportException $e) {
                    if ($e->getPrevious()) {
                        throw $e;
                    }
                }

                continue;
            }

            $stamps = [
                new RedisReceivedStamp($message['id']),
                new TransportMessageIdStamp($message['id']),
            ];

            try {
                if (null === $redisEnvelope = $this->decodeRedisEnvelope($message['data'])) {
                    continue;
                }

                if (\array_key_exists('body', $redisEnvelope) && \array_key_exists('headers', $redisEnvelope)) {
                    $envelope = $this->serializer->decode($redisEnvelope = [
                        'body' => $redisEnvelope['body'],
                        'headers' => $redisEnvelope['headers'],
                    ]);
                } else {
                    $envelope = $this->serializer->decode($redisEnvelope);
                }
            } catch (\JsonException $e) {
                $envelopes[] = MessageDecodingFailedException::wrap($message['data'], 'Could not decode the Redis stream entry: '.$e->getMessage(), $e->getCode(), $e)->with(...$stamps);

                continue;
            } catch (MessageDecodingFailedException $e) {
                $envelopes[] = MessageDecodingFailedException::wrap($redisEnvelope, $e->getMessage(), $e->getCode(), $e)->with(...$stamps);

                continue;
            }

            $envelopes[] = $envelope->withoutAll(TransportMessageIdStamp::class)->with(...$stamps);
        }

        if (!$envelopes && $shouldRetry) {
            goto retry;
        }

        return $envelopes;
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
            if (null !== $envelope = $this->createEnvelopeFromData($message['id'], $message['data'] ?? null)) {
                yield $envelope;
            }
        }
    }

    public function find(mixed $id): ?Envelope
    {
        if (null === $message = $this->connection->find($id)) {
            return null;
        }

        return $this->createEnvelopeFromData($message['id'], $message['data'] ?? null);
    }

    private function createEnvelopeFromData(string $id, ?array $data): ?Envelope
    {
        if (null === $data) {
            return null;
        }

        try {
            if (null === $redisEnvelope = $this->decodeRedisEnvelope($data)) {
                return null;
            }

            if (\array_key_exists('body', $redisEnvelope) && \array_key_exists('headers', $redisEnvelope)) {
                $envelope = $this->serializer->decode([
                    'body' => $redisEnvelope['body'],
                    'headers' => $redisEnvelope['headers'],
                ]);
            } else {
                $envelope = $this->serializer->decode($redisEnvelope);
            }
        } catch (\JsonException|MessageDecodingFailedException) {
            return null;
        }

        return $envelope
            ->withoutAll(TransportMessageIdStamp::class)
            ->with(
                new RedisReceivedStamp($id),
                new TransportMessageIdStamp($id)
            );
    }

    private function decodeRedisEnvelope(array $data): ?array
    {
        if (isset($data['message'])) {
            $redisEnvelope = json_decode($data['message'], true, flags: \JSON_THROW_ON_ERROR | \JSON_BIGINT_AS_STRING);

            return \is_array($redisEnvelope) ? $redisEnvelope : null;
        }

        if (!isset($data['body'])) {
            return null;
        }

        $headers = isset($data['headers']) ? json_decode($data['headers'], true, flags: \JSON_THROW_ON_ERROR | \JSON_BIGINT_AS_STRING) : [];

        return [
            'body' => $data['body'],
            'headers' => \is_array($headers) ? $headers : [],
        ];
    }

    private function findRedisReceivedStampId(Envelope $envelope): string
    {
        return $envelope->last(RedisReceivedStamp::class)?->getId() ?? throw new LogicException('No RedisReceivedStamp found on the Envelope.');
    }
}
