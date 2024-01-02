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
 *
 * @final
 */
class Connection
{
    private const DEFAULT_OPTIONS = [
        'stream' => 'messages',
        'group' => 'symfony',
        'consumer' => 'consumer',
        'auto_setup' => true,
        'delete_after_ack' => false,
        'delete_after_reject' => true,
        'stream_max_entries' => 0, // any value higher than 0 defines an approximate maximum number of stream entries
        'dbindex' => 0,
        'tls' => false,
        'redeliver_timeout' => 3600, // Timeout before redeliver messages still in pending state (seconds)
        'claim_interval' => 60000, // Interval by which pending/abandoned messages should be checked
        'lazy' => false,
        'auth' => null,
        'serializer' => \Redis::SERIALIZER_PHP,
    ];

    private $connection;
    private $stream;
    private $queue;
    private $group;
    private $consumer;
    private $autoSetup;
    private $maxEntries;
    private $redeliverTimeout;
    private $nextClaim = 0.0;
    private $claimInterval;
    private $deleteAfterAck;
    private $deleteAfterReject;
    private $couldHavePendingMessages = true;

    /**
     * @param \Redis|\RedisCluster|null $redis
     */
    public function __construct(array $configuration, array $connectionCredentials = [], array $redisOptions = [], $redis = null)
    {
        if (version_compare(phpversion('redis'), '4.3.0', '<')) {
            throw new LogicException('The redis transport requires php-redis 4.3.0 or higher.');
        }

        $host = $connectionCredentials['host'] ?? '127.0.0.1';
        $port = $connectionCredentials['port'] ?? 6379;
        $serializer = $redisOptions['serializer'] ?? \Redis::SERIALIZER_PHP;
        $dbIndex = $configuration['dbindex'] ?? self::DEFAULT_OPTIONS['dbindex'];
        $auth = $connectionCredentials['auth'] ?? null;
        if ('' === $auth) {
            $auth = null;
        }

        $lazy = $configuration['lazy'] ?? self::DEFAULT_OPTIONS['lazy'];
        if (\is_array($host) || $redis instanceof \RedisCluster) {
            $hosts = \is_string($host) ? [$host.':'.$port] : $host; // Always ensure we have an array
            $initializer = static function ($redis) use ($hosts, $auth, $serializer) {
                return self::initializeRedisCluster($redis, $hosts, $auth, $serializer);
            };
            $redis = $lazy ? new RedisClusterProxy($redis, $initializer) : $initializer($redis);
        } else {
            $redis = $redis ?? new \Redis();
            $initializer = static function ($redis) use ($host, $port, $auth, $serializer, $dbIndex) {
                return self::initializeRedis($redis, $host, $port, $auth, $serializer, $dbIndex);
            };
            $redis = $lazy ? new RedisProxy($redis, $initializer) : $initializer($redis);
        }

        $this->connection = $redis;

        foreach (['stream', 'group', 'consumer'] as $key) {
            if (isset($configuration[$key]) && '' === $configuration[$key]) {
                throw new InvalidArgumentException(sprintf('"%s" should be configured, got an empty string.', $key));
            }
        }

        $this->stream = $configuration['stream'] ?? self::DEFAULT_OPTIONS['stream'];
        $this->group = $configuration['group'] ?? self::DEFAULT_OPTIONS['group'];
        $this->consumer = $configuration['consumer'] ?? self::DEFAULT_OPTIONS['consumer'];
        $this->queue = $this->stream.'__queue';
        $this->autoSetup = $configuration['auto_setup'] ?? self::DEFAULT_OPTIONS['auto_setup'];
        $this->maxEntries = $configuration['stream_max_entries'] ?? self::DEFAULT_OPTIONS['stream_max_entries'];
        $this->deleteAfterAck = $configuration['delete_after_ack'] ?? self::DEFAULT_OPTIONS['delete_after_ack'];
        $this->deleteAfterReject = $configuration['delete_after_reject'] ?? self::DEFAULT_OPTIONS['delete_after_reject'];
        $this->redeliverTimeout = ($configuration['redeliver_timeout'] ?? self::DEFAULT_OPTIONS['redeliver_timeout']) * 1000;
        $this->claimInterval = ($configuration['claim_interval'] ?? self::DEFAULT_OPTIONS['claim_interval']) / 1000;
    }

    /**
     * @param string|string[]|null $auth
     */
    private static function initializeRedis(\Redis $redis, string $host, int $port, $auth, int $serializer, int $dbIndex): \Redis
    {
        if ($redis->isConnected()) {
            return $redis;
        }

        $redis->connect($host, $port);
        $redis->setOption(\Redis::OPT_SERIALIZER, $serializer);

        if (null !== $auth && !$redis->auth($auth)) {
            throw new InvalidArgumentException('Redis connection failed: '.$redis->getLastError());
        }

        if ($dbIndex && !$redis->select($dbIndex)) {
            throw new InvalidArgumentException('Redis connection failed: '.$redis->getLastError());
        }

        return $redis;
    }

    /**
     * @param string|string[]|null $auth
     */
    private static function initializeRedisCluster(?\RedisCluster $redis, array $hosts, $auth, int $serializer): \RedisCluster
    {
        if (null === $redis) {
            $redis = new \RedisCluster(null, $hosts, 0.0, 0.0, false, $auth);
        }

        $redis->setOption(\Redis::OPT_SERIALIZER, $serializer);

        return $redis;
    }

    /**
     * @param \Redis|\RedisCluster|null $redis
     */
    public static function fromDsn(string $dsn, array $redisOptions = [], $redis = null): self
    {
        if (false === strpos($dsn, ',')) {
            $params = self::parseDsn($dsn, $redisOptions);
        } else {
            $dsns = explode(',', $dsn);
            $parsedUrls = array_map(function ($dsn) use (&$redisOptions) {
                return self::parseDsn($dsn, $redisOptions);
            }, $dsns);

            // Merge all the URLs, the last one overrides the previous ones
            $params = array_merge(...$parsedUrls);

            // Regroup all the hosts in an array interpretable by RedisCluster
            $params['host'] = array_map(function ($parsedUrl) {
                if (!isset($parsedUrl['host'])) {
                    throw new InvalidArgumentException('Missing host in DSN, it must be defined when using Redis Cluster.');
                }

                return $parsedUrl['host'].':'.($parsedUrl['port'] ?? 6379);
            }, $parsedUrls, $dsns);
        }

        self::validateOptions($redisOptions);

        $autoSetup = null;
        if (\array_key_exists('auto_setup', $redisOptions)) {
            $autoSetup = filter_var($redisOptions['auto_setup'], \FILTER_VALIDATE_BOOLEAN);
            unset($redisOptions['auto_setup']);
        }

        $maxEntries = null;
        if (\array_key_exists('stream_max_entries', $redisOptions)) {
            $maxEntries = filter_var($redisOptions['stream_max_entries'], \FILTER_VALIDATE_INT);
            unset($redisOptions['stream_max_entries']);
        }

        $deleteAfterAck = null;
        if (\array_key_exists('delete_after_ack', $redisOptions)) {
            $deleteAfterAck = filter_var($redisOptions['delete_after_ack'], \FILTER_VALIDATE_BOOLEAN);
            unset($redisOptions['delete_after_ack']);
        } else {
            trigger_deprecation('symfony/redis-messenger', '5.4', 'Not setting the "delete_after_ack" boolean option explicitly is deprecated, its default value will change to true in 6.0.');
        }

        $deleteAfterReject = null;
        if (\array_key_exists('delete_after_reject', $redisOptions)) {
            $deleteAfterReject = filter_var($redisOptions['delete_after_reject'], \FILTER_VALIDATE_BOOLEAN);
            unset($redisOptions['delete_after_reject']);
        }

        $dbIndex = null;
        if (\array_key_exists('dbindex', $redisOptions)) {
            $dbIndex = filter_var($redisOptions['dbindex'], \FILTER_VALIDATE_INT);
            unset($redisOptions['dbindex']);
        }

        $tls = 'rediss' === $params['scheme'];
        if (\array_key_exists('tls', $redisOptions)) {
            trigger_deprecation('symfony/redis-messenger', '5.3', 'Providing "tls" parameter is deprecated, use "rediss://" DSN scheme instead');
            $tls = filter_var($redisOptions['tls'], \FILTER_VALIDATE_BOOLEAN);
            unset($redisOptions['tls']);
        }

        $redeliverTimeout = null;
        if (\array_key_exists('redeliver_timeout', $redisOptions)) {
            $redeliverTimeout = filter_var($redisOptions['redeliver_timeout'], \FILTER_VALIDATE_INT);
            unset($redisOptions['redeliver_timeout']);
        }

        $claimInterval = null;
        if (\array_key_exists('claim_interval', $redisOptions)) {
            $claimInterval = filter_var($redisOptions['claim_interval'], \FILTER_VALIDATE_INT);
            unset($redisOptions['claim_interval']);
        }

        $configuration = [
            'stream' => $redisOptions['stream'] ?? null,
            'group' => $redisOptions['group'] ?? null,
            'consumer' => $redisOptions['consumer'] ?? null,
            'lazy' => $redisOptions['lazy'] ?? self::DEFAULT_OPTIONS['lazy'],
            'auto_setup' => $autoSetup,
            'stream_max_entries' => $maxEntries,
            'delete_after_ack' => $deleteAfterAck,
            'delete_after_reject' => $deleteAfterReject,
            'dbindex' => $dbIndex,
            'redeliver_timeout' => $redeliverTimeout,
            'claim_interval' => $claimInterval,
        ];

        if (isset($params['host'])) {
            $user = isset($params['user']) && '' !== $params['user'] ? rawurldecode($params['user']) : null;
            $pass = isset($params['pass']) && '' !== $params['pass'] ? rawurldecode($params['pass']) : null;
            $connectionCredentials = [
                'host' => $params['host'],
                'port' => $params['port'] ?? 6379,
                // See: https://github.com/phpredis/phpredis/#auth
                'auth' => $redisOptions['auth'] ?? (null !== $pass && null !== $user ? [$user, $pass] : ($pass ?? $user)),
            ];

            $pathParts = explode('/', rtrim($params['path'] ?? '', '/'));

            $configuration['stream'] = $pathParts[1] ?? $configuration['stream'];
            $configuration['group'] = $pathParts[2] ?? $configuration['group'];
            $configuration['consumer'] = $pathParts[3] ?? $configuration['consumer'];
            if ($tls) {
                $connectionCredentials['host'] = 'tls://'.$connectionCredentials['host'];
            }
        } else {
            $connectionCredentials = [
                'host' => $params['path'],
                'port' => 0,
            ];
        }

        return new self($configuration, $connectionCredentials, $redisOptions, $redis);
    }

    private static function parseDsn(string $dsn, array &$redisOptions): array
    {
        $url = $dsn;
        $scheme = 0 === strpos($dsn, 'rediss:') ? 'rediss' : 'redis';

        if (preg_match('#^'.$scheme.':///([^:@])+$#', $dsn)) {
            $url = str_replace($scheme.':', 'file:', $dsn);
        }

        if (false === $params = parse_url($url)) {
            throw new InvalidArgumentException('The given Redis DSN is invalid.');
        }
        if (isset($params['query'])) {
            parse_str($params['query'], $dsnOptions);
            $redisOptions = array_merge($redisOptions, $dsnOptions);
        }

        return $params;
    }

    private static function validateOptions(array $options): void
    {
        $availableOptions = array_keys(self::DEFAULT_OPTIONS);

        if (0 < \count($invalidOptions = array_diff(array_keys($options), $availableOptions))) {
            trigger_deprecation('symfony/messenger', '5.1', 'Invalid option(s) "%s" passed to the Redis Messenger transport. Passing invalid options is deprecated.', implode('", "', $invalidOptions));
        }
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

        $queuedMessageCount = $this->rawCommand('ZCOUNT', 0, $now) ?? 0;

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

    public function add(string $body, array $headers, int $delayInMs = 0): void
    {
        if ($this->autoSetup) {
            $this->setup();
        }

        try {
            if ($delayInMs > 0) { // the delay is <= 0 for queued messages
                $message = json_encode([
                    'body' => $body,
                    'headers' => $headers,
                    // Entry need to be unique in the sorted set else it would only be added once to the delayed messages queue
                    'uniqid' => uniqid('', true),
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
            } catch (\Throwable $e) {
                $unlink = false;
            }
        }

        if (!$unlink) {
            $this->connection->del($this->stream, $this->queue);
        }
    }

    /**
     * @return mixed
     */
    private function rawCommand(string $command, ...$arguments)
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

if (!class_exists(\Symfony\Component\Messenger\Transport\RedisExt\Connection::class, false)) {
    class_alias(Connection::class, \Symfony\Component\Messenger\Transport\RedisExt\Connection::class);
}
