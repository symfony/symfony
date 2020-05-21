<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Transport;

use RuntimeException;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Task\TaskListInterface;
use function array_merge;

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
    public function get(string $taskName): TaskInterface
    {
        throw new RuntimeException(sprintf('The %s() cannot be called on %s transport!', __METHOD__, __CLASS__));
    }

    /**
     * {@inheritdoc}
     */
    public function list(): TaskListInterface
    {
        throw new RuntimeException(sprintf('The %s() cannot be called on %s transport!', __METHOD__, __CLASS__));
    }

    /**
     * {@inheritdoc}
     */
    public function create(TaskInterface $task): void
    {
        throw new RuntimeException(sprintf('The %s() cannot be called on %s transport!', __METHOD__, __CLASS__));
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $taskName, TaskInterface $updatedTask): void
    {
        throw new RuntimeException(sprintf('The %s() cannot be called on %s transport!', __METHOD__, __CLASS__));
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $taskName): void
    {
        throw new RuntimeException(sprintf('The %s() cannot be called on %s transport!', __METHOD__, __CLASS__));
    }

    /**
     * {@inheritdoc}
     */
    public function pause(string $taskName): void
    {
        throw new RuntimeException(sprintf('The %s() cannot be called on %s transport!', __METHOD__, __CLASS__));
    }

    /**
     * {@inheritdoc}
     */
    public function resume(string $taskName): void
    {
        throw new RuntimeException(sprintf('The %s() cannot be called on %s transport!', __METHOD__, __CLASS__));
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): void
    {
        throw new RuntimeException(sprintf('The %s() cannot be called on %s transport!', __METHOD__, __CLASS__));
    }

    /**
     * @return array<string,mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }
}
