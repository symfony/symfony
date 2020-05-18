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

use Symfony\Component\Scheduler\Exception\LogicException;
use Symfony\Component\Scheduler\Task\FactoryInterface;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Task\TaskList;
use Symfony\Component\Scheduler\Task\TaskListInterface;
use Symfony\Component\Scheduler\Transport\Dsn;
use Symfony\Component\Scheduler\Transport\TransportInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class RedisTransport implements TransportInterface
{
    private $connection;
    private $options;
    private $serializer;
    private $taskFactory;

    public function __construct(Dsn $dsn, array $options, SerializerInterface $serializer, FactoryInterface $taskFactory)
    {
        $this->connection = Connection::createFromDsn($dsn);
        $this->options = $options;
        $this->serializer = $serializer;
        $this->taskFactory = $taskFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $taskName): TaskInterface
    {
        $task = $this->connection->get($taskName);

        if (!$this->taskFactory->support($task['type'])) {
            throw new LogicException('');
        }

        return $this->taskFactory->create($task);
    }

    /**
     * {@inheritdoc}
     */
    public function list(): TaskListInterface
    {
        return new TaskList($this->connection->list());
    }

    /**
     * {@inheritdoc}
     */
    public function create(TaskInterface $task): void
    {
        $body = $this->serializer->serialize($task->getFormattedInformations(), 'json');

        $this->connection->add($task->getName(), $body);
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $taskName, TaskInterface $updatedTask): void
    {
        $this->connection->update($taskName, $updatedTask->getFormattedInformations());
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $taskName): void
    {
        $this->connection->delete($taskName);
    }

    /**
     * {@inheritdoc}
     */
    public function pause(string $taskName): void
    {
        $this->connection->pause($taskName);
    }

    /**
     * {@inheritdoc}
     */
    public function resume(string $taskName): void
    {
        $this->connection->resume($taskName);
    }

    /**
     * {@inheritdoc}
     */
    public function empty(): void
    {
        $this->connection->empty();
    }

    /**
     * {@inheritdoc}
     */
    public function getOptions(): array
    {
        return $this->options;
    }
}
