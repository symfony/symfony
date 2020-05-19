<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Worker;

use Symfony\Component\Scheduler\EventListener\WorkerSubscriberInterface;
use Symfony\Component\Scheduler\Exception\InvalidArgumentException;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class WorkerRegistry implements WorkerRegistryInterface
{
    /**
     * @var WorkerInterface[]
     */
    private $workers = [];
    private $subscribers;

    /**
     * @param iterable|WorkerSubscriberInterface[] $subscribers
     */
    public function __construct(iterable $subscribers)
    {
        $this->subscribers = $subscribers;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $name): WorkerInterface
    {
        if (!$this->has($name)) {
            throw new InvalidArgumentException(sprintf('The "%s" worker cannot be found.', $name));
        }

        return $this->workers[$name];
    }

    /**
     * {@inheritdoc}
     */
    public function filter(\Closure $filter): array
    {
        return array_filter($this->workers, $filter, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $name): bool
    {
        return \array_key_exists($name, $this->workers);
    }

    /**
     * {@inheritdoc}
     */
    public function register(string $name, WorkerInterface $worker): void
    {
        if ($this->has($name)) {
            throw new InvalidArgumentException(sprintf('The "%s" worker already exist, please consider using %s::override()', $name, self::class));
        }

        foreach ($this->subscribers as $workerSubscriber) {
            if (\in_array($name, $workerSubscriber::getSubscribedWorkers()) || \in_array('*', $workerSubscriber::getSubscribedWorkers())) {
                $worker->addSubscriber($workerSubscriber);
            }
        }

        $this->workers[$name] = $worker;
    }

    /**
     * {@inheritdoc}
     */
    public function remove(string $name): void
    {
        if (!$this->has($name)) {
            throw new InvalidArgumentException(sprintf('The "%s" worker cannot be found.', $name));
        }

        unset($this->workers[$name]);
    }

    /**
     * {@inheritdoc}
     */
    public function override(string $name, WorkerInterface $worker): void
    {
        if (!$this->has($name)) {
            throw new InvalidArgumentException(sprintf('The "%s" worker cannot be found, please consider using %s::register()', $name, self::class));
        }

        $this->workers[$name] = $worker;
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        return $this->workers;
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        return \count($this->workers);
    }
}
