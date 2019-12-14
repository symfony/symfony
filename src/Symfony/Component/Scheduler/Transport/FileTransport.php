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
final class FileTransport implements TransportInterface
{
    private $options;
    private $savePath;

    public function __construct(Dsn $dsn, array $options)
    {
        $this->options = array_merge($dsn->getOptions(), $options);

        if (!\array_key_exists('save_path', $this->options)) {
            $this->savePath = sys_get_temp_dir();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $taskName): TaskInterface
    {
        // TODO: Implement get() method.
    }

    /**
     * {@inheritdoc}
     */
    public function list(): TaskListInterface
    {
        // TODO: Implement list() method.
    }

    /**
     * {@inheritdoc}
     */
    public function create(TaskInterface $task): void
    {
        // TODO: Implement create() method.
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
        // TODO: Implement delete() method.
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
        // TODO: Implement empty() method.
    }

    /**
     * {@inheritdoc}
     */
    public function getOptions(): array
    {
        return $this->options;
    }
}
