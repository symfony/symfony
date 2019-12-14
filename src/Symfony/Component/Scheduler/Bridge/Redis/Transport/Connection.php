<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Bridge\Redis\Transport;

use Symfony\Component\Scheduler\Bridge\Google\Task\State;
use Symfony\Component\Scheduler\Exception\InvalidArgumentException;
use Symfony\Component\Scheduler\Exception\LogicException;
use Symfony\Component\Scheduler\Transport\ConnectionInterface;
use Symfony\Component\Scheduler\Transport\Dsn;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class Connection implements ConnectionInterface
{
    private const DEFAULT_OPTIONS = [
        'host' => '127.0.0.1',
        'port'    => 6379,
        'timeout' => 30,
        'dbindex' => 'scheduler_tasks',
    ];

    private $connection;
    private $queue;

    public function __construct(Dsn $dsn, ?\Redis $redis = null)
    {
        if (version_compare(phpversion('redis'), '4.3.0', '<')) {
            throw new LogicException('The redis transport requires php-redis 4.3.0 or higher.');
        }

        $this->connection = $redis ?? new \Redis();
        $this->connection->connect(
            $dsn->getOption('host') ?? self::DEFAULT_OPTIONS['host'],
            $dsn->getOption('port') ?? self::DEFAULT_OPTIONS['port'],
            $dsn->getOption('timeout') ?? self::DEFAULT_OPTIONS['timeout']
        );

        if (null !== $dsn->getOption('auth') && !$this->connection->auth($dsn->getOption('auth'))) {
            throw new InvalidArgumentException(sprintf('Redis connection failed: %s.', $redis->getLastError()));
        }

        if (($this->queue = $dsn->getOption('dbindex') ?? self::DEFAULT_OPTIONS['dbindex']) && !$this->connection->select($this->queue)) {
            throw new InvalidArgumentException(sprintf('Redis connection failed: %s.', $redis->getLastError()));
        }
    }

    public static function createFromDsn(Dsn $dsn, ?\Redis $redis = null): self
    {
        return new self($dsn, $redis);
    }

    /**
     * {@inheritdoc}
     */
    public function list(): array
    {
    }

    public function add(string $taskName, string $body): void
    {
        $this->connection->rPush($taskName, $body);
    }

    public function update(string $taskName, array $body): void
    {
        $this->connection->rPush($taskName, $body);
    }

    public function get(string $taskName): array
    {
        return $this->connection->blPop([$taskName], self::DEFAULT_OPTIONS['timeout']);
    }

    /**
     * {@inheritdoc}
     */
    public function pause(string $taskName): void
    {
        $task = $this->connection->get($taskName);
        $task['state'] = State::PAUSED;

        $this->update($taskName, $task);
    }

    /**
     * {@inheritdoc}
     */
    public function resume(string $taskName): void
    {
        $task = $this->connection->get($taskName);
        $task['state'] = State::ENABLED;

        $this->update($taskName, $task);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $taskName): void
    {
        if (0 === $this->connection->del($taskName)) {
            throw new InvalidArgumentException('The task does not exist');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function empty(): void
    {
        $this->connection->flushDB();
    }
}
