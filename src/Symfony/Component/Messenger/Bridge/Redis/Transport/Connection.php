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

use Relay\Relay;
use Relay\Sentinel;
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
        'serializer' => 1, // see \Redis::SERIALIZER_PHP,
        'sentinel_master' => null, // String, master to look for (optional, default is NULL meaning Sentinel support is disabled)
        'timeout' => 0.0, // Float, value in seconds (optional, default is 0 meaning unlimited)
        'read_timeout' => 0.0, //  Float, value in seconds (optional, default is 0 meaning unlimited)
        'retry_interval' => 0, //  Int, value in milliseconds (optional, default is 0)
        'persistent_id' => null, // String, persistent connection id (optional, default is NULL meaning not persistent)
        'ssl' => null, // see https://php.net/context.ssl
    ];

    private \Redis|Relay|\RedisCluster|\Closure $redis;
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

    public function __construct(array $options, \Redis|Relay|\RedisCluster $redis = null)
    {
        if (version_compare(phpversion('redis'), '4.3.0', '<')) {
            throw new LogicException('The redis transport requires php-redis 4.3.0 or higher.');
        }

        $options += self::DEFAULT_OPTIONS;
        $host = $options['host'];
        $port = $options['port'];
        $auth = $options['auth'];
        $sentinelMaster = $options['sentinel_master'];

        if (null !== $sentinelMaster && !class_exists(\RedisSentinel::class) && !class_exists(Sentinel::class)) {
            throw new InvalidArgumentException('Redis Sentinel support requires ext-redis>=5.2, or ext-relay.');
        }

        if (null !== $sentinelMaster && ($redis instanceof \RedisCluster || \is_array($host))) {
            throw new InvalidArgumentException('Cannot configure Redis Sentinel and Redis Cluster instance at the same time.');
        }

        if (\is_array($host) || $redis instanceof \RedisCluster) {
            $hosts = \is_string($host) ? [$host.':'.$port] : $host; // Always ensure we have an array
            $this->redis = static fn () => self::initializeRedisCluster($redis, $hosts, $auth, $options);
        } else {
            if (null !== $sentinelMaster) {
                $sentinelClass = \extension_loaded('redis') ? \RedisSentinel::class : Sentinel::class;
                $sentinelClient = new $sentinelClass($host, $port, $options['timeout'], $options['persistent_id'], $options['retry_interval'], $options['read_timeout']);

                if (!$address = $sentinelClient->getMasterAddrByName($sentinelMaster)) {
                    throw new InvalidArgumentException(sprintf('Failed to retrieve master information from master name "%s" and address "%s:%d".', $sentinelMaster, $host, $port));
                }

                [$host, $port] = $address;
            }

            $this->redis = static fn () => self::initializeRedis($redis ?? (\extension_loaded('redis') ? new \Redis() : new Relay()), $host, $port, $auth, $options);
        }

        if (!$options['lazy']) {
            $this->getRedis();
        }

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
    private static function initializeRedis(\Redis|Relay $redis, string $host, int $port, string|array|null $auth, array $params): \Redis|Relay
    {
        $connect = isset($params['persistent_id']) ? 'pconnect' : 'connect';
        $redis->{$connect}($host, $port, $params['timeout'], $params['persistent_id'], $params['retry_interval'], $params['read_timeout'], ...(\defined('Redis::SCAN_PREFIX') || \extension_loaded('relay')) ? [['stream' => $params['ssl'] ?? null]] : []);

        $redis->setOption($redis instanceof \Redis ? \Redis::OPT_SERIALIZER : Relay::OPT_SERIALIZER, $params['serializer']);

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
        $redis ??= new \RedisCluster(null, $hosts, $params['timeout'], $params['read_timeout'], (bool) ($params['persistent'] ?? false), $auth, ...\defined('Redis::SCAN_PREFIX') ? [$params['ssl'] ?? null] : []);
        $redis->setOption(\Redis::OPT_SERIALIZER, $params['serializer']);

        return $redis;
    }

    public static function fromDsn(#[\SensitiveParameter] string $dsn, array $options = [], \Redis|Relay|\RedisCluster $redis = null): self
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
            $parsedUrl['host'] = array_map(function ($parsedUrl) use ($tls) {
                if (!isset($parsedUrl['host'])) {
                    throw new InvalidArgumentException('Missing host in DSN, it must be defined when using Redis Cluster.');
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
                'boolean' => filter_var($options[$k] ?? $v, \FILTER_VALIDATE_BOOL),
                'double' => filter_var($options[$k] ?? $v, \FILTER_VALIDATE_FLOAT),
                default => $options[$k] ?? $v,
            };
        }

        $pass = '' !== ($parsedUrl['pass'] ?? '') ? urldecode($parsedUrl['pass']) : null;
        $user = '' !== ($parsedUrl['user'] ?? '') ? urldecode($parsedUrl['user']) : null;
        $options['auth'] ??= null !== $pass && null !== $user ? [$user, $pass] : ($pass ?? $user);

        if (isset($parsedUrl['host'])) {
            $options['host'] = $parsedUrl['host'] ?? $options['host'];
            $options['port'] = $parsedUrl['port'] ?? $options['port'];

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

        $url = preg_replace_callback('#^'.$scheme.':(//)?(?:(?:(?<user>[^:@]*+):)?(?<password>[^@]*+)@)?#', function ($m) use (&$auth) {
            if (isset($m['password'])) {
                if (!\in_array($m['user'], ['', 'default'], true)) {
                    $auth['user'] = $m['user'];
                }

                $auth['pass'] = $m['password'];
            }

            return 'file:'.($m[1] ?? '');
        }, $url);

        if (false === $parsedUrl = parse_url($url)) {
            throw new InvalidArgumentException('The given Redis DSN is invalid.');
        }

        if (null !== $auth) {
            unset($parsedUrl['user']); // parse_url thinks //0@localhost/ is a username of "0"! doh!
            $parsedUrl += ($auth ?? []); // But don't worry as $auth array will have user, user/pass or pass as needed
        }

        if (isset($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $dsnOptions);
            $options = array_merge($options, $dsnOptions);
        }
        $parsedUrl['scheme'] = $scheme;

        return $parsedUrl;
    }

    private function claimOldPendingMessages(): void
    {
        try {
            // This could soon be optimized with https://github.com/antirez/redis/issues/5212 or
            // https://github.com/antirez/redis/issues/6256
            $pendingMessages = $this->getRedis()->xpending($this->stream, $this->group, '-', '+', 1);
        } catch (\RedisException|\Relay\Exception $e) {
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
                $this->getRedis()->xclaim(
                    $this->stream,
                    $this->group,
                    $this->consumer,
                    $this->redeliverTimeout,
                    $claimableIds,
                    ['JUSTID']
                );

                $this->couldHavePendingMessages = true;
            } catch (\RedisException|\Relay\Exception $e) {
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
        $redis = $this->getRedis();

        try {
            $messages = $redis->xreadgroup(
                $this->group,
                $this->consumer,
                [$this->stream => $messageId],
                1
            );
        } catch (\RedisException|\Relay\Exception $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }

        if (false === $messages) {
            if ($error = $redis->getLastError() ?: null) {
                $redis->clearLastError();
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
        $redis = $this->getRedis();

        try {
            $acknowledged = $redis->xack($this->stream, $this->group, [$id]);
            if ($this->deleteAfterAck) {
                $acknowledged = $redis->xdel($this->stream, [$id]);
            }
        } catch (\RedisException|\Relay\Exception $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }

        if (!$acknowledged) {
            if ($error = $redis->getLastError() ?: null) {
                $redis->clearLastError();
            }
            throw new TransportException($error ?? sprintf('Could not acknowledge redis message "%s".', $id));
        }
    }

    public function reject(string $id): void
    {
        $redis = $this->getRedis();

        try {
            $deleted = $redis->xack($this->stream, $this->group, [$id]);
            if ($this->deleteAfterReject) {
                $deleted = $redis->xdel($this->stream, [$id]) && $deleted;
            }
        } catch (\RedisException|\Relay\Exception $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }

        if (!$deleted) {
            if ($error = $redis->getLastError() ?: null) {
                $redis->clearLastError();
            }
            throw new TransportException($error ?? sprintf('Could not delete message "%s" from the redis stream.', $id));
        }
    }

    public function add(string $body, array $headers, int $delayInMs = 0): string
    {
        if ($this->autoSetup) {
            $this->setup();
        }
        $redis = $this->getRedis();

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
                    $added = $redis->xadd($this->stream, '*', ['message' => $message], $this->maxEntries, true);
                } else {
                    $added = $redis->xadd($this->stream, '*', ['message' => $message]);
                }

                $id = $added;
            }
        } catch (\RedisException|\Relay\Exception $e) {
            if ($error = $redis->getLastError() ?: null) {
                $redis->clearLastError();
            }
            throw new TransportException($error ?? $e->getMessage(), 0, $e);
        }

        if (!$added) {
            if ($error = $redis->getLastError() ?: null) {
                $redis->clearLastError();
            }
            throw new TransportException($error ?? 'Could not add a message to the redis stream.');
        }

        return $id;
    }

    public function setup(): void
    {
        $redis = $this->getRedis();

        try {
            $redis->xgroup('CREATE', $this->stream, $this->group, 0, true);
        } catch (\RedisException|\Relay\Exception $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }

        // group might already exist, ignore
        if ($redis->getLastError()) {
            $redis->clearLastError();
        }

        if ($this->deleteAfterAck || $this->deleteAfterReject) {
            $groups = $redis->xinfo('GROUPS', $this->stream);
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

    public function cleanup(): void
    {
        static $unlink = true;
        $redis = $this->getRedis();

        if ($unlink) {
            try {
                $unlink = false !== $redis->unlink($this->stream, $this->queue);
            } catch (\Throwable) {
                $unlink = false;
            }
        }

        if (!$unlink) {
            $redis->del($this->stream, $this->queue);
        }
    }

    public function getMessageCount(): int
    {
        $redis = $this->getRedis();
        $groups = $redis->xinfo('GROUPS', $this->stream) ?: [];

        $lastDeliveredId = null;
        foreach ($groups as $group) {
            if ($group['name'] !== $this->group) {
                continue;
            }

            // Use "lag" key provided by Redis 7.x. See https://redis.io/commands/xinfo-groups/#consumer-group-lag.
            if (isset($group['lag'])) {
                return $group['lag'];
            }

            if (!isset($group['last-delivered-id'])) {
                return 0;
            }

            $lastDeliveredId = $group['last-delivered-id'];
            break;
        }

        if (null === $lastDeliveredId) {
            return 0;
        }

        // Iterate through the stream. See https://redis.io/commands/xrange/#iterating-a-stream.
        $useExclusiveRangeInterval = version_compare(phpversion('redis'), '6.2.0', '>=');
        $total = 0;
        while (true) {
            if (!$range = $redis->xRange($this->stream, $lastDeliveredId, '+', 100)) {
                return $total;
            }

            $total += \count($range);

            if ($useExclusiveRangeInterval) {
                $lastDeliveredId = preg_replace_callback('#\d+$#', static fn (array $matches) => (int) $matches[0] + 1, array_key_last($range));
            } else {
                $lastDeliveredId = '('.array_key_last($range);
            }
        }
    }

    private function rawCommand(string $command, ...$arguments): mixed
    {
        $redis = $this->getRedis();

        try {
            if ($redis instanceof \RedisCluster) {
                $result = $redis->rawCommand($this->queue, $command, $this->queue, ...$arguments);
            } else {
                $result = $redis->rawCommand($command, $this->queue, ...$arguments);
            }
        } catch (\RedisException|\Relay\Exception $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }

        if (false === $result) {
            if ($error = $redis->getLastError() ?: null) {
                $redis->clearLastError();
            }
            throw new TransportException($error ?? sprintf('Could not run "%s" on Redis queue.', $command));
        }

        return $result;
    }

    private function getRedis(): \Redis|Relay|\RedisCluster
    {
        if ($this->redis instanceof \Closure) {
            $this->redis = ($this->redis)();
        }

        return $this->redis;
    }
}
