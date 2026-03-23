<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache;

use Psr\Clock\ClockInterface;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Component\Cache\Traits\LRUCache;
use Symfony\Component\Clock\NativeClock;

final class InMemoryLRUSimpleCache implements CacheInterface, PruneableInterface
{
    use LRUCache;

    private ClockInterface $clock;

    public function __construct(
        ?ClockInterface $clock = null,
        ?MemoryUsageCalculator $memoryUsageCalculator = null,
        ?int $maxItems = null,
        ?int $softMemoryLimit = null,
        ?int $hardMemoryLimit = null,
    ) {
        $this->clock = $clock ?? new NativeClock();
        $memoryUsageCalculator ??= new class implements MemoryUsageCalculator {
            public function calculate(): int
            {
                return memory_get_usage(true);
            }
        };
        $this->init($memoryUsageCalculator, $maxItems, $softMemoryLimit, $hardMemoryLimit);
    }

    #[\Override]
    public function get($key, $default = null): mixed
    {
        $this->assertValidKey($key);
        $now = $this->clock->now();
        $hasKey = $this->doHas($key, $now);
        $value = $this->doGet((string) $key, $now);

        // returns default only if the key was not present
        return $hasKey ? $value : $default;
    }

    #[\Override]
    public function set($key, $value, $ttl = null): bool
    {
        $this->assertValidKey($key);

        $this->doSet((string) $key, $value, $this->clock->now(), $this->normalizeTtl($ttl));

        return true;
    }

    #[\Override]
    public function delete($key): bool
    {
        $this->assertValidKey($key);

        $this->doDel((string) $key);

        return true;
    }

    #[\Override]
    public function clear(): bool
    {
        $this->doClear();

        return true;
    }

    #[\Override]
    public function getMultiple($keys, $default = null): iterable
    {
        $result = [];

        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }

        return $result;
    }

    #[\Override]
    public function setMultiple(
        $values,
        $ttl = null,
    ): bool {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }

        return true;
    }

    #[\Override]
    public function deleteMultiple($keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    #[\Override]
    public function has($key): bool
    {
        $this->assertValidKey($key);

        return $this->doHas((string) $key, $this->clock->now());
    }

    public function prune(): bool
    {
        $this->evictExpired($this->clock->now());

        return true;
    }

    private function normalizeTtl(int|\DateInterval|null $ttl): ?int
    {
        if (null === $ttl) {
            return null;
        }

        if ($ttl instanceof \DateInterval) {
            $now = $this->clock->now();

            return (int) ($now->add($ttl)->format('U') - $now->format('U'));
        }

        return (int) $ttl;
    }

    private function assertValidKey(int|string $key): void
    {
        if ('' === $key) {
            throw new InvalidArgumentException('Key cannot be empty.');
        }

        if (preg_match('#[{}()/\\\\@:]#', (string) $key)) {
            throw new InvalidArgumentException('Invalid key characters - Key: '.$key);
        }
    }
}
