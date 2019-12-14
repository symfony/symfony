<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler;

use Symfony\Component\Scheduler\Exception\InvalidArgumentException;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SchedulerRegistry implements SchedulerRegistryInterface
{
    /**
     * @var SchedulerInterface[]
     */
    private $schedulers = [];

    /**
     * {@inheritdoc}
     */
    public function get(string $name): SchedulerInterface
    {
        if (!$this->has($name)) {
            throw new InvalidArgumentException(sprintf('The "%s" does not exist.', $name));
        }

        return $this->schedulers[$name];
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $name): bool
    {
        return \array_key_exists($name, $this->schedulers);
    }

    /**
     * {@inheritdoc}
     */
    public function register(string $name, SchedulerInterface $scheduler): void
    {
        if ($this->has($name)) {
            throw new InvalidArgumentException(sprintf('The "%s" scheduler is already registered, consider using %s::override() if it need to be override', $name, self::class));
        }

        $this->schedulers[$name] = $scheduler;
    }

    /**
     * {@inheritdoc}
     */
    public function filter(\Closure $filter): array
    {
        return array_filter($this->schedulers, $filter, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * {@inheritdoc}
     */
    public function remove(string $name): void
    {
        if (!$this->has($name)) {
            throw new InvalidArgumentException(sprintf('The "%s" scheduler does not exist.', $name));
        }

        unset($this->schedulers[$name]);
    }

    /**
     * {@inheritdoc}
     */
    public function override(string $name, SchedulerInterface $scheduler): void
    {
        if (!$this->has($name)) {
            throw new InvalidArgumentException(sprintf('The "%s" does not exist.', $name));
        }

        $this->schedulers[$name] = $scheduler;
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        return $this->schedulers;
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        return \count($this->schedulers);
    }
}
