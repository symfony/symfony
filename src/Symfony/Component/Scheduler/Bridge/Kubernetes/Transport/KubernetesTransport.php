<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Bridge\Kubernetes\Transport;

use Symfony\Component\Scheduler\Bridge\Kubernetes\Task\CronJob;
use Symfony\Component\Scheduler\Exception\InvalidArgumentException;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Task\TaskListInterface;
use Symfony\Component\Scheduler\Transport\Dsn;
use Symfony\Component\Scheduler\Transport\TransportInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class KubernetesTransport implements TransportInterface
{
    private $connection;
    private $options;
    private $serializer;
    private $httpClient;

    public function __construct(Dsn $dsn, array $options, SerializerInterface $serializer)
    {
        $this->options = array_merge($dsn->getOptions(), $options);
        $this->serializer = $serializer;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $taskName): TaskInterface
    {
    }

    /**
     * {@inheritdoc}
     */
    public function list(): TaskListInterface
    {
    }

    /**
     * {@inheritdoc}
     */
    public function create(TaskInterface $task): void
    {
        if (!$task instanceof CronJob) {
            throw new InvalidArgumentException('');
        }

        $this->connection->create($task);
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $taskName, TaskInterface $updatedTask): void
    {
        // TODO: Implement update() method.
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
        // TODO: Implement pause() method.
    }

    /**
     * {@inheritdoc}
     */
    public function resume(string $taskName): void
    {
        // TODO: Implement resume() method.
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
