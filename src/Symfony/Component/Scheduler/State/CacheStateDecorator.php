<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\State;

use Symfony\Contracts\Cache\CacheInterface;

final class CacheStateDecorator implements StateInterface
{
    public function __construct(
        private readonly StateInterface $inner,
        private readonly CacheInterface $cache,
        private readonly string $name,
    ) {
    }

    public function acquire(\DateTimeImmutable $now): bool
    {
        if (!$this->inner->acquire($now)) {
            return false;
        }

        $this->inner->save(...$this->cache->get($this->name, fn () => [$now, -1]));

        return true;
    }

    public function time(): \DateTimeImmutable
    {
        return $this->inner->time();
    }

    public function index(): int
    {
        return $this->inner->index();
    }

    public function save(\DateTimeImmutable $time, int $index): void
    {
        $this->inner->save($time, $index);
        $this->cache->get($this->name, fn () => [$time, $index], \INF);
    }

    public function release(\DateTimeImmutable $now, ?\DateTimeImmutable $nextTime): void
    {
        $this->inner->release($now, $nextTime);
    }
}
