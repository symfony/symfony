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

use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Exception\LogicException;
use Symfony\Component\Messenger\Exception\TransportException;

/**
 * A Redis connection.
 *
 * @author Alexander Schranz <alexander@sulu.io>
 * @author Antoine Bluchet <soyuka@gmail.com>
 * @author Robin Chalas <robin.chalas@gmail.com>
 *
 * @internal
 * @final
 */
class Connection
{
    private const DEFAULT_OPTIONS = [
        'host' => '127.0.0.1',
        'port' => 6379,
        'stream' => 'messages',
        'group' => 'symfony',
        'consumer' => 'consumer',
        'auto_setup' => true,
        'delete_after_ack' => true,
        'delete_after_reject' => true,
        'stream_max_entries' => 0, // any value higher than 0 defines an approximate maximum number of stream entries
        'dbindex' => 0,
        'redeliver_timeout' => 3600, // Timeout before redeliver messages still in pending state (seconds)
        'claim_interval' => 60000, // Interval by which pending/abandoned messages should be checked
        'lazy' => false,
        'auth' => null,
        'serializer' => \Redis::SERIALIZER_PHP,
        'sentinel_master' => null, // String, master to look for (optional, default is NULL meaning Sentinel support is disabled)
        'timeout' => 0.0, // Float, value in seconds (optional, default is 0 meaning unlimited)
        'read_timeout' => 0.0, //  Float, value in seconds (optional, default is 0 meaning unlimited)
        'retry_interval' => 0, //  Int, value in milliseconds (optional, default is 0)
        'persistent_id' => null, // String, persistent connection id (optional, default is NULL meaning not persistent)
        'ssl' => null, // see https://php.net/context.ssl
    ];

    private \Redis|\RedisCluster|RedisProxy|RedisClusterProxy $connection;
    private string $stream;
    private string $queue;
    private string $group;
    private string $consumer;
    private bool $autoSetup;
    private int $maxEntries;
    private int $redeliverTimeout;
    private float $nextClaim = 0.0;
    private float $claimInterval;
    private bool $deleteAfterAck;
    private bool $deleteAfterReject;
    private bool $couldHavePendingMessages = true;

    public function __construct(array $options, \Redis|\RedisCluster $redis = null)
    {
        if (version_compare(phpversion('redis'), '4.3.0', '<')) {
            throw new LogicException('The redis transport requires php-redis 4.3.0 or higher.');
        }

        $options += self::DEFAULT_OPTIONS;
        $host = $options['host'];
        $port = $options['port'];
        $auth = $options['auth'];
        $sentinelMaster = $options['sentinel_master'];

        if (null !== $sentinelMaster && !class_exists(\RedisSentinel::class)) {
            throw new InvalidArgumentException('Redis Sentinel support requires the "redis" extension v5.2 or higher.');
        }

        if (null !== $sentinelMaster && ($redis instanceof \RedisCluster || \is_array($host))) {
            throw new InvalidArgumentException('Cannot configure Redis Sentinel and Redis Cluster instance at the same time.');
        }

        if (\is_array($host) || $redis instanceof \RedisCluster) {
            $hosts = \is_string($host) ? [$host.':'.$port] : $host; // Always ensure we have an array
            $initializer = static function ($redis) use ($hosts, $auth, $options) {
                return self::initializeRedisCluster($redis, $hosts, $auth, $options);
            };
            $redis = $options['lazy'] ? new RedisClusterProxy($redis, $initializer) : $initializer($redis);
        } else {
            if (null !== $sentinelMaster) {
                $sentinelClient = new \RedisSentinel($host, $port, $options['timeout'], $options['persistent_id'], $options['retry_interval'], $options['read_timeout']);

                if (!$address = $sentinelClient->getMasterAddrByName($sentinelMaster)) {
                    throw new InvalidArgumentException(sprintf('Failed to retrieve master information from master name "%s" and address "%s:%d".', $sentinelMaster, $host, $port));
                }

                [$host, $port] = $address;
            }

            $redis = $redis ?? new \Redis();
            $initializer = static function ($redis) use ($host, $port, $auth, $options) {
                return self::initializeRedis($redis, $host, $port, $auth, $options);
            };
            $redis = $options['lazy'] ? new RedisProxy($redis, $initializer) : $initializer($redis);
        }

        $this->connection = $redis;

        foreach (['stream', 'group', 'consumer'] as $key) {
            if ('' === $options[$key]) {
                throw new InvalidArgumentException(sprintf('"%s" should be configured, got an empty string.', $key));
            }
        }

        $this->stream = $options['stream'];
        $this->group = $options['group'];
        $this->consumer = $options['consumer'];
        $this->queue = $this->stream.'__queue';
        $this->autoSetup = $options['auto_setup'];
        $this->maxEntries = $options['stream_max_entries'];
        $this->deleteAfterAck = $options['delete_after_ack'];
        $this->deleteAfterReject = $options['delete_after_reject'];
        $this->redeliverTimeout = $options['redeliver_timeout'] * 1000;
        $this->claimInterval = $options['claim_interval'] / 1000;
    }

    /**
     * @param string|string[]|null $auth
     */
    private static function initializeRedis(\Redis $redis, string $host, int $port, string|array|null $auth, array $params): \Redis
    {
        $connect = isset($params['persistent_id']) ? 'pconnect' : 'connect';
        $redis->{$connect}($host, $port, $params['timeout'], $params['persistent_id'], $params['retry_interval'], $params['read_timeout'], ...\defined('Redis::SCAN_PREFIX') ? [['stream' => $params['ssl'] ?? null]] : []);

        $redis->setOption(\Redis::OPT_SERIALIZER, $params['serializer']);

        if (null !== $auth && !$redis->auth($auth)) {
            throw new InvalidArgumentException('Redis connection failed: '.$redis->getLastError());
        }

        if (($params['dbindex'] ?? false) && !$redis->select($params['dbindex'])) {
            throw new InvalidArgumentException('Redis connection failed: '.$redis->getLastError());
        }

        return $redis;
    }

    /**
     * @param string|string[]|null $auth
     */
    private static function initializeRedisCluster(?\RedisCluster $redis, array $hosts, string|array|null $auth, array $params): \RedisCluster
    {
        $redis ??= new \RedisCluster(null, $hosts, $params['timeout'], $params['read_timeout'], (bool) $params['persistent'], $auth, ...\defined('Redis::SCAN_PREFIX') ? [$params['ssl'] ?? null] : []);
        $redis->setOption(\Redis::OPT_SERIALIZER, $params['serializer']);

        return $redis;
    }

    public static function fromDsn(string $dsn, array $options = [], \Redis|\RedisCluster $redis = null): self
    {
        if (!str_contains($dsn, ',')) {
            $parsedUrl = self::parseDsn($dsn, $options);

            if (isset($parsedUrl['host']) && 'rediss' === $parsedUrl['scheme']) {
                $parsedUrl['host'] = 'tls://'.$parsedUrl['host'];
            }
        } else {
            $dsns = explode(',', $dsn);
            $parsedUrls = array_map(function ($dsn) use (&$options) {
                return self::parseDsn($dsn, $options);
            }, $dsns);

            // Merge all the URLs, the last one overrides the previous ones
            $parsedUrl = array_merge(...$parsedUrls);
            $tls = 'rediss' === $parsedUrl['scheme'];

            // Regroup all the hosts in an array interpretable by RedisCluster
            $parsedUrl['host'] = array_map(function ($parsedUrl, $dsn) use ($tls) {
                if (!isset($parsedUrl['host'])) {
                    throw new InvalidArgumentException(sprintf('Missing host in DSN part "%s", it must be defined when using Redis Cluster.', $dsn));
                }
                if ($tls) {
                    $parsedUrl['host'] = 'tls://'.$parsedUrl['host'];
                }

                return $parsedUrl['host'].':'.($parsedUrl['port'] ?? 6379);
            }, $parsedUrls, $dsns);
        }

        if ($invalidOptions = array_diff(array_keys($options), array_keys(self::DEFAULT_OPTIONS), ['host', 'port'])) {
            throw new LogicException(sprintf('Invalid option(s) "%s" passed to the Redis Messenger transport.', implode('", "', $invalidOptions)));
        }
        foreach (self::DEFAULT_OPTIONS as $k => $v) {
            $options[$k] = match (\gettype($v)) {
                'integer' => filter_var($options[$k] ?? $v, \FILTER_VALIDATE_INT),
                'boolean' => filter_var($options[$k] ?? $v, \FILTER_VALIDATE_BOOLEAN),
                'double' => filter_var($options[$k] ?? $v, \FILTER_VALIDATE_FLOAT),
                default => $options[$k] ?? $v,
            };
        }

        if (isset($parsedUrl['host'])) {
            $pass = '' !== ($parsedUrl['pass'] ?? '') ? urldecode($parsedUrl['pass']) : null;
            $user = '' !== ($parsedUrl['user'] ?? '') ? urldecode($parsedUrl['user']) : null;
            $options['host'] = $parsedUrl['host'] ?? $options['host'];
            $options['port'] = $parsedUrl['port'] ?? $options['port'];
            // See: https://github.com/phpredis/phpredis/#auth
            $options['auth'] = $options['auth'] ?? (null !== $pass && null !== $user ? [$user, $pass] : ($pass ?? $user));

            $pathParts = explode('/', rtrim($parsedUrl['path'] ?? '', '/'));
            $options['stream'] = $pathParts[1] ?? $options['stream'];
            $options['group'] = $pathParts[2] ?? $options['group'];
            $options['consumer'] = $pathParts[3] ?? $options['consumer'];
        } else {
            $options['host'] = $parsedUrl['path'];
            $options['port'] = 0;
        }

        return new self($options, $redis);
    }

    private static function parseDsn(string $dsn, array &$options): array
    {
        $url = $dsn;
        $scheme = str_starts_with($dsn, 'rediss:') ? 'rediss' : 'redis';

        if (preg_match('#^'.$scheme.':///([^:@])+$#', $dsn)) {
            $url = str_replace($scheme.':', 'file:', $dsn);
        }

        if (false === $parsedUrl = parse_url($url)) {
            throw new InvalidArgumentException(sprintf('The given Redis DSN "%s" is invalid.', $dsn));
        }
        if (isset($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $dsnOptions);
            $options = array_merge($options, $dsnOptions);
        }
        $parsedUrl['scheme'] = $scheme;

        return $parsedUrl;
    }

    private function claimOldPendingMessages()
    {
        try {
            // This could soon be optimized with https://github.com/antirez/redis/issues/5212 or
            // https://github.com/antirez/redis/issues/6256
            $pendingMessages = $this->connection->xpending($this->stream, $this->group, '-', '+', 1);
        } catch (\RedisException $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }

        $claimableIds = [];
        foreach ($pendingMessages as $pendingMessage) {
            if ($pendingMessage[1] === $this->consumer) {
                $this->couldHavePendingMessages = true;

                return;
            }

            if ($pendingMessage[2] >= $this->redeliverTimeout) {
                $claimableIds[] = $pendingMessage[0];
            }
        }

        if (\count($claimableIds) > 0) {
            try {
                $this->connection->xclaim(
                    $this->stream,
                    $this->group,
                    $this->consumer,
                    $this->redeliverTimeout,
                    $claimableIds,
                    ['JUSTID']
                );

                $this->couldHavePendingMessages = true;
            } catch (\RedisException $e) {
                throw new TransportException($e->getMessage(), 0, $e);
            }
        }

        $this->nextClaim = microtime(true) + $this->claimInterval;
    }

    public function get(): ?array
    {
        if ($this->autoSetup) {
            $this->setup();
        }
        $now = microtime();
        $now = substr($now, 11).substr($now, 2, 3);

        $queuedMessageCount = $this->rawCommand('ZCOUNT', 0, $now);

        while ($queuedMessageCount--) {
            if (!$message = $this->rawCommand('ZPOPMIN', 1)) {
                break;
            }

            [$queuedMessage, $expiry] = $message;

            if (\strlen($expiry) === \strlen($now) ? $expiry > $now : \strlen($expiry) < \strlen($now)) {
                // if a future-placed message is popped because of a race condition with
                // another running consumer, the message is readded to the queue

                if (!$this->rawCommand('ZADD', 'NX', $expiry, $queuedMessage)) {
                    throw new TransportException('Could not add a message to the redis stream.');
                }

                break;
            }

            $decodedQueuedMessage = json_decode($queuedMessage, true);
            $this->add(\array_key_exists('body', $decodedQueuedMessage) ? $decodedQueuedMessage['body'] : $queuedMessage, $decodedQueuedMessage['headers'] ?? [], 0);
        }

        if (!$this->couldHavePendingMessages && $this->nextClaim <= microtime(true)) {
            $this->claimOldPendingMessages();
        }

        $messageId = '>'; // will receive new messages

        if ($this->couldHavePendingMessages) {
            $messageId = '0'; // will receive consumers pending messages
        }

        try {
            $messages = $this->connection->xreadgroup(
                $this->group,
                $this->consumer,
                [$this->stream => $messageId],
                1
            );
        } catch (\RedisException $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }

        if (false === $messages) {
            if ($error = $this->connection->getLastError() ?: null) {
                $this->connection->clearLastError();
            }

            throw new TransportException($error ?? 'Could not read messages from the redis stream.');
        }

        if ($this->couldHavePendingMessages && empty($messages[$this->stream])) {
            $this->couldHavePendingMessages = false;

            // No pending messages so get a new one
            return $this->get();
        }

        foreach ($messages[$this->stream] ?? [] as $key => $message) {
            return [
                'id' => $key,
                'data' => $message,
            ];
        }

        return null;
    }

    public function ack(string $id): void
    {
        try {
            $acknowledged = $this->connection->xack($this->stream, $this->group, [$id]);
            if ($this->deleteAfterAck) {
                $acknowledged = $this->connection->xdel($this->stream, [$id]);
            }
        } catch (\RedisException $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }

        if (!$acknowledged) {
            if ($error = $this->connection->getLastError() ?: null) {
                $this->connection->clearLastError();
            }
            throw new TransportException($error ?? sprintf('Could not acknowledge redis message "%s".', $id));
        }
    }

    public function reject(string $id): void
    {
        try {
            $deleted = $this->connection->xack($this->stream, $this->group, [$id]);
            if ($this->deleteAfterReject) {
                $deleted = $this->connection->xdel($this->stream, [$id]) && $deleted;
            }
        } catch (\RedisException $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }

        if (!$deleted) {
            if ($error = $this->connection->getLastError() ?: null) {
                $this->connection->clearLastError();
            }
            throw new TransportException($error ?? sprintf('Could not delete message "%s" from the redis stream.', $id));
        }
    }

    public function add(string $body, array $headers, int $delayInMs = 0): string
    {
        if ($this->autoSetup) {
            $this->setup();
        }

        try {
            if ($delayInMs > 0) { // the delay is <= 0 for queued messages
                $id = uniqid('', true);
                $message = json_encode([
                    'body' => $body,
                    'headers' => $headers,
                    // Entry need to be unique in the sorted set else it would only be added once to the delayed messages queue
                    'uniqid' => $id,
                ]);

                if (false === $message) {
                    throw new TransportException(json_last_error_msg());
                }

                $now = explode(' ', microtime(), 2);
                $now[0] = str_pad($delayInMs + substr($now[0], 2, 3), 3, '0', \STR_PAD_LEFT);
                if (3 < \strlen($now[0])) {
                    $now[1] += substr($now[0], 0, -3);
                    $now[0] = substr($now[0], -3);

                    if (\is_float($now[1])) {
                        throw new TransportException("Message delay is too big: {$delayInMs}ms.");
                    }
                }

                $added = $this->rawCommand('ZADD', 'NX', $now[1].$now[0], $message);
            } else {
                $message = json_encode([
                    'body' => $body,
                    'headers' => $headers,
                ]);

                if (false === $message) {
                    throw new TransportException(json_last_error_msg());
                }

                if ($this->maxEntries) {
                    $added = $this->connection->xadd($this->stream, '*', ['message' => $message], $this->maxEntries, true);
                } else {
                    $added = $this->connection->xadd($this->stream, '*', ['message' => $message]);
                }

                $id = $added;
            }
        } catch (\RedisException $e) {
            if ($error = $this->connection->getLastError() ?: null) {
                $this->connection->clearLastError();
            }
            throw new TransportException($error ?? $e->getMessage(), 0, $e);
        }

        if (!$added) {
            if ($error = $this->connection->getLastError() ?: null) {
                $this->connection->clearLastError();
            }
            throw new TransportException($error ?? 'Could not add a message to the redis stream.');
        }

        return $id;
    }

    public function setup(): void
    {
        try {
            $this->connection->xgroup('CREATE', $this->stream, $this->group, 0, true);
        } catch (\RedisException $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }

        // group might already exist, ignore
        if ($this->connection->getLastError()) {
            $this->connection->clearLastError();
        }

        if ($this->deleteAfterAck || $this->deleteAfterReject) {
            $groups = $this->connection->xinfo('GROUPS', $this->stream);
            if (
                // support for Redis extension version 5+
                (\is_array($groups) && 1 < \count($groups))
                // support for Redis extension version 4.x
                || (\is_string($groups) && substr_count($groups, '"name"'))
            ) {
                throw new LogicException(sprintf('More than one group exists for stream "%s", delete_after_ack and delete_after_reject cannot be enabled as it risks deleting messages before all groups could consume them.', $this->stream));
            }
        }

        $this->autoSetup = false;
    }

    private function getCurrentTimeInMilliseconds(): int
    {
        return (int) (microtime(true) * 1000);
    }

    public function cleanup(): void
    {
        static $unlink = true;

        if ($unlink) {
            try {
                $unlink = false !== $this->connection->unlink($this->stream, $this->queue);
            } catch (\Throwable) {
                $unlink = false;
            }
        }

        if (!$unlink) {
            $this->connection->del($this->stream, $this->queue);
        }
    }

    private function rawCommand(string $command, ...$arguments): mixed
    {
        try {
            if ($this->connection instanceof \RedisCluster || $this->connection instanceof RedisClusterProxy) {
                $result = $this->connection->rawCommand($this->queue, $command, $this->queue, ...$arguments);
            } else {
                $result = $this->connection->rawCommand($command, $this->queue, ...$arguments);
            }
        } catch (\RedisException $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }

        if (false === $result) {
            if ($error = $this->connection->getLastError() ?: null) {
                $this->connection->clearLastError();
            }
            throw new TransportException($error ?? sprintf('Could not run "%s" on Redis queue.', $command));
        }

        return $result;
    }
}
