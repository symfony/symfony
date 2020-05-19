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
use Symfony\Component\Scheduler\Exception\TransportException;
use Symfony\Component\Scheduler\ExecutionModeOrchestrator;
use Symfony\Component\Scheduler\Task\TaskFactoryInterface;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Task\TaskList;
use Symfony\Component\Scheduler\Task\TaskListInterface;
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
        'transaction_mode' => 'multi',
    ];

    private $connection;
    private $factory;
    private $queue;
    private $orchestrator;
    private $transactionMode;

    public function __construct(Dsn $dsn, TaskFactoryInterface $factory, ?\Redis $redis = null)
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
            throw new InvalidArgumentException(sprintf('Redis connection failed: "%s".', $redis->getLastError()));
        }

        if (($this->queue = $dsn->getOption('dbindex') ?? self::DEFAULT_OPTIONS['dbindex']) && !$this->connection->select($this->queue)) {
            throw new InvalidArgumentException(sprintf('Redis connection failed: "%s".', $redis->getLastError()));
        }

        if (($transactionMode = $dsn->getOption('transaction_mode')) && !\in_array($transactionMode, ['pipeline', 'multi'])) {
            throw new InvalidArgumentException(sprintf('The transaction mode "%s" is not a valid one.', $transactionMode));
        }

        $this->factory = $factory;
        $this->transactionMode = $transactionMode ?? self::DEFAULT_OPTIONS['transaction_mode'];
        $this->orchestrator = new ExecutionModeOrchestrator($dsn->getOption('execution_mode') ?? ExecutionModeOrchestrator::FIFO);
    }

    public static function createFromDsn(Dsn $dsn, TaskFactoryInterface $factory, ?\Redis $redis = null): self
    {
        return new self($dsn, $factory, $redis);
    }

    /**
     * {@inheritdoc}
     */
    public function list(): TaskListInterface
    {
        $taskList = new TaskList();

        $this->transactional(function (\Redis $redis) use ($taskList): void {
           $data = $redis->keys('*');

           $tasks = json_decode($data, true);

           if (0 !== \count($tasks)) {
               foreach (json_decode($data, true) as $task) {
                   $taskList->add($this->factory->create($task));
               }
           }
        });

        return $taskList;
    }

    /**
     * {@inheritdoc}
     */
    public function create(TaskInterface $task): void
    {
        $result = $this->transactional(function (\Redis $redis) use ($task): bool {
            return $redis->setnx($task->getName(), json_encode([
                'name' => $task->getName(),
                'expression' => $task->getExpression(),
                'state' => $task->get('state'),
                'options' => $task->getOptions(),
                'type' => $task->getType(),
            ]));
        });

        if (false === $result) {
            throw new LogicException(sprintf('The task cannot be created as it already exist, consider using "%s::update().', self::class));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $taskName): TaskInterface
    {
        $data = $this->connection->get($taskName);

        if (false === $data) {
            throw new InvalidArgumentException('The task does not exist');
        }

        return $this->factory->create(json_decode($data, true));
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $taskName, TaskInterface $updatedTask): void
    {
        $res = $this->transactional(function (\Redis $redis) use ($taskName, $updatedTask): bool {
            return $redis->set($taskName, json_encode([
                'name' => $updatedTask->getName(),
                'expression' => $updatedTask->getExpression(),
                'state' => $updatedTask->get('state'),
                'options' => $updatedTask->getOptions(),
                'type' => $updatedTask->getType(),
            ]));
        });

        if (false === $res) {
            throw new LogicException(sprintf('The task cannot be updated, error: %s', $this->connection->getLastError()));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function pause(string $taskName): void
    {
        $this->transactional(function (\Redis $redis) use ($taskName): void {
            $task = $redis->get($taskName);
            if (false === $task) {
                throw new InvalidArgumentException('The task does not exist');
            }

            $data = json_decode($task, true);
            $data['state'] = State::PAUSED;

            $updateState = $redis->rPush($taskName, json_encode($data));
            if (false === $updateState) {
                throw new InvalidArgumentException('The task cannot be updated');
            }
        });
    }

    /**
     * {@inheritdoc}
     */
    public function resume(string $taskName): void
    {
        $this->transactional(function (\Redis $redis) use ($taskName): void {
            $task = $redis->get($taskName);
            if (false === $task) {
                throw new InvalidArgumentException('The task does not exist');
            }

            $data = json_decode($task, true);
            $data['state'] = State::ENABLED;

            $updateState = $redis->rPush($taskName, json_encode($data));
            if (false === $updateState) {
                throw new InvalidArgumentException('The task cannot be updated');
            }
        });
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $taskName): void
    {
        $res = $this->transactional(function (\Redis $redis) use ($taskName): int {
            return $redis->del($taskName);
        });

        if (0 === $res) {
            throw new LogicException('The task cannot be deleted as it does not exist');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function empty(): void
    {
        $this->transactional(function (\Redis $redis): bool {
            return $redis->flushDB();
        });
    }

    /**
     * Execute a function in a transaction.
     *
     * The function receive the Redis::MULTI instance returned by {@see Redis::multi()}.
     *
     * @param \Closure $func The function to execute
     *
     * @return mixed The value returned by $func
     *
     * @throws LogicException if an exception is thrown during the callback of {@see Redis::exec()}
     */
    private function transactional(\Closure $func)
    {
        $redis = $this->connection->multi($this->transactionMode === 'pipeline' ? \Redis::PIPELINE : \Redis::MULTI);

        try {
            $res = $func($redis);
            $redis->exec();

            return $res;
        } catch (\Throwable $exception) {
            $redis->discard();
            throw new TransportException($exception->getMessage(), 0, $exception);
        }
    }
}
