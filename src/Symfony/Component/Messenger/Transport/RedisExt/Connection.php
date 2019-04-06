<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport\RedisExt;

use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Exception\LogicException;

/**
 * A Redis connection.
 *
 * @author Alexander Schranz <alexander@sulu.io>
 * @author Antoine Bluchet <soyuka@gmail.com>
 *
 * @internal
 * @final
 *
 * @experimental in 4.3
 */
class Connection
{
    private $connection;
    private $stream;
    private $group;
    private $consumer;
    private $blockingTimeout;
    private $couldHavePendingMessages = true;

    public function __construct(array $configuration, array $connectionCredentials = [], array $redisOptions = [], \Redis $redis = null)
    {
        $this->connection = $redis ?: new \Redis();
        $this->connection->connect($connectionCredentials['host'] ?? '127.0.0.1', $connectionCredentials['port'] ?? 6379);
        $this->connection->setOption(\Redis::OPT_SERIALIZER, $redisOptions['serializer'] ?? \Redis::SERIALIZER_PHP);
        $this->stream = $configuration['stream'] ?? '' ?: 'messages';
        $this->group = $configuration['group'] ?? '' ?: 'symfony';
        $this->consumer = $configuration['consumer'] ?? '' ?: 'consumer';
        $this->blockingTimeout = $redisOptions['blocking_timeout'] ?? null;
    }

    public static function fromDsn(string $dsn, array $redisOptions = [], \Redis $redis = null): self
    {
        if (false === $parsedUrl = parse_url($dsn)) {
            throw new InvalidArgumentException(sprintf('The given Redis DSN "%s" is invalid.', $dsn));
        }

        $pathParts = explode('/', $parsedUrl['path'] ?? '');

        $stream = $pathParts[1] ?? '';
        $group = $pathParts[2] ?? '';
        $consumer = $pathParts[3] ?? '';

        $connectionCredentials = [
            'host' => $parsedUrl['host'] ?? '127.0.0.1',
            'port' => $parsedUrl['port'] ?? 6379,
        ];

        if (isset($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $redisOptions);
        }

        return new self(['stream' => $stream, 'group' => $group, 'consumer' => $consumer], $connectionCredentials, $redisOptions, $redis);
    }

    public function get(): ?array
    {
        $messageId = '>'; // will receive new messages

        if ($this->couldHavePendingMessages) {
            $messageId = '0'; // will receive consumers pending messages
        }

        $messages = $this->connection->xreadgroup(
            $this->group,
            $this->consumer,
            [$this->stream => $messageId],
            1,
            $this->blockingTimeout
        );

        if (false === $messages) {
            throw new LogicException(
                $this->connection->getLastError() ?: 'Unexpected redis stream error happened.'
            );
        }

        if ($this->couldHavePendingMessages && empty($messages[$this->stream])) {
            $this->couldHavePendingMessages = false;

            // No pending messages so get a new one
            return $this->get();
        }

        foreach ($messages[$this->stream] as $key => $message) {
            $redisEnvelope = \json_decode($message['message'], true);

            return [
                'id' => $key,
                'body' => $redisEnvelope['body'],
                'headers' => $redisEnvelope['headers'],
            ];
        }

        return null;
    }

    public function ack(string $id): void
    {
        $this->connection->xack($this->stream, $this->group, [$id]);
    }

    public function reject(string $id): void
    {
        $this->connection->xdel($this->stream, [$id]);
    }

    public function add(string $body, array $headers)
    {
        $this->connection->xadd($this->stream, '*', ['message' => json_encode(
            ['body' => $body, 'headers' => $headers]
        )]);
    }

    public function setup(): void
    {
        $this->connection->xgroup('CREATE', $this->stream, $this->group, 0, true);
    }
}
