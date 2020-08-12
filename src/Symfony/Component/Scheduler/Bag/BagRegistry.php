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

namespace Symfony\Component\Scheduler\Bag;

use Closure;
use Symfony\Component\Scheduler\Exception\InvalidArgumentException;
use Symfony\Component\Scheduler\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class BagRegistry implements BagRegistryInterface
{
    /**
     * @var array<string,BagInterface>
     */
    private $bags = [];

    public function get(string $name): BagInterface
    {
        if (!$this->has($name)) {
            throw new InvalidArgumentException('The desired bag does not exist.');
        }

        return $this->bags[$name];
    }

    public function register(TaskInterface $task, BagInterface $bag): void
    {
        $name = sprintf('%s_%s_%s', strtr($task->getName(), ['.' => '_']), $task->get('arrival_time')->format('Ymdhi'), $bag->getName());

        if ($this->has($name)) {
            throw new InvalidArgumentException('This bag is already registered.');
        }

        $this->bags[$name] = $bag;
        $task->addBag(sprintf('%s_bag', $bag->getName()), $name);
    }

    public function filter(Closure $filter): array
    {
        return array_filter($this->bags, $filter, ARRAY_FILTER_USE_BOTH);
    }

    public function has(string $name): bool
    {
        return \array_key_exists($name, $this->bags);
    }

    public function remove(string $name): void
    {
        unset($this->bags[$name]);
    }

    /**
     * @return array<string,BagInterface>
     */
    public function toArray(): array
    {
        return $this->bags;
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return \count($this->bags);
    }
}
