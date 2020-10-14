<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\RateLimiter;

use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Lock\NoLock;
use Symfony\Component\RateLimiter\Storage\StorageInterface;
use Symfony\Component\RateLimiter\Util\TimeUtil;

/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @experimental in 5.2
 */
final class FixedWindowLimiter implements LimiterInterface
{
    private $id;
    private $limit;
    private $interval;
    private $storage;
    private $lock;

    use ResetLimiterTrait;

    public function __construct(string $id, int $limit, \DateInterval $interval, StorageInterface $storage, ?LockInterface $lock = null)
    {
        $this->storage = $storage;
        $this->lock = $lock ?? new NoLock();
        $this->id = $id;
        $this->limit = $limit;
        $this->interval = TimeUtil::dateIntervalToSeconds($interval);
    }

    /**
     * {@inheritdoc}
     */
    public function consume(int $tokens = 1): Limit
    {
        $this->lock->acquire(true);

        try {
            $window = $this->storage->fetch($this->id);
            if (!$window instanceof Window) {
                $window = new Window($this->id, $this->interval);
            }

            $hitCount = $window->getHitCount();
            $availableTokens = $this->getAvailableTokens($hitCount);
            $windowStart = \DateTimeImmutable::createFromFormat('U', time());
            if ($availableTokens < $tokens) {
                return new Limit($availableTokens, $this->getRetryAfter($windowStart), false);
            }

            $window->add($tokens);
            $this->storage->save($window);

            return new Limit($this->getAvailableTokens($window->getHitCount()), $this->getRetryAfter($windowStart), true);
        } finally {
            $this->lock->release();
        }
    }

    public function getAvailableTokens(int $hitCount): int
    {
        return $this->limit - $hitCount;
    }

    private function getRetryAfter(\DateTimeImmutable $windowStart): \DateTimeImmutable
    {
        return $windowStart->add(new \DateInterval(sprintf('PT%sS', $this->interval)));
    }
}
