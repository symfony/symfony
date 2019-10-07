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

        $this->connection = $redis ?: new \Redis();
        $this->connection->connect($connectionCredentials['host'] ?? '127.0.0.1', $connectionCredentials['port'] ?? 6379);
        $this->connection->setOption(\Redis::OPT_SERIALIZER, $redisOptions['serializer'] ?? \Redis::SERIALIZER_PHP);

        if (isset($connectionCredentials['auth'])) {
            $this->connection->auth($connectionCredentials['auth']);
        }

        if (($dbIndex = $configuration['dbindex'] ?? self::DEFAULT_OPTIONS['dbindex']) && !$this->connection->select($dbIndex)) {
            throw new InvalidArgumentException(sprintf('Redis connection failed: %s', $redis->getLastError()));
        }

        $this->stream = $configuration['stream'] ?? self::DEFAULT_OPTIONS['stream'];
        $this->group = $configuration['group'] ?? self::DEFAULT_OPTIONS['group'];
        $this->consumer = $configuration['consumer'] ?? self::DEFAULT_OPTIONS['consumer'];
        $this->autoSetup = $configuration['auto_setup'] ?? self::DEFAULT_OPTIONS['auto_setup'];
        $this->maxEntries = $configuration['stream_max_entries'] ?? self::DEFAULT_OPTIONS['stream_max_entries'];
    }

    public static function fromDsn(string $dsn, array $redisOptions = [], \Redis $redis = null): self
    {
        if (false === $parsedUrl = parse_url($dsn)) {
            throw new InvalidArgumentException(sprintf('The given Redis DSN "%s" is invalid.', $dsn));
        }

        $pathParts = explode('/', $parsedUrl['path'] ?? '');

        $stream = $pathParts[1] ?? null;
        $group = $pathParts[2] ?? null;
        $consumer = $pathParts[3] ?? null;

        $connectionCredentials = [
            'host' => $parsedUrl['host'] ?? '127.0.0.1',
            'port' => $parsedUrl['port'] ?? 6379,
            'auth' => $parsedUrl['pass'] ?? $parsedUrl['user'] ?? null,
        ];

        if (isset($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $redisOptions);
        }

        $autoSetup = null;
        if (\array_key_exists('auto_setup', $redisOptions)) {
            $autoSetup = filter_var($redisOptions['auto_setup'], FILTER_VALIDATE_BOOLEAN);
            unset($redisOptions['auto_setup']);
        }

        $maxEntries = null;
        if (\array_key_exists('stream_max_entries', $redisOptions)) {
            $maxEntries = filter_var($redisOptions['stream_max_entries'], FILTER_VALIDATE_INT);
            unset($redisOptions['stream_max_entries']);
        }

        $dbIndex = null;
        if (\array_key_exists('dbindex', $redisOptions)) {
            $dbIndex = filter_var($redisOptions['dbindex'], FILTER_VALIDATE_INT);
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

        $messageId = '>'; // will receive new messages

        if ($this->couldHavePendingMessages) {
            $messageId = '0'; // will receive consumers pending messages
        }

        $e = null;
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

    public function add(string $body, array $headers): void
    {
        if ($this->autoSetup) {
            $this->setup();
        }

        try {
            if ($this->maxEntries) {
                $added = $this->connection->xadd($this->stream, '*', ['message' => json_encode(
                    ['body' => $body, 'headers' => $headers]
                )], $this->maxEntries, true);
            } else {
                $added = $this->connection->xadd($this->stream, '*', ['message' => json_encode(
                    ['body' => $body, 'headers' => $headers]
                )]);
            }
        } catch (\RedisException $e) {
            throw new TransportException($e->getMessage(), 0, $e);
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
}
