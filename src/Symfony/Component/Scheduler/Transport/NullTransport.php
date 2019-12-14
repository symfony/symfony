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

use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Task\TaskListInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class NullTransport implements TransportInterface
{
    private $dsn;
    private $options;

    /**
     * {@inheritdoc}
     */
    public function __construct(Dsn $dsn, array $options = [])
    {
        $this->dsn = $dsn;
        $this->options = array_merge($dsn->getOptions(), $options);
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $configuration): bool
    {
        return 0 === strpos('null://', $configuration);
    }

    /**
     * {@inheritdoc}
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $taskName): TaskInterface
    {
        throw new \RuntimeException(sprintf('The %s() cannot be called on %s transport!', __METHOD__, __CLASS__));
    }

    /**
     * {@inheritdoc}
     */
    public function list(): TaskListInterface
    {
        throw new \RuntimeException(sprintf('The %s() cannot be called on %s transport!', __METHOD__, __CLASS__));
    }

    /**
     * {@inheritdoc}
     */
    public function create(TaskInterface $task): void
    {
        throw new \RuntimeException(sprintf('The %s() cannot be called on %s transport!', __METHOD__, __CLASS__));
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $taskName, TaskInterface $updatedTask): void
    {
        throw new \RuntimeException(sprintf('The %s() cannot be called on %s transport!', __METHOD__, __CLASS__));
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $taskName): void
    {
        throw new \RuntimeException(sprintf('The %s() cannot be called on %s transport!', __METHOD__, __CLASS__));
    }

    /**
     * {@inheritdoc}
     */
    public function pause(string $taskName): void
    {
        throw new \RuntimeException(sprintf('The %s() cannot be called on %s transport!', __METHOD__, __CLASS__));
    }

    /**
     * {@inheritdoc}
     */
    public function resume(string $taskName): void
    {
        throw new \RuntimeException(sprintf('The %s() cannot be called on %s transport!', __METHOD__, __CLASS__));
    }

    /**
     * {@inheritdoc}
     */
    public function empty(): void
    {
        throw new \RuntimeException(sprintf('The %s() cannot be called on %s transport!', __METHOD__, __CLASS__));
    }
}
