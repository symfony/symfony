<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Adapter;

use Psr\Cache\CacheItemInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Cache\ResettableInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\NamespacedPoolInterface;

final class MemoryAwareArrayAdapter implements AdapterInterface, CacheInterface, NamespacedPoolInterface, LoggerAwareInterface, ResettableInterface
{
    private const int MAX_EVICTION_PER_RUN = 10;

    private ArrayAdapter $arrayAdapter;

    private MemoryUsageCalculator $memoryUsageCalculator;

    private int $softMemoryLimit;

    private int $hardMemoryLimit;

    private int $maxEvictionPerRun;

    /**
     * @param string|int|null $softMemoryLimit A memory limit in bytes or a string like '128M'
     * @param string|int|null $hardMemoryLimit A memory limit in bytes or a string like '128M'. If not set, it defaults to php memory_limit
     */
    public function __construct(
        int $defaultLifetime = 0,
        float $maxLifetime = 0,
        int $maxItems = 0,
        string|int|null $softMemoryLimit = null,
        string|int|null $hardMemoryLimit = null,
        ?int $maxEvictionPerRun = null,
        ?MemoryUsageCalculator $memoryUsageCalculator = null,
        ?ClockInterface $clock = null,
    ) {
        if (!$softMemoryLimit && !$hardMemoryLimit) {
            $hardMemoryLimit = \ini_get('memory_limit') ? (int) \ini_get('memory_limit') : 0;
        }
        if (!$softMemoryLimit) {
            $softMemoryLimit = $hardMemoryLimit;
        }
        if (!$hardMemoryLimit) {
            $hardMemoryLimit = $softMemoryLimit;
        }

        $this->maxEvictionPerRun = $maxEvictionPerRun ?? self::MAX_EVICTION_PER_RUN;
        $this->hardMemoryLimit = self::parseMemoryLimit($hardMemoryLimit);
        $this->softMemoryLimit = self::parseMemoryLimit($softMemoryLimit);
        $this->memoryUsageCalculator = $memoryUsageCalculator ?? new PhpMemoryUsageCalculator();
        $this->arrayAdapter = new ArrayAdapter(
            $defaultLifetime,
            false,
            $maxLifetime,
            $maxItems,
            $clock,
        );
    }

    public function getItem(mixed $key): CacheItem
    {
        return $this->arrayAdapter->getItem($key);
    }

    public function getItems(array $keys = []): iterable
    {
        return $this->arrayAdapter->getItems($keys);
    }

    public function clear(string $prefix = ''): bool
    {
        return $this->arrayAdapter->clear($prefix);
    }

    public function get(string $key, callable $callback, ?float $beta = null, ?array &$metadata = null): mixed
    {
        return $this->arrayAdapter->get($key, $callback, $beta, $metadata);
    }

    public function delete(string $key): bool
    {
        return $this->arrayAdapter->delete($key);
    }

    public function hasItem(mixed $key): bool
    {
        return $this->arrayAdapter->hasItem($key);
    }

    public function deleteItem(mixed $key): bool
    {
        return $this->arrayAdapter->deleteItem($key);
    }

    public function deleteItems(array $keys): bool
    {
        return $this->arrayAdapter->deleteItems($keys);
    }

    public function save(CacheItemInterface $item): bool
    {
        $result = $this->arrayAdapter->save($item);
        $this->evictIfNeeded();

        return $result;
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        return $this->save($item);
    }

    public function commit(): bool
    {
        return $this->arrayAdapter->commit();
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->arrayAdapter->setLogger($logger);
    }

    public function withSubNamespace(string $namespace): static
    {
        $new = clone $this;
        $new->arrayAdapter = $this->arrayAdapter->withSubNamespace($namespace);

        return $new;
    }

    public function reset(): void
    {
        $this->arrayAdapter->reset();
    }

    public function getValues(): array
    {
        return $this->arrayAdapter->getValues();
    }

    private function evictIfNeeded(): void
    {
        $this->evictHardLimited();
        $this->evictSoftLimited();
    }

    private function evictHardLimited(): void
    {
        if (!$this->hardMemoryLimit || $this->hardMemoryLimit >= $this->memoryUsageCalculator->calculate()) {
            return;
        }

        $this->evictBatch(0.2);
        $this->evictBatchUntilTarget(0.2, $this->softMemoryLimit);
    }

    private function evictSoftLimited(): void
    {
        if (!$this->softMemoryLimit || $this->softMemoryLimit >= $this->memoryUsageCalculator->calculate()) {
            return;
        }
        $target = (int) ($this->softMemoryLimit * 0.9); // hysteresis target
        $this->evictBatchUntilTarget(0.05, $target);
    }

    private function evictBatchUntilTarget(float $ratio, int $target): void
    {
        if (!$target) {
            return;
        }
        $evicted = 0;
        while ($target < $this->memoryUsageCalculator->calculate() && $this->maxEvictionPerRun > $evicted) {
            if (false === $this->evictBatch($ratio)) {
                break;
            }
            ++$evicted;
        }
    }

    private function evictBatch(float $ratio): bool
    {
        $values = $this->arrayAdapter->getValues();
        if ([] === $values) {
            return false;
        }
        $count = max(1, (int) (\count($values) * $ratio));

        for ($i = 0; $i < $count; ++$i) {
            if (false === $this->evictOne()) {
                return false;
            }
        }

        return true;
    }

    private function evictOne(): bool
    {
        $values = $this->arrayAdapter->getValues();
        if ([] === $values) {
            return false;
        }

        $key = array_key_first($values);

        return $this->delete($key);
    }

    private static function parseMemoryLimit(string|int|null $value): int
    {
        if (null === $value || -1 === $value || '' === $value) {
            return 0;
        }
        if (\is_int($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int) $value;
        }

        $unit = strtolower(substr($value, -1));
        $value = (int) substr($value, 0, -1);
        $multiplier = match ($unit) {
            'k' => 1024,
            'm' => 1024 * 1024,
            'g' => 1024 * 1024 * 1024,
            default => 1,
        };
        $value *= $multiplier;

        return $value;
    }
}

interface MemoryUsageCalculator
{
    public function calculate(): int;
}

final class PhpMemoryUsageCalculator implements MemoryUsageCalculator
{
    public function calculate(): int
    {
        return memory_get_usage(true);
    }
}
