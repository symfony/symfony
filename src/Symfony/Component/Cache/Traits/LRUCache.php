<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Traits;

use Symfony\Component\Cache\MemoryUsageCalculator;

trait LRUCache
{
    use LRUMemoryTracker;

    private MemoryUsageCalculator $memoryUsageCalculator;

    private ?int $maxItems;

    private ?int $softMemoryLimit;

    private ?int $hardMemoryLimit;

    /**
     * @var array<string, Node>
     */
    private array $map = [];

    private ?Node $head = null;

    private ?Node $tail = null;

    private function init(
        MemoryUsageCalculator $memoryUsageCalculator,
        ?int $maxItems,
        ?int $softMemoryLimit,
        ?int $hardMemoryLimit,
    ): void {
        $this->memoryUsageCalculator = $memoryUsageCalculator;

        $this->maxItems = $maxItems;
        $this->softMemoryLimit = $softMemoryLimit;
        $this->hardMemoryLimit = $hardMemoryLimit;
    }

    private function doGet(string $key, \DateTimeImmutable $at): mixed
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

    private function doHas(string $key, \DateTimeImmutable $at): bool
    {
        $node = $this->map[$key] ?? null;
        if (null === $node) {
            return false;
        }

        return !$this->isExpired($node, $at);
    }

    private function doSet(string $key, mixed $value, \DateTimeImmutable $at, ?int $ttl = null): void
    {
        $size = $this->estimateMemorySize($value);

        if (isset($this->map[$key])) {
            $this->remove($this->map[$key]);
        }

        $expiresAt = null !== $ttl ? $at->getTimestamp() + $ttl : null;
        $node = new Node($key, $value, $size, $expiresAt);
        $this->addToFront($node);
        $this->map[$key] = $node;

        $this->allocateMemorySize($size);
        $this->tick($this->memoryUsageCalculator);
        $this->evictIfNeeded($at);
    }

    private function doDel(string $key): void
    {
        if (!isset($this->map[$key])) {
            return;
        }

        $this->remove($this->map[$key]);
    }

    private function doClear(): void
    {
        $this->map = [];
        $this->head = null;
        $this->tail = null;
        $this->restartTracking();
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

    private function evictExpired(\DateTimeImmutable $at): void
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
        $this->releaseMemorySize($node->size);
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
        public int $size,
        public ?int $expiresAt,
        public ?self $prev = null,
        public ?self $next = null,
    ) {
    }
}
