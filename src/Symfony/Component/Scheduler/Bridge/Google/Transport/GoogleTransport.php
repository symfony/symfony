<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Bridge\Google\Transport;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Scheduler\Bridge\Google\Task\JobFactory;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Task\TaskList;
use Symfony\Component\Scheduler\Task\TaskListInterface;
use Symfony\Component\Scheduler\Transport\Dsn;
use Symfony\Component\Scheduler\Transport\TransportInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class GoogleTransport implements TransportInterface
{
    private $client;
    private $dsn;
    private $options;

    /**
     * {@inheritdoc}
     */
    public function __construct(Dsn $dsn, array $options, JobFactory $factory)
    {
        $this->dsn = $dsn;
        $this->options = $options;
        $this->client = new Connection($dsn, HttpClient::create(), $factory);
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $taskName): TaskInterface
    {
        return $this->client->get($taskName);
    }

    /**
     * {@inheritdoc}
     */
    public function list(): TaskListInterface
    {
        return new TaskList($this->client->list());
    }

    /**
     * {@inheritdoc}
     */
    public function create(TaskInterface $task): void
    {
        $this->client->create($task);
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $taskName, TaskInterface $updatedTask): void
    {
        $this->client->patch($taskName, $updatedTask);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $taskName): void
    {
        $this->client->delete($taskName);
    }

    /**
     * {@inheritdoc}
     */
    public function pause(string $taskName): void
    {
        $this->client->pause($taskName);
    }

    /**
     * {@inheritdoc}
     */
    public function resume(string $taskName): void
    {
        $this->client->resume($taskName);
    }

    /**
     * {@inheritdoc}
     */
    public function empty(): void
    {
        foreach ($this->list()->toArray() as $task) {
            $this->delete($task->getName());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getOptions(): array
    {
        return $this->options;
    }
}
