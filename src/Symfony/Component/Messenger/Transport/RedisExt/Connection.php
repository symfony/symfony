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
        'stream' => 'messages',
        'group' => 'symfony',
        'consumer' => 'consumer',
        'auto_setup' => true,
        'stream_max_entries' => 0, // any value higher than 0 defines an approximate maximum number of stream entries
        'dbindex' => 0,
    ];

    private $connection;
    private $stream;
    private $queue;
    private $group;
    private $consumer;
    private $autoSetup;
    private $maxEntries;
    private $couldHavePendingMessages = true;

    public function __construct(array $configuration, array $connectionCredentials = [], array $redisOptions = [], \Redis $redis = null)
    {
        if (version_compare(phpversion('redis'), '4.3.0', '<')) {
            throw new LogicException('The redis transport requires php-redis 4.3.0 or higher.');
        }

        $this->connection = $redis ?? new \Redis();
        $this->connection->connect($connectionCredentials['host'] ?? '127.0.0.1', $connectionCredentials['port'] ?? 6379);
        $this->connection->setOption(\Redis::OPT_SERIALIZER, $redisOptions['serializer'] ?? \Redis::SERIALIZER_PHP);

        $auth = $connectionCredentials['auth'] ?? null;
        if ('' === $auth) {
            $auth = null;
        }

        if (null !== $auth && !$this->connection->auth($auth)) {
            throw new InvalidArgumentException('Redis connection failed: '.$this->connection->getLastError());
        }

        if (($dbIndex = $configuration['dbindex'] ?? self::DEFAULT_OPTIONS['dbindex']) && !$this->connection->select($dbIndex)) {
            throw new InvalidArgumentException('Redis connection failed: '.$this->connection->getLastError());
        }

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
    }

    public static function fromDsn(string $dsn, array $redisOptions = [], \Redis $redis = null): self
    {
        if (false === $parsedUrl = parse_url($dsn)) {
            throw new InvalidArgumentException(sprintf('The given Redis DSN "%s" is invalid.', $dsn));
        }

        $pathParts = explode('/', rtrim($parsedUrl['path'] ?? '', '/'));

        $stream = $pathParts[1] ?? $redisOptions['stream'] ?? null;
        $group = $pathParts[2] ?? $redisOptions['group'] ?? null;
        $consumer = $pathParts[3] ?? $redisOptions['consumer'] ?? null;
        $pass = '' !== ($parsedUrl['pass'] ?? '') ? urldecode($parsedUrl['pass']) : null;
        $user = '' !== ($parsedUrl['user'] ?? '') ? urldecode($parsedUrl['user']) : null;

        $connectionCredentials = [
            'host' => $parsedUrl['host'] ?? '127.0.0.1',
            'port' => $parsedUrl['port'] ?? 6379,
            // See: https://github.com/phpredis/phpredis/#auth
            'auth' => null !== $pass && null !== $user ? [$user, $pass] : ($pass ?? $user),
        ];

        if (isset($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $dsnOptions);
            $redisOptions = array_merge($redisOptions, $dsnOptions);
        }

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

        $dbIndex = null;
        if (\array_key_exists('dbindex', $redisOptions)) {
            $dbIndex = filter_var($redisOptions['dbindex'], \FILTER_VALIDATE_INT);
            unset($redisOptions['dbindex']);
        }

        return new self([
            'stream' => $stream,
            'group' => $group,
            'consumer' => $consumer,
            'auto_setup' => $autoSetup,
            'stream_max_entries' => $maxEntries,
            'dbindex' => $dbIndex,
        ], $connectionCredentials, $redisOptions, $redis);
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
            if (![$queuedMessage, $expiry] = $this->rawCommand('ZPOPMIN', 1)) {
                break;
            }

            if (\strlen($expiry) === \strlen($now) ? $expiry > $now : \strlen($expiry) < \strlen($now)) {
                // if a future-placed message is popped because of a race condition with
                // another running consumer, the message is readded to the queue

                if (!$this->rawCommand('ZADD', 'NX', $expiry, $queuedMessage)) {
                    throw new TransportException('Could not add a message to the redis stream.');
                }

                break;
            }

            $queuedMessage = json_decode($queuedMessage, true);
            $this->add($queuedMessage['body'], $queuedMessage['headers'], 0);
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
            $redisEnvelope = json_decode($message['message'], true);

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
        try {
            $acknowledged = $this->connection->xack($this->stream, $this->group, [$id]);
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
            $deleted = $this->connection->xdel($this->stream, [$id]) && $deleted;
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

        $this->autoSetup = false;
    }

    public function cleanup(): void
    {
        $this->connection->del($this->stream);
        $this->connection->del($this->queue);
    }

    /**
     * @return mixed
     */
    private function rawCommand(string $command, ...$arguments)
    {
        try {
            $result = $this->connection->rawCommand($command, $this->queue, ...$arguments);
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
