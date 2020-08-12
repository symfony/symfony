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

use Symfony\Component\Messenger\Bridge\Redis\Transport\Connection as BridgeConnection;

<<<<<<< HEAD
trigger_deprecation('symfony/messenger', '5.1', 'The "%s" class is deprecated, use "%s" instead. The RedisExt transport has been moved to package "symfony/redis-messenger" and will not be included by default in 6.0. Run "composer require symfony/redis-messenger".', Connection::class, BridgeConnection::class);
=======
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

        $this->connection = $redis ?: new \Redis();
        $this->connection->connect($connectionCredentials['host'] ?? '127.0.0.1', $connectionCredentials['port'] ?? 6379);
        $this->connection->setOption(\Redis::OPT_SERIALIZER, $redisOptions['serializer'] ?? \Redis::SERIALIZER_PHP);

        $auth = $connectionCredentials['auth'] ?? null;
        if ('' === $auth) {
            $auth = null;
        }

        if (null !== $auth && !$this->connection->auth($auth)) {
            throw new InvalidArgumentException('Redis connection failed: '.$redis->getLastError());
        }

        if (($dbIndex = $configuration['dbindex'] ?? self::DEFAULT_OPTIONS['dbindex']) && !$this->connection->select($dbIndex)) {
            throw new InvalidArgumentException('Redis connection failed: '.$redis->getLastError());
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

        try {
            $queuedMessageCount = $this->connection->zcount($this->queue, 0, $this->getCurrentTimeInMilliseconds());
        } catch (\RedisException $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }

        if ($queuedMessageCount) {
            for ($i = 0; $i < $queuedMessageCount; ++$i) {
                try {
                    $queuedMessages = $this->connection->zpopmin($this->queue, 1);
                } catch (\RedisException $e) {
                    throw new TransportException($e->getMessage(), 0, $e);
                }

                foreach ($queuedMessages as $queuedMessage => $time) {
                    $queuedMessage = json_decode($queuedMessage, true);
                    // if a futured placed message is actually popped because of a race condition with
                    // another running message consumer, the message is readded to the queue by add function
                    // else its just added stream and will be available for all stream consumers
                    $this->add(
                        $queuedMessage['body'],
                        $queuedMessage['headers'],
                        $time - $this->getCurrentTimeInMilliseconds()
                    );
                }
            }
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
>>>>>>> 4.4

class_exists(BridgeConnection::class);

if (false) {
    /**
     * @deprecated since Symfony 5.1, to be removed in 6.0. Use symfony/redis-messenger instead.
     */
    class Connection
    {
    }
}
