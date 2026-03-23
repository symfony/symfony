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
use Symfony\Component\Clock\NativeClock;

final class InMemoryLRUSimpleCache implements CacheInterface, PruneableInterface
{
    private ClockInterface $clock;

    private Storage $storage;

    public function __construct(
        ?ClockInterface $clock = null,
        ?MemoryUsageCalculator $memoryUsageCalculator = null,
        ?int $maxItems = null,
        ?int $softMemoryLimit = null,
        ?int $hardMemoryLimit = null,
    ) {
        $this->clock = $clock ?? new NativeClock();
        $this->storage = new Storage(
            $memoryUsageCalculator ?? new PhpMemoryUsageCalculator(),
            $maxItems,
            $softMemoryLimit,
            $hardMemoryLimit,
        );
    }

    public function get($key, $default = null): mixed
    {
        $this->assertValidKey($key);
        $now = $this->clock->now();
        $hasKey = $this->storage->has($key, $now);
        $value = $this->storage->get((string) $key, $now);

        // returns default only if the key was not present
        return $hasKey ? $value : $default;
    }

    public function set($key, $value, $ttl = null): bool
    {
        $this->assertValidKey($key);
        $this->assertValidValue($value);

        $this->storage->set((string) $key, $value, $this->clock->now(), $this->normalizeTtl($ttl));

        return true;
    }

    public function delete($key): bool
    {
        $this->assertValidKey($key);

        $this->storage->del((string) $key);

        return true;
    }

    public function clear(): bool
    {
        $this->storage->clear();

        return true;
    }

    public function getMultiple($keys, $default = null): iterable
    {
        $result = [];

        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }

        return $result;
    }

    public function setMultiple(
        $values,
        $ttl = null,
    ): bool {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }

        return true;
    }

    public function deleteMultiple($keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    public function has($key): bool
    {
        $this->assertValidKey($key);

        return $this->storage->has((string) $key, $this->clock->now());
    }

    public function prune(): bool
    {
        $this->storage->evictExpired($this->clock->now());

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

    private function assertValidValue(mixed $value): void
    {
        if ($value instanceof \Closure) {
            throw new InvalidArgumentException('Closures cannot be cached.');
        }
    }
}

class Storage
{
    /**
     * @var array<string, Node>
     */
    private array $map = [];

    private ?Node $head = null;

    private ?Node $tail = null;

    public function __construct(
        private MemoryUsageCalculator $memoryUsageCalculator,
        private ?int $maxItems,
        private ?int $softMemoryLimit,
        private ?int $hardMemoryLimit,
    ) {
    }

    public function get(string $key, \DateTimeImmutable $at): mixed
    {
        $node = $this->map[$key] ?? null;
        if (!$node) {
            return null;
        }
        if ($this->isExpired($node, $at)) {
            $this->remove($node);

            return null;
        }

        $this->moveToFront($node);

        return $node->value;
    }

    public function has(string $key, \DateTimeImmutable $at): bool
    {
        $node = $this->map[$key] ?? null;
        if (null === $node) {
            return false;
        }

        return !$this->isExpired($node, $at);
    }

    public function set(string $key, mixed $value, \DateTimeImmutable $at, ?int $ttl = null): void
    {
        if (isset($this->map[$key])) {
            $this->remove($this->map[$key]);
        }

        $expiresAt = null !== $ttl ? $at->getTimestamp() + $ttl : null;
        $node = new Node($key, $value, $expiresAt);
        $this->addToFront($node);
        $this->map[$key] = $node;

        $this->evictIfNeeded($at);
    }

    public function del(string $key): void
    {
        if (!isset($this->map[$key])) {
            return;
        }

        $this->remove($this->map[$key]);
    }

    public function clear(): void
    {
        $this->map = [];
        $this->head = null;
        $this->tail = null;
    }

    private function isExpired(Node $node, \DateTimeImmutable $at): bool
    {
        return null !== $node->expiresAt && $node->expiresAt <= $at->getTimestamp();
    }

    private function evictIfNeeded(\DateTimeImmutable $at): void
    {
        $this->evictHardLimited($at);
        $this->evictSoftLimited($at);
        $this->evictMaxItemsLimited($at);
        $this->evictExpiredDuringUnlimited($at);
    }

    private function evictHardLimited(\DateTimeImmutable $at): void
    {
        if (null === $this->hardMemoryLimit) {
            return;
        }

        if ($this->memoryUsageCalculator->calculate() <= $this->hardMemoryLimit) {
            return;
        }

        $this->evictExpired($at);
        $this->evictBatch(0.2);

        if (null === $this->softMemoryLimit) {
            return;
        }

        while ($this->memoryUsageCalculator->calculate() > $this->softMemoryLimit) {
            $this->evictBatch(0.2);
            if (!$this->tail) {
                break;
            }
        }
    }

    private function evictSoftLimited(\DateTimeImmutable $at): void
    {
        if (null === $this->softMemoryLimit) {
            return;
        }
        if ($this->memoryUsageCalculator->calculate() <= $this->softMemoryLimit) {
            return;
        }

        $this->evictExpired($at);
        $target = (int) ($this->softMemoryLimit * 0.9); // hysteresis target

        while ($this->memoryUsageCalculator->calculate() > $target) {
            $this->evictBatch(0.05);
            if (!$this->tail) {
                break;
            }
        }
    }

    private function evictMaxItemsLimited(\DateTimeImmutable $at): void
    {
        if (null === $this->maxItems) {
            return;
        }
        if (\count($this->map) <= $this->maxItems) {
            return;
        }
        $this->evictExpired($at);

        while (\count($this->map) > $this->maxItems) {
            $this->evictOne();
        }
    }

    private function evictExpiredDuringUnlimited(\DateTimeImmutable $at): void
    {
        // at least one limiter is enabled, skip
        if (null !== $this->softMemoryLimit
            || null !== $this->hardMemoryLimit
            || null !== $this->maxItems) {
            return;
        }
        $this->evictExpired($at);
    }

    public function evictExpired(\DateTimeImmutable $at): void
    {
        while ($this->tail) {
            if (!$this->isExpired($this->tail, $at)) {
                break;
            }
            $this->remove($this->tail);
        }
    }

    private function evictBatch(float $ratio): void
    {
        $count = max(1, (int) (\count($this->map) * $ratio));

        for ($i = 0; $i < $count; ++$i) {
            $this->evictOne();
        }
    }

    private function evictOne(): void
    {
        if (null === $this->tail) {
            return;
        }
        $this->remove($this->tail);
    }

    private function remove(Node $node): void
    {
        unset($this->map[$node->key]);
        $this->unlink($node);
    }

    private function unlink(Node $node): void
    {
        if ($node->prev) {
            $node->prev->next = $node->next;
        }
        if ($node->next) {
            $node->next->prev = $node->prev;
        }

        if ($this->head === $node) {
            $this->head = $node->next;
        }
        if ($this->tail !== $node) {
            return;
        }

        $this->tail = $node->prev;
    }

    private function addToFront(Node $node): void
    {
        $node->prev = null;
        $node->next = $this->head;

        if ($this->head) {
            /* @noinspection PhpFieldImmediatelyRewrittenInspection */
            $this->head->prev = $node;
        }

        $this->head = $node;

        if ($this->tail) {
            return;
        }

        $this->tail = $node;
    }

    private function moveToFront(Node $node): void
    {
        if ($this->head === $node) {
            return;
        }

        $this->unlink($node);
        $this->addToFront($node);
    }
}

final class Node
{
    public function __construct(
        public string $key,
        public mixed $value,
        public ?int $expiresAt,
        public ?self $prev = null,
        public ?self $next = null,
    ) {
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
