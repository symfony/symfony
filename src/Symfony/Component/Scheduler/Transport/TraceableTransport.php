<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Transport;

use Psr\Log\LoggerInterface;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Task\TaskListInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TraceableTransport implements TransportInterface
{
    private $exceptions = [];
    private $logger;
    private $transport;

    public function __construct(TransportInterface $transport, LoggerInterface $logger = null)
    {
        $this->transport = $transport;
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $taskName): TaskInterface
    {
        try {
            return $this->transport->get($taskName);
        } catch (\Throwable $exception) {
            $this->exceptions[] = $exception->getMessage();

            throw $exception;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function list(): TaskListInterface
    {
        try {
            return $this->transport->list();
        } catch (\Throwable $exception) {
            $this->exceptions[] = $exception->getMessage();

            throw $exception;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function create(TaskInterface $task): void
    {
        try {
            $this->transport->create($task);
        } catch (\Throwable $exception) {
            $this->exceptions[] = $exception->getMessage();

            throw $exception;
        }

        $this->log(sprintf('The task "%s" has been created', $task->getName()));
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $taskName, TaskInterface $updatedTask): void
    {
        try {
            $this->transport->update($taskName, $updatedTask);
        } catch (\Throwable $exception) {
            $this->exceptions[] = $exception->getMessage();

            throw $exception;
        }

        $this->log(sprintf('The task "%s" has been updated', $taskName));
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $taskName): void
    {

        try {
            $this->transport->delete($taskName);
        } catch (\Throwable $exception) {
            $this->exceptions[] = $exception->getMessage();

            throw $exception;
        }

        $this->log(sprintf('The task "%s" has been deleted', $taskName));
    }

    /**
     * {@inheritdoc}
     */
    public function pause(string $taskName): void
    {
        try {
            $this->transport->pause($taskName);
        } catch (\Throwable $exception) {
            $this->exceptions[] = $exception->getMessage();

            throw $exception;
        }

        $this->log(sprintf('The task "%s" has been resumed', $taskName));
    }

    /**
     * {@inheritdoc}
     */
    public function resume(string $taskName): void
    {
        try {
            $this->transport->resume($taskName);
        } catch (\Throwable $exception) {
            $this->exceptions[] = $exception->getMessage();

            throw $exception;
        }

        $this->log(sprintf('The task "%s" has been resumed', $taskName));
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): void
    {
        try {
            $this->transport->clear();
        } catch (\Throwable $exception) {
            $this->exceptions[] = $exception->getMessage();

            throw $exception;
        }

        $this->log(sprintf('The connection has been cleaned'));
    }

    /**
     * {@inheritdoc}
     */
    public function getOptions(): array
    {
        return $this->transport->getOptions();
    }

    public function getExceptionsCount(): int
    {
        return \count($this->exceptions);
    }

    private function log(string $message, array $context = []): void
    {
        if (null === $this->logger) {
            return;
        }

        $this->logger->info($message, array_merge($context, ['connection' => get_class($this->transport)]));
    }
}
