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

namespace Symfony\Component\Scheduler\Cron;

use Symfony\Component\Scheduler\Exception\InvalidArgumentException;
use Symfony\Component\Scheduler\RegistryInterface;
use function array_key_exists;
use function array_filter;
use function count;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class CronRegistry implements RegistryInterface
{
    /**
     * @var array<string,CronInterface>
     */
    private $crons = [];

    /**
     * {@inheritdoc}
     */
    public function get(string $name): CronInterface
    {
        if (!$this->has($name)) {
            throw new InvalidArgumentException(sprintf('The "%s" cron cannot be found.', $name));
        }

        return $this->crons[$name];
    }

    /**
     * {@inheritdoc}
     */
    public function filter(\Closure $filter): array
    {
        return array_filter($this->crons, $filter, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $name): bool
    {
        return array_key_exists($name, $this->crons);
    }

    /**
     * {@inheritdoc}
     */
    public function register(string $name, CronInterface $worker): void
    {
        if ($this->has($name)) {
            throw new InvalidArgumentException(sprintf('The "%s" cron already exist, please consider using %s::override()', $name, self::class));
        }

        $this->crons[$name] = $worker;
    }

    /**
     * {@inheritdoc}
     */
    public function remove(string $name): void
    {
        if (!$this->has($name)) {
            throw new InvalidArgumentException(sprintf('The "%s" cron cannot be found.', $name));
        }

        unset($this->crons[$name]);
    }

    public function override(string $name, CronInterface $worker): void
    {
        if (!$this->has($name)) {
            throw new InvalidArgumentException(sprintf('The "%s" cron cannot be found, please consider using %s::register()', $name, self::class));
        }

        $this->crons[$name] = $worker;
    }

    /**
     * @return array<string,CronInterface>
     */
    public function toArray(): array
    {
        return $this->crons;
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return count($this->crons);
    }
}
