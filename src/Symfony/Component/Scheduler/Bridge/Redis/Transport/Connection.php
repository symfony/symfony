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

use Symfony\Component\Scheduler\Exception\AlreadyScheduledTaskException;
use Symfony\Component\Scheduler\Exception\InvalidArgumentException;
use Symfony\Component\Scheduler\Exception\LogicException;
use Symfony\Component\Scheduler\Exception\TransportException;
use Symfony\Component\Scheduler\ExecutionModeOrchestrator;
use Symfony\Component\Scheduler\Task\AbstractTask;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Task\TaskList;
use Symfony\Component\Scheduler\Task\TaskListInterface;
use Symfony\Component\Scheduler\Transport\ConnectionInterface;
use Symfony\Component\Scheduler\Transport\Dsn;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class Connection implements ConnectionInterface
{
    private const DEFAULT_OPTIONS = [
        'host' => '127.0.0.1',
        'port'    => 6379,
        'timeout' => 30,
        'dbindex' => 'sf_app_scheduler_tasks',
        'transaction_mode' => 'multi',
    ];

    private $connection;
    private $queue;
    private $orchestrator;
    private $serializer;
    private $transactionMode;

    public function __construct(Dsn $dsn, SerializerInterface $serializer, ?\Redis $redis = null)
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

        $this->serializer = $serializer;
        $this->transactionMode = $transactionMode ?? self::DEFAULT_OPTIONS['transaction_mode'];
        $this->orchestrator = new ExecutionModeOrchestrator($dsn->getOption('execution_mode') ?? ExecutionModeOrchestrator::FIFO);
    }

    public static function createFromDsn(Dsn $dsn, SerializerInterface $serializer, ?\Redis $redis = null): self
    {
        return new self($dsn, $serializer, $redis);
    }

    /**
     * {@inheritdoc}
     */
    public function list(): TaskListInterface
    {
        $taskList = new TaskList();

        $this->transactional(function (\Redis $redis) use ($taskList): void {
           $keys = $redis->keys('*');

           if (0 === \count($keys)) {
               return;
           }

           foreach ($keys as $key) {
               $data = $redis->get($key);
               $task = $this->serializer->deserialize($data, TaskInterface::class, 'json');

               $taskList->add($task);
           }
        });

        return $taskList;
    }

    /**
     * {@inheritdoc}
     */
    public function create(TaskInterface $task): void
    {
        $currentList = $this->list();

        if (\array_key_exists($task->getName(), $currentList->toArray())) {
            throw new AlreadyScheduledTaskException(sprintf('The following task "%s" has already been scheduled!', $task->getName()));
        }

        $currentList->add($task);

        $sortedList = $this->orchestrator->sort($currentList->toArray());

        foreach ($sortedList as $sortedTask) {
            $this->update($sortedTask->getName(), $sortedTask);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $taskName): TaskInterface
    {
        $task = $this->transactional(function (\Redis $redis) use ($taskName) {
            return $redis->get($taskName);
        });

        if (false === $task) {
            throw new InvalidArgumentException(sprintf('The task "%s" does not exist', $taskName));
        }

        return $this->serializer->deserialize($task, TaskInterface::class, 'json');
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $taskName, TaskInterface $updatedTask): void
    {
        $result = $this->transactional(function (\Redis $redis) use ($taskName, $updatedTask): bool {
            $task = $this->serializer->serialize($updatedTask, 'json');

            return $redis->set($taskName, $task);
        });

        if (false === $result) {
            throw new LogicException(sprintf('The task cannot be updated, error: %s', $this->connection->getLastError()));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function pause(string $taskName): void
    {
        $this->transactional(function (\Redis $redis) use ($taskName): void {
            $data = $redis->get($taskName);
            if (false === $data) {
                throw new InvalidArgumentException('The task does not exist');
            }

            $task = $this->serializer->deserialize($data, TaskInterface::class, 'json');
            $task->set('state', AbstractTask::PAUSED);

            $data = $this->serializer->serialize($task, 'json');

            $updateState = $redis->rPush($taskName, $data);
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
            $data = $redis->get($taskName);
            if (false === $data) {
                throw new InvalidArgumentException('The task does not exist');
            }

            $task = $this->serializer->deserialize($data, TaskInterface::class, 'json');
            $task->set('state', AbstractTask::ENABLED);

            $data = $this->serializer->serialize($task, 'json');

            $updateState = $redis->rPush($taskName, $data);
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
        $this->transactional(function (\Redis $redis): void {
            $redis->flushDB();
        });
    }

    /**
     * Execute a function in a transaction.
     *
     * The function receive the Redis::MULTI instance returned by {@see Redis::multi()}.
     *
     * @param \Closure $func The function to execute (the current instance of {@see \Redis} is passed as the only argument)
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
