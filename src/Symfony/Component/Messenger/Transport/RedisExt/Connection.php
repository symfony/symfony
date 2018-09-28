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

/**
 * @author Antoine Bluchet <soyuka@gmail.com>
 */
class Connection
{
    const PROCESSING_QUEUE_SUFFIX = '_processing';
    const DEFAULT_CONNECTION_CREDENTIALS = array('host' => '127.0.0.1', 'port' => 6379);
    const DEFAULT_REDIS_OPTIONS = array('serializer' => \Redis::SERIALIZER_PHP, 'processing_ttl' => 10000, 'blocking_timeout' => 1000);

    /**
     * @var \Redis
     */
    private $connection;

    /**
     * @var string
     */
    private $queue;

    public function __construct(string $queue, array $connectionCredentials = self::DEFAULT_CONNECTION_CREDENTIALS, array $redisOptions = self::DEFAULT_REDIS_OPTIONS)
    {
        $this->connection = new \Redis();
        $this->connection->connect($connectionCredentials['host'] ?? '127.0.0.1', $connectionCredentials['port'] ?? 6379);
        $this->connection->setOption(\Redis::OPT_SERIALIZER, $redisOptions['serializer'] ?? \Redis::SERIALIZER_PHP);
        // We force this because we rely on the fact that redis doesn't timeout with bRPopLPush
        $this->connection->setOption(\Redis::OPT_READ_TIMEOUT, -1);
        $this->queue = $queue;
        $this->processingTtl = $redisOptions['processing_ttl'] ?? self::DEFAULT_REDIS_OPTIONS['processing_ttl'];
        $this->blockingTimeout = $redisOptions['blocking_timeout'] ?? self::DEFAULT_REDIS_OPTIONS['blocking_timeout'];
    }

    public static function fromDsn(string $dsn, array $redisOptions = self::DEFAULT_REDIS_OPTIONS): self
    {
        if (false === $parsedUrl = parse_url($dsn)) {
            throw new InvalidArgumentException(sprintf('The given Redis DSN "%s" is invalid.', $dsn));
        }

        $queue = isset($parsedUrl['path']) ? trim($parsedUrl['path'], '/') : $redisOptions['queue'] ?? 'messages';
        $connectionCredentials = array(
            'host' => $parsedUrl['host'] ?? '127.0.0.1',
            'port' => $parsedUrl['port'] ?? 6379,
        );

        if (isset($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $parsedQuery);
            $redisOptions = array_replace_recursive($redisOptions, $parsedQuery);
        }

        return new self($queue, $connectionCredentials, $redisOptions);
    }

    /**
     * Takes last element (tail) of the list and add it to the processing queue (head - blocking)
     * Also sets a key with TTL that will be checked by the `doCheck` method.
     */
    public function waitAndGet(): ?array
    {
        $this->doCheck();
        $value = $this->connection->bRPopLPush($this->queue, $this->queue.self::PROCESSING_QUEUE_SUFFIX, $this->blockingTimeout);

        // false in case of timeout
        if (false === $value) {
            return null;
        }

        $key = md5($value['body']);
        $this->connection->set($key, 1, array('px' => $this->processingTtl));

        return $value;
    }

    /**
     * Acknowledge the message:
     * 1. Remove the ttl key
     * 2. LREM the message from the processing list.
     */
    public function ack($message)
    {
        $key = md5($message['body']);
        $processingQueue = $this->queue.self::PROCESSING_QUEUE_SUFFIX;
        $this->connection->multi()
            ->lRem($processingQueue, $message)
            ->del($key)
            ->exec();
    }

    /**
     * Reject the message: we acknowledge it, means we remove it form the queues.
     *
     * @TODO: log something?
     */
    public function reject($message)
    {
        $this->ack($message);
    }

    /**
     * Requeue - add it back to the queue
     * All we have to do is to make our key expire and let the `doCheck` system manage it.
     */
    public function requeue($message)
    {
        $key = md5($message['body']);
        $this->connection->expire($key, -1);
    }

    /**
     * Add item at the tail of list.
     */
    public function add($message)
    {
        $this->connection->lpush($this->queue, $message);
    }

    /**
     * The check:
     * 1. Get the processing queue items
     * 2. Check if the TTL is over
     * 3. If it is, rpush back the message to the origin queue.
     */
    private function doCheck()
    {
        $processingQueue = $this->queue.self::PROCESSING_QUEUE_SUFFIX;
        $pending = $this->connection->lRange($processingQueue, 0, -1);

        foreach ($pending as $temp) {
            $key = md5($temp['body']);

            if ($this->connection->ttl($key) > 0) {
                continue;
            }

            $this->connection
                ->multi()
                ->del($key)
                ->lRem($processingQueue, $temp, 1)
                ->rPush($this->queue, $temp)
                ->exec();
        }
    }
}
